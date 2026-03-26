<?php
/**
 * Ownuh SAIPS — DELETE /sessions/{id}  |  DELETE /sessions/user/{userId}
 * Revokes a specific session or all sessions for a user.
 * Superadmin can revoke all system sessions (emergency).
 * SRS §3.4 + §6.2
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json');

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\AuthMiddleware;

$dbConfig  = require __DIR__ . '/../../config/database.php';
$secConfig = require __DIR__ . '/../../config/security.php';

$pdo = new PDO(
    "mysql:host={$dbConfig['app']['host']};dbname={$dbConfig['app']['name']};charset=utf8mb4",
    $dbConfig['app']['user'], $dbConfig['app']['pass'], $dbConfig['app']['options']
);
$redis = new Redis();
$redis->connect($dbConfig['redis']['host'], (int)$dbConfig['redis']['port']);
if ($dbConfig['redis']['pass']) $redis->auth($dbConfig['redis']['pass']);

AuditMiddleware::init($pdo);

$auth    = new AuthMiddleware($secConfig);
$payload = $auth->validate();
$adminId = $payload['sub'];
$role    = $payload['role'];

// Must be admin to revoke others' sessions
if (!in_array($role, ['admin', 'superadmin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$reason = substr((string)($body['reason'] ?? 'Admin revocation'), 0, 255);
$uri    = $_SERVER['REQUEST_URI'] ?? '';

// ── Route: DELETE /sessions/all  (Superadmin emergency) ──────────────────────
if (str_contains($uri, '/sessions/all')) {
    if ($role !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Superadmin only.']);
        exit;
    }

    $confirm = (string)($body['confirmation'] ?? '');
    if ($confirm !== 'CONFIRM REVOKE ALL') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'code' => 'CONFIRMATION_REQUIRED', 'message' => 'Type CONFIRM REVOKE ALL to proceed.']);
        exit;
    }

    $count = $pdo->prepare(
        'UPDATE sessions SET invalidated_at = NOW(), invalidated_by = ?, invalidation_reason = ?
         WHERE invalidated_at IS NULL'
    );
    $count->execute([$adminId, "emergency_all: {$reason}"]);
    $affected = $count->rowCount();

    // Flush all session keys from Redis
    $keys = $redis->keys('saips:session:*');
    if ($keys) $redis->del(...$keys);

    AuditMiddleware::log('SES-003', 'Session Invalidated', null, null, null, null, null, null, [
        'scope'    => 'SYSTEM_WIDE',
        'count'    => $affected,
        'reason'   => $reason,
    ], $adminId, null);

    echo json_encode(['status' => 'success', 'message' => "{$affected} sessions revoked system-wide."]);
    exit;
}

// ── Route: DELETE /sessions/user/{userId} ────────────────────────────────────
preg_match('#/sessions/user/([a-f0-9-]{36})#', $uri, $matchUser);
if ($matchUser) {
    $targetUserId = $matchUser[1];

    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$targetUserId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND']);
        exit;
    }

    $pdo->prepare(
        'UPDATE sessions SET invalidated_at = NOW(), invalidated_by = ?, invalidation_reason = ?
         WHERE user_id = ? AND invalidated_at IS NULL'
    )->execute([$adminId, $reason, $targetUserId]);

    // Clear from Redis
    $keys = $redis->keys('saips:session:*');
    foreach ($keys as $key) {
        $val = json_decode($redis->get($key) ?? '', true);
        if (($val['user_id'] ?? null) === $targetUserId) {
            $redis->del($key);
        }
    }

    AuditMiddleware::sessionInvalidated('ALL', $targetUserId, $adminId, $reason);

    echo json_encode(['status' => 'success', 'message' => "All sessions for user revoked."]);
    exit;
}

// ── Route: DELETE /sessions/{sessionId} ──────────────────────────────────────
preg_match('#/sessions/([a-f0-9]{32})#', $uri, $matchSess);
$sessionId = $matchSess[1] ?? ($body['session_id'] ?? null);

if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'session_id is required.']);
    exit;
}

$stmt = $pdo->prepare('SELECT user_id, refresh_token_hash FROM sessions WHERE id = ? AND invalidated_at IS NULL');
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND', 'message' => 'Session not found or already invalidated.']);
    exit;
}

$pdo->prepare(
    'UPDATE sessions SET invalidated_at = NOW(), invalidated_by = ?, invalidation_reason = ? WHERE id = ?'
)->execute([$adminId, $reason, $sessionId]);

$redis->del("saips:session:{$session['refresh_token_hash']}");

AuditMiddleware::sessionInvalidated($sessionId, $session['user_id'], $adminId, $reason);

echo json_encode(['status' => 'success', 'message' => 'Session revoked successfully.']);
