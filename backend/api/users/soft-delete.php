<?php
/**
 * Ownuh SAIPS — DELETE /users/{id}
 * Soft-delete a user account with 30-day recovery period.
 * SRS §2.1 — User Management
 * 
 * After 30 days, a cleanup job will permanently delete the user.
 * During recovery period, user can be restored.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\AuthMiddleware;
use SAIPS\Middleware\RateLimitMiddleware;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Only admins can delete users
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
$revokeSessions = (bool)($body['revoke_sessions'] ?? true);
$permanent = (bool)($body['permanent'] ?? false); // Super admin only

if (strlen($reason) < 10) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'A detailed reason (min 10 chars) is required for audit purposes.']);
    exit;
}

// ── Fetch target user ────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, email, display_name, role, status, deleted_at FROM users WHERE id = ?');
$stmt->execute([$targetUserId]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND', 'message' => 'User not found.']);
    exit;
}

// Check if already soft-deleted
if ($targetUser['deleted_at'] !== null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'ALREADY_DELETED', 'message' => 'User is already soft-deleted. Use permanent delete or restore.']);
    exit;
}

// ── Check privilege constraints ──────────────────────────────────────────────
if (($hierarchy[$targetUser['role']] ?? 0) >= ($hierarchy[$admin['role']] ?? 0) && $admin['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Cannot delete users with equal or higher privileges.']);
    exit;
}

// Permanent delete requires superadmin
if ($permanent && $admin['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Only superadmins can permanently delete users.']);
    exit;
}

// Cannot delete yourself
if ($targetUserId === $adminId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'SELF_DELETE', 'message' => 'You cannot delete your own account.']);
    exit;
}

$recoveryDeadline = date('Y-m-d H:i:s', strtotime('+30 days'));

// ── Perform deletion ─────────────────────────────────────────────────────────
if ($permanent) {
    // Permanent deletion - cascade will handle related tables
    // First, backup data to audit log
    
    // Revoke all sessions
    $stmt = $pdo->prepare('SELECT refresh_token_hash FROM sessions WHERE user_id = ?');
    $stmt->execute([$targetUserId]);
    foreach ($stmt->fetchAll() as $session) {
        $redis->del("saips:session:{$session['refresh_token_hash']}");
    }
    
    // Delete from credentials DB
    $pdoAuth = new PDO(
        "mysql:host={$dbConfig['auth']['host']};dbname=ownuh_credentials;charset=utf8mb4",
        $dbConfig['auth']['user'], $dbConfig['auth']['pass'], $dbConfig['auth']['options']
    );
    $pdoAuth->prepare('DELETE FROM credentials WHERE user_id = ?')->execute([$targetUserId]);
    
    // Delete from main DB (cascades to related tables)
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetUserId]);
    
    AuditMiddleware::userDeleted($targetUserId, $adminId, $reason);
    
    echo json_encode([
        'status'        => 'success',
        'message'       => 'User permanently deleted.',
        'user'          => [
            'id'           => $targetUserId,
            'email'        => $targetUser['email'],
            'display_name' => $targetUser['display_name'],
        ],
        'permanent'     => true,
    ]);
    
} else {
    // Soft delete - set deleted_at timestamp
    
    // Revoke all sessions if requested
    $sessionCount = 0;
    if ($revokeSessions) {
        $stmt = $pdo->prepare('SELECT refresh_token_hash FROM sessions WHERE user_id = ?');
        $stmt->execute([$targetUserId]);
        foreach ($stmt->fetchAll() as $session) {
            $redis->del("saips:session:{$session['refresh_token_hash']}");
            $sessionCount++;
        }
        
        $pdo->prepare('UPDATE sessions SET invalidated_at = NOW(), invalidated_by = ?, invalidation_reason = ? WHERE user_id = ?')
            ->execute([$adminId, 'user_deleted', $targetUserId]);
    }
    
    // Set deleted_at
    $pdo->prepare('UPDATE users SET deleted_at = NOW(), status = ? WHERE id = ?')
        ->execute(['suspended', $targetUserId]);
    
    // Store recovery info in Redis
    $redis->setex("saips:deleted_user:{$targetUserId}", 86400 * 30, json_encode([
        'user_id'        => $targetUserId,
        'email'          => $targetUser['email'],
        'display_name'   => $targetUser['display_name'],
        'deleted_by'     => $adminId,
        'deleted_at'     => time(),
        'reason'         => $reason,
        'recovery_deadline' => $recoveryDeadline,
    ]));
    
    AuditMiddleware::userSoftDeleted($targetUserId, $adminId, $reason, 30);
    
    echo json_encode([
        'status'            => 'success',
        'message'           => 'User soft-deleted successfully. They can be restored within 30 days.',
        'user'              => [
            'id'           => $targetUserId,
            'email'        => $targetUser['email'],
            'display_name' => $targetUser['display_name'],
        ],
        'permanent'         => false,
        'recovery_deadline' => $recoveryDeadline,
        'sessions_revoked'  => $sessionCount,
        'restore_endpoint'  => "/users/{$targetUserId}/restore",
    ]);
}