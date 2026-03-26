<?php
/**
 * Ownuh SAIPS — POST /users/{id}/mfa-bypass
 * Admin-only endpoint to issue a 4-hour MFA bypass token for a user.
 * SRS §2.4 — MFA Bypass for account recovery scenarios.
 * 
 * POST /users/{id}/mfa-bypass
 * Headers: Authorization: Bearer <admin_jwt>
 * Request body: { "reason": "Account recovery - user lost device", "duration_hours": 4 }
 * Response: { "status": "success", "bypass_token": "abc123...", "expires_at": "2024-..." }
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

// Check admin role
$hierarchy = ['user' => 1, 'manager' => 2, 'admin' => 3, 'superadmin' => 4];
if (($hierarchy[$admin['role']] ?? 0) < 3) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Admin access required.']);
    exit;
}

$adminId = $admin['sub'];

// ── Rate limit ────────────────────────────────────────────────────────────────
try {
    $rateLimit->check('/users/mfa-bypass', $adminId, 'per_user');
} catch (Throwable $e) {
    exit;
}

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
$durationHours = (int)($body['duration_hours'] ?? 4);

if (strlen($reason) < 10) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'A detailed reason (min 10 chars) is required for audit purposes.']);
    exit;
}

// Cap duration at 4 hours per SRS
$durationHours = min($durationHours, $secConfig['mfa']['bypass_ttl'] ?? 4);

// ── Fetch target user ────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, email, display_name, role, mfa_enrolled, mfa_factor, status FROM users WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$targetUserId]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND', 'message' => 'User not found.']);
    exit;
}

// ── Check if target is higher privilege ──────────────────────────────────────
if (($hierarchy[$targetUser['role']] ?? 0) >= ($hierarchy[$admin['role']] ?? 0) && $admin['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Cannot issue bypass token for users with equal or higher privileges.']);
    exit;
}

// ── Generate bypass token ────────────────────────────────────────────────────
$bypassToken = bin2hex(random_bytes(32));
$bypassTokenHash = hash('sha256', $bypassToken);
$expiresAt = date('Y-m-d H:i:s', time() + ($durationHours * 3600));

// ── Store bypass token in database ───────────────────────────────────────────
$pdo->prepare('UPDATE users SET mfa_bypass_token = ?, mfa_bypass_expiry = ? WHERE id = ?')
    ->execute([$bypassTokenHash, $expiresAt, $targetUserId]);

// Also store in Redis for fast lookup
$redis->setex("saips:mfa_bypass:{$bypassTokenHash}", $durationHours * 3600, json_encode([
    'user_id' => $targetUserId,
    'admin_id' => $adminId,
    'reason' => $reason,
    'expires_at' => $expiresAt,
]));

// ── Audit log ────────────────────────────────────────────────────────────────
AuditMiddleware::mfaBypassIssued($targetUserId, $adminId, $reason);

// ── Return response ──────────────────────────────────────────────────────────
echo json_encode([
    'status'       => 'success',
    'message'      => 'MFA bypass token issued successfully.',
    'bypass_token' => $bypassToken,
    'expires_at'   => $expiresAt,
    'duration_hours' => $durationHours,
    'user'         => [
        'id'           => $targetUser['id'],
        'email'        => $targetUser['email'],
        'display_name' => $targetUser['display_name'],
    ],
    'usage_instructions' => 'Provide this token to the user. They can use it during MFA verification by passing it as "bypass_token" in the request body.',
]);