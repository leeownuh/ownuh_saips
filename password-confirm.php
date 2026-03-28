<?php
/**
 * Ownuh SAIPS — POST /auth/password/confirm
 * Consumes a password-reset token (stored in Redis by /auth/password/reset)
 * and sets a new password without requiring the user to be logged in.
 *
 * Request body: { "token": "<raw 64-char hex>", "new_password": "...", "new_password_confirm": "..." }
 * SRS §2.2 + §6.1
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\RateLimitMiddleware;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'code' => 'METHOD_NOT_ALLOWED']);
    exit;
}

// ── Bootstrap ──────────────────────────────────────────────────────────────────
$dbConfig  = require __DIR__ . '/../../config/database.php';
$secConfig = require __DIR__ . '/../../config/security.php';

$pdo = new PDO(
    "mysql:host={$dbConfig['app']['host']};dbname={$dbConfig['app']['name']};charset=utf8mb4",
    $dbConfig['app']['user'], $dbConfig['app']['pass'], $dbConfig['app']['options']
);
$pdoAuth = new PDO(
    "mysql:host={$dbConfig['auth']['host']};dbname=ownuh_credentials;charset=utf8mb4",
    $dbConfig['auth']['user'], $dbConfig['auth']['pass'], $dbConfig['auth']['options']
);
$redis = new Redis();
$redis->connect($dbConfig['redis']['host'], (int)$dbConfig['redis']['port']);
if ($dbConfig['redis']['pass']) {
    $redis->auth($dbConfig['redis']['pass']);
}

AuditMiddleware::init($pdo);
$rateLimit = new RateLimitMiddleware($redis, $secConfig);

// ── Rate limit by IP (prevent brute-forcing tokens) ───────────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
try {
    $rateLimit->check('/auth/password/confirm', $ip, 'per_ip');
} catch (Throwable $e) {
    exit;
}

// ── Parse input ────────────────────────────────────────────────────────────────
$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$rawToken   = (string)($body['token']               ?? '');
$newPass    = (string)($body['new_password']         ?? '');
$confirmPass = (string)($body['new_password_confirm'] ?? '');

if (!$rawToken || !$newPass || !$confirmPass) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR',
        'message' => 'token, new_password, and new_password_confirm are required.']);
    exit;
}

if ($newPass !== $confirmPass) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR',
        'message' => 'Passwords do not match.']);
    exit;
}

// ── Look up token in Redis ─────────────────────────────────────────────────────
// password-reset.php stores:  saips:pwreset:{sha256(rawToken)}  => userId
$tokenHash = hash('sha256', $rawToken);
$redisKey  = "saips:pwreset:{$tokenHash}";
$userId    = $redis->get($redisKey);

if (!$userId) {
    // Generic message — prevents oracle attacks on token validity
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'INVALID_OR_EXPIRED_TOKEN',
        'message' => 'Reset token is invalid or has expired. Please request a new reset link.']);
    exit;
}

// ── Fetch user ─────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $redis->del($redisKey);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'INVALID_OR_EXPIRED_TOKEN',
        'message' => 'Reset token is invalid or has expired.']);
    exit;
}

// Admin accounts must never use self-service reset (SRS §6.1)
if (in_array($user['role'], ['admin', 'superadmin'])) {
    AuditMiddleware::log('AUTH-005', 'Password Confirm Attempted (Admin — Blocked)', $userId,
        null, null, null, null, null, ['reason' => 'admin_self_service_not_permitted']);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN',
        'message' => 'Admin accounts cannot use self-service password reset.']);
    exit;
}

// ── Enforce password policy (SRS §2.2) ────────────────────────────────────────
$policy = $secConfig['password'];
$errors = _validatePasswordPolicy($newPass, $userId, $pdo, $policy);

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'code' => 'POLICY_VIOLATION', 'errors' => $errors]);
    exit;
}

// ── Check password history (last N) ───────────────────────────────────────────
$stmtHist = $pdo->prepare(
    'SELECT password_hash FROM password_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ?'
);
$stmtHist->execute([$userId, $policy['history_count']]);
foreach ($stmtHist->fetchAll() as $h) {
    if (password_verify($newPass, $h['password_hash'])) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'code' => 'POLICY_VIOLATION',
            'message' => "Password was used recently. You cannot reuse your last {$policy['history_count']} passwords."]);
        exit;
    }
}

// ── Hash and persist new password ─────────────────────────────────────────────
$newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => $policy['bcrypt_cost']]);

$pdoAuth->prepare('UPDATE credentials SET password_hash = ?, bcrypt_cost = ? WHERE user_id = ?')
    ->execute([$newHash, $policy['bcrypt_cost'], $userId]);

// Add to history
$pdo->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)')->execute([$userId, $newHash]);

// Trim history to last N entries
$pdo->prepare(
    'DELETE FROM password_history WHERE user_id = ? AND id NOT IN (
        SELECT id FROM (SELECT id FROM password_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ?) t
     )'
)->execute([$userId, $userId, $policy['history_count']]);

// Update timestamp
$pdo->prepare('UPDATE users SET password_changed_at = NOW() WHERE id = ?')->execute([$userId]);

// ── Consume the token so it cannot be replayed ────────────────────────────────
$redis->del($redisKey);

// ── Invalidate all existing sessions (force fresh login) ─────────────────────
$pdo->prepare(
    'UPDATE sessions SET invalidated_at = NOW(), invalidation_reason = ? WHERE user_id = ? AND invalidated_at IS NULL'
)->execute(['password_reset', $userId]);

$sessionKeys = $redis->keys('saips:session:*');
foreach ($sessionKeys as $sk) {
    $val = json_decode($redis->get($sk) ?? '', true);
    if (($val['user_id'] ?? null) === $userId) {
        $redis->del($sk);
    }
}

AuditMiddleware::passwordChanged($userId, 'self_service_reset');

echo json_encode([
    'status'  => 'success',
    'message' => 'Password reset successfully. Please log in with your new password.',
]);

// ── Policy validation (mirrors password-change.php) ──────────────────────────
function _validatePasswordPolicy(string $password, string $userId, PDO $pdo, array $policy): array
{
    $errors = [];

    if (strlen($password) < $policy['min_length']) {
        $errors[] = "Password must be at least {$policy['min_length']} characters.";
    }
    if (strlen($password) > $policy['max_length']) {
        $errors[] = "Password must not exceed {$policy['max_length']} characters.";
    }

    $classes = 0;
    if (preg_match('/[A-Z]/', $password)) $classes++;
    if (preg_match('/[a-z]/', $password)) $classes++;
    if (preg_match('/[0-9]/', $password)) $classes++;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $classes++;

    if ($classes < $policy['require_classes']) {
        $errors[] = "Password must contain at least {$policy['require_classes']} of: uppercase, lowercase, digits, special characters.";
    }

    return $errors;
}
