<?php
/**
 * Ownuh SAIPS — POST /users/{id}/mfa-reset
 * Admin-only endpoint to reset/clear a user's MFA configuration.
 * SRS §2.4 — MFA Management
 * 
 * Clears all MFA secrets, backup codes, and FIDO2 credentials.
 * User will need to re-enroll MFA on next login.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\AuthMiddleware;
use SAIPS\Middleware\RateLimitMiddleware;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'code' => 'METHOD_NOT_ALLOWED']);
    exit;
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$dbConfig  = require __DIR__ . '/../../config/database.php';
$secConfig = require __DIR__ . '/../../config/security.php';

$pdo       = new PDO(
    "mysql:host={$dbConfig['app']['host']};dbname={$dbConfig['app']['name']};charset=utf8mb4",
    $dbConfig['app']['user'], $dbConfig['app']['pass'], $dbConfig['app']['options']
);

$redis = new Redis();
$redis->connect($dbConfig['redis']['host'], (int)$dbConfig['redis']['port']);
if ($dbConfig['redis']['pass']) {
    $redis->auth($dbConfig['redis']['pass']);
}

AuditMiddleware::init($pdo);
$rateLimit = new RateLimitMiddleware($redis, $secConfig);

// ── Authenticate admin via JWT ───────────────────────────────────────────────
$auth = new AuthMiddleware($secConfig);
$admin = $auth->validate();

if (!$admin) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'UNAUTHORIZED', 'message' => 'Authentication required.']);
    exit;
}

// Only admins can reset MFA
$hierarchy = ['user' => 1, 'manager' => 2, 'admin' => 3, 'superadmin' => 4];
if (($hierarchy[$admin['role']] ?? 0) < 3) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Admin access required.']);
    exit;
}

$adminId = $admin['sub'];

// ── Parse input ───────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Get user ID from path or body
$pathParts = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$targetUserId = $body['user_id'] ?? ($pathParts[0] ?? null);

if (!$targetUserId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'User ID is required.']);
    exit;
}

$reason = trim((string)($body['reason'] ?? ''));
$invalidateSessions = (bool)($body['invalidate_sessions'] ?? true);

if (strlen($reason) < 10) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'A detailed reason (min 10 chars) is required for audit purposes.']);
    exit;
}

// ── Fetch target user ────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, email, display_name, role, mfa_enrolled, mfa_factor FROM users WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$targetUserId]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND', 'message' => 'User not found.']);
    exit;
}

// ── Check privilege constraints ──────────────────────────────────────────────
if (($hierarchy[$targetUser['role']] ?? 0) >= ($hierarchy[$admin['role']] ?? 0) && $admin['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Cannot reset MFA for users with equal or higher privileges.']);
    exit;
}

// ── Clear all MFA data ───────────────────────────────────────────────────────

// Clear TOTP secrets
$pdo->prepare('DELETE FROM mfa_totp_secrets WHERE user_id = ?')->execute([$targetUserId]);

// Clear backup codes
$pdo->prepare('DELETE FROM mfa_backup_codes WHERE user_id = ?')->execute([$targetUserId]);

// Clear FIDO2 credentials
$pdo->prepare('DELETE FROM mfa_fido2_credentials WHERE user_id = ?')->execute([$targetUserId]);

// Clear MFA bypass token
$pdo->prepare('UPDATE users SET mfa_bypass_token = NULL, mfa_bypass_expiry = NULL WHERE id = ?')->execute([$targetUserId]);

// Update user MFA status
$pdo->prepare('UPDATE users SET mfa_enrolled = 0, mfa_factor = ? WHERE id = ?')
    ->execute(['none', $targetUserId]);

// Clear any pending MFA sessions from Redis (KEYS/SCAN — DEL doesn't support wildcards)
$pendingKeys = $redis->keys('saips:mfa_pending:*');
foreach ($pendingKeys as $pk) {
    $val = $redis->get($pk);
    if ($val) {
        $pd = json_decode($val, true);
        if (($pd['user_id'] ?? null) === $targetUserId) {
            $redis->del($pk);
        }
    }
}

// ── Invalidate sessions if requested ─────────────────────────────────────────
$invalidatedCount = 0;
if ($invalidateSessions) {
    // Get all active sessions
    $stmt = $pdo->prepare('SELECT id, refresh_token_hash FROM sessions WHERE user_id = ? AND invalidated_at IS NULL');
    $stmt->execute([$targetUserId]);
    $sessions = $stmt->fetchAll();
    
    foreach ($sessions as $session) {
        $redis->del("saips:session:{$session['refresh_token_hash']}");
        $invalidatedCount++;
    }
    
    // Invalidate in database
    $pdo->prepare('UPDATE sessions SET invalidated_at = NOW(), invalidated_by = ?, invalidation_reason = ? WHERE user_id = ? AND invalidated_at IS NULL')
        ->execute([$adminId, 'mfa_reset', $targetUserId]);
}

// ── Audit log ────────────────────────────────────────────────────────────────
AuditMiddleware::mfaReset($targetUserId, $adminId, $reason, $invalidateSessions);

// ── Return response ──────────────────────────────────────────────────────────
echo json_encode([
    'status'        => 'success',
    'message'       => 'MFA configuration reset successfully.',
    'user'          => [
        'id'           => $targetUser['id'],
        'email'        => $targetUser['email'],
        'display_name' => $targetUser['display_name'],
        'mfa_enrolled' => false,
        'mfa_factor'   => 'none',
    ],
    'cleared'       => [
        'totp_secrets'   => true,
        'backup_codes'   => true,
        'fido2_credentials' => true,
        'bypass_token'   => true,
    ],
    'sessions_invalidated' => $invalidatedCount,
    'next_steps'    => 'User must re-enroll MFA on next login.',
]);