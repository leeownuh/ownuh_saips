<?php
/**
 * Ownuh SAIPS — POST /auth/password/change
 * Changes authenticated user password with full SRS §2.2 policy enforcement.
 * Checks: min/max length, char classes, HIBP, history, similarity.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\AuthMiddleware;

header('Content-Type: application/json');

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
if ($dbConfig['redis']['pass']) $redis->auth($dbConfig['redis']['pass']);

AuditMiddleware::init($pdo);

$auth    = new AuthMiddleware($secConfig);
$payload = $auth->validate();
$userId  = $payload['sub'];

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$currentPass = (string)($body['current_password']       ?? '');
$newPass     = (string)($body['new_password']            ?? '');
$confirmPass = (string)($body['new_password_confirm']    ?? '');

// Validation
if (!$currentPass || !$newPass || !$confirmPass) {
    _fail(400, 'VALIDATION_ERROR', 'All password fields are required.');
}

if ($newPass !== $confirmPass) {
    _fail(400, 'VALIDATION_ERROR', 'New passwords do not match.');
}

// Policy enforcement (SRS §2.2)
$policy  = $secConfig['password'];
$errors  = _validatePasswordPolicy($newPass, $userId, $pdo, $policy);

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'code' => 'POLICY_VIOLATION', 'errors' => $errors]);
    exit;
}

// Verify current password
$stmtCred = $pdoAuth->prepare('SELECT password_hash FROM credentials WHERE user_id = ?');
$stmtCred->execute([$userId]);
$cred = $stmtCred->fetch();

if (!$cred || !password_verify($currentPass, $cred['password_hash'])) {
    _fail(401, 'UNAUTHORIZED', 'Current password is incorrect.');
}

// Check password history (last 12)
$stmtHist = $pdo->prepare(
    'SELECT password_hash FROM password_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ?'
);
$stmtHist->execute([$userId, $policy['history_count']]);
$history = $stmtHist->fetchAll();

foreach ($history as $h) {
    if (password_verify($newPass, $h['password_hash'])) {
        _fail(422, 'POLICY_VIOLATION', "Password was used recently. You cannot reuse your last {$policy['history_count']} passwords.");
    }
}

// Hash new password (bcrypt cost from config)
$newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => $policy['bcrypt_cost']]);

// Update credentials DB
$pdoAuth->prepare('UPDATE credentials SET password_hash = ?, bcrypt_cost = ? WHERE user_id = ?')
    ->execute([$newHash, $policy['bcrypt_cost'], $userId]);

// Add to history
$pdo->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)')->execute([$userId, $newHash]);

// Trim history to last N
$pdo->prepare(
    'DELETE FROM password_history WHERE user_id = ? AND id NOT IN (
        SELECT id FROM (SELECT id FROM password_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ?) t
     )'
)->execute([$userId, $userId, $policy['history_count']]);

// Update password_changed_at
$pdo->prepare('UPDATE users SET password_changed_at = NOW() WHERE id = ?')->execute([$userId]);

AuditMiddleware::passwordChanged($userId, 'self_service');

echo json_encode(['status' => 'success', 'message' => 'Password changed successfully.']);

// ── Policy Validation ────────────────────────────────────────────────────────
function _validatePasswordPolicy(string $password, string $userId, \PDO $pdo, array $policy): array
{
    $errors = [];

    if (strlen($password) < $policy['min_length']) {
        $errors[] = "Password must be at least {$policy['min_length']} characters.";
    }
    if (strlen($password) > $policy['max_length']) {
        $errors[] = "Password must not exceed {$policy['max_length']} characters.";
    }

    // Character class check (3-of-4)
    $classes = 0;
    if (preg_match('/[A-Z]/', $password)) $classes++;
    if (preg_match('/[a-z]/', $password)) $classes++;
    if (preg_match('/[0-9]/', $password)) $classes++;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $classes++;

    if ($classes < $policy['require_classes']) {
        $errors[] = "Password must contain at least {$policy['require_classes']} of: uppercase, lowercase, digits, special characters.";
    }

    // Similarity check vs email and username
    if ($policy['similarity_check']) {
        $stmt = $pdo->prepare('SELECT email, display_name FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $emailLocal = explode('@', $user['email'])[0];
            if (levenshtein(strtolower($password), strtolower($emailLocal)) < $policy['similarity_min']) {
                $errors[] = 'Password is too similar to your email address.';
            }
            if (levenshtein(strtolower($password), strtolower($user['display_name'])) < $policy['similarity_min']) {
                $errors[] = 'Password is too similar to your display name.';
            }
        }
    }

    // HIBP check
    if ($policy['hibp_check'] && empty($errors)) {
        if (_isPasswordPwned($password)) {
            $errors[] = 'This password has appeared in a known data breach. Please choose a different password.';
        }
    }

    return $errors;
}

function _isPasswordPwned(string $password): bool
{
    $sha1    = strtoupper(sha1($password));
    $prefix  = substr($sha1, 0, 5);
    $suffix  = substr($sha1, 5);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://api.pwnedpasswords.com/range/{$prefix}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'OwnuhSAIPS/1.0',
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_HTTPHEADER     => ['Add-Padding: true'],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return false; // Fail open if HIBP unreachable

    foreach (explode("\n", $response) as $line) {
        [$hash, $count] = explode(':', trim($line)) + [null, '0'];
        if ($hash === $suffix && (int)$count > 0) {
            return true;
        }
    }
    return false;
}

function _fail(int $status, string $code, string $message): never
{
    http_response_code($status);
    echo json_encode(['status' => 'error', 'code' => $code, 'message' => $message]);
    exit;
}
