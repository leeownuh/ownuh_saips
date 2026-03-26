<?php
/**
 * Ownuh SAIPS — POST /users/{id}/password-reset
 * Admin-initiated password reset endpoint.
 * SRS §2.2 — Password Management
 * 
 * Generates a one-time reset token and optionally sends email to user.
 * The user must use the token within 1 hour to set a new password.
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

// Only admins can reset passwords
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

$reason = trim((string)($body['reason'] ?? 'Admin-initiated password reset'));
$sendEmail = (bool)($body['send_email'] ?? true);
$resetUrl = $body['reset_url'] ?? null;

// ── Fetch target user ────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, email, display_name, role, status FROM users WHERE id = ? AND deleted_at IS NULL');
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
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Cannot reset password for users with equal or higher privileges.']);
    exit;
}

// ── Generate reset token ─────────────────────────────────────────────────────
$resetToken = bin2hex(random_bytes(32));
$resetTokenHash = hash('sha256', $resetToken);
$expiresAt = time() + 3600; // 1 hour

// Store in Redis
$redis->setex("saips:password_reset:{$resetTokenHash}", 3600, json_encode([
    'user_id'    => $targetUserId,
    'admin_id'   => $adminId,
    'reason'     => $reason,
    'created_at' => time(),
    'expires_at' => $expiresAt,
]));

// Store hash in database for audit trail
$pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, created_by, expires_at, reason) VALUES (?, ?, ?, FROM_UNIXTIME(?), ?)')
    ->execute([$targetUserId, $resetTokenHash, $adminId, $expiresAt, $reason]);

// ── Invalidate all existing sessions for this user ────────────────────────────
$pdo->prepare('UPDATE sessions SET invalidated_at = NOW(), invalidated_by = ?, invalidation_reason = ? WHERE user_id = ? AND invalidated_at IS NULL')
    ->execute([$adminId, 'password_reset', $targetUserId]);

// Clear session from Redis
$stmt = $pdo->prepare('SELECT refresh_token_hash FROM sessions WHERE user_id = ?');
$stmt->execute([$targetUserId]);
foreach ($stmt->fetchAll() as $session) {
    $redis->del("saips:session:{$session['refresh_token_hash']}");
}

// ── Send email if requested ──────────────────────────────────────────────────
if ($sendEmail && $targetUser['email']) {
    // Email would be sent via EmailService
    // For now, log the reset link
    $resetLink = $resetUrl ? "{$resetUrl}?token={$resetToken}" : "https://app.example.com/auth/reset-password?token={$resetToken}";
    error_log("[SAIPS] Password reset for {$targetUser['email']}: {$resetLink}");
    
    // Queue email notification
    $redis->lpush('saips:email:queue', json_encode([
        'to'       => $targetUser['email'],
        'template' => 'password_reset',
        'data'     => [
            'display_name' => $targetUser['display_name'],
            'reset_link'   => $resetLink,
            'expires_in'   => '1 hour',
            'admin_name'   => $admin['email'] ?? 'Administrator',
        ],
    ]));
}

// ── Audit log ────────────────────────────────────────────────────────────────
AuditMiddleware::passwordResetInitiated($targetUserId, $adminId, $reason);

// ── Return response ──────────────────────────────────────────────────────────
echo json_encode([
    'status'        => 'success',
    'message'       => 'Password reset initiated successfully.',
    'reset_token'   => $resetToken,
    'expires_at'    => date('c', $expiresAt),
    'reset_url'     => $resetUrl ? "{$resetUrl}?token={$resetToken}" : null,
    'email_sent'    => $sendEmail && $targetUser['email'],
    'user'          => [
        'id'           => $targetUser['id'],
        'email'        => $targetUser['email'],
        'display_name' => $targetUser['display_name'],
    ],
    'sessions_invalidated' => true,
]);