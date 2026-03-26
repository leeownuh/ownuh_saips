<?php
/**
 * Ownuh SAIPS — POST /users/{id}/sessions/revoke
 * Admin endpoint to revoke all sessions for a user.
 * SRS §3.4 — Session Management
 * 
 * Can revoke all sessions or a specific session.
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

// ── Authenticate user via JWT ─────────────────────────────────────────────────
$auth = new AuthMiddleware($pdo, $redis, $secConfig);
$user = $auth->authenticate();

if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'UNAUTHORIZED', 'message' => 'Authentication required.']);
    exit;
}

$hierarchy = ['user' => 1, 'manager' => 2, 'admin' => 3, 'superadmin' => 4];
$userRole = $user['role'];
$userId = $user['sub'];

// ── Parse input ───────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Get target user ID from path or body
$pathParts = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$targetUserId = $body['user_id'] ?? ($pathParts[0] ?? null);
$sessionId = $body['session_id'] ?? null; // Specific session to revoke
$reason = trim((string)($body['reason'] ?? 'Session revoked by administrator'));

// ── Determine if admin operation or self-operation ────────────────────────────
$isAdmin = ($hierarchy[$userRole] ?? 0) >= 3;
$isSelfOperation = ($targetUserId === $userId);

if (!$targetUserId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'User ID is required.']);
    exit;
}

// Non-admins can only revoke their own sessions
if (!$isSelfOperation && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'You can only revoke your own sessions.']);
    exit;
}

// ── Fetch target user ────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, email, display_name, role FROM users WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$targetUserId]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND', 'message' => 'User not found.']);
    exit;
}

// ── Check privilege constraints for admin operations ──────────────────────────
if (!$isSelfOperation && ($hierarchy[$targetUser['role']] ?? 0) >= ($hierarchy[$userRole] ?? 0) && $userRole !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Cannot revoke sessions for users with equal or higher privileges.']);
    exit;
}

$revokedCount = 0;

// ── Revoke specific session or all sessions ───────────────────────────────────
if ($sessionId) {
    // Revoke specific session
    $stmt = $pdo->prepare('SELECT id, refresh_token_hash FROM sessions WHERE id = ? AND user_id = ? AND invalidated_at IS NULL');
    $stmt->execute([$sessionId, $targetUserId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND', 'message' => 'Session not found or already invalidated.']);
        exit;
    }
    
    // Remove from Redis
    $redis->del("saips:session:{$session['refresh_token_hash']}");
    
    // Update in database
    $pdo->prepare('UPDATE sessions SET invalidated_at = NOW(), invalidated_by = ?, invalidation_reason = ? WHERE id = ?')
        ->execute([$userId, $reason, $sessionId]);
    
    $revokedCount = 1;
    
    AuditMiddleware::sessionRevoked($sessionId, $targetUserId, $userId, $reason);
    
} else {
    // Revoke all sessions
    $stmt = $pdo->prepare('SELECT id, refresh_token_hash FROM sessions WHERE user_id = ? AND invalidated_at IS NULL');
    $stmt->execute([$targetUserId]);
    $sessions = $stmt->fetchAll();
    
    foreach ($sessions as $session) {
        // Remove from Redis
        $redis->del("saips:session:{$session['refresh_token_hash']}");
        $revokedCount++;
    }
    
    // Update in database
    $pdo->prepare('UPDATE sessions SET invalidated_at = NOW(), invalidated_by = ?, invalidation_reason = ? WHERE user_id = ? AND invalidated_at IS NULL')
        ->execute([$userId, $reason, $targetUserId]);
    
    AuditMiddleware::sessionsRevoked($targetUserId, $userId, $revokedCount, $reason);
}

// ── Return response ──────────────────────────────────────────────────────────
echo json_encode([
    'status'          => 'success',
    'message'         => $revokedCount === 1 ? 'Session revoked successfully.' : "{$revokedCount} sessions revoked successfully.",
    'revoked_count'   => $revokedCount,
    'user'            => [
        'id'           => $targetUser['id'],
        'email'        => $targetUser['email'],
        'display_name' => $targetUser['display_name'],
    ],
    'session_id'      => $sessionId,
    'reason'          => $reason,
]);