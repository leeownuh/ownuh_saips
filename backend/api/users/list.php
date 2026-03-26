<?php
/**
 * Ownuh SAIPS — /users/* endpoints
 * GET    /users          — list users (Admin+)
 * POST   /users          — create user (Admin+)
 * PUT    /users/{id}     — update user (Admin+)
 * POST   /users/{id}/lock   — lock account (Admin+)
 * POST   /users/{id}/unlock — unlock account (Admin+)
 * PUT    /users/{id}/role   — change role (Superadmin)
 * DELETE /users/{id}/sessions — revoke all sessions (Admin+)
 * SRS §6.2 — Manage Users Panel
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

if (!in_array($role, ['admin', 'superadmin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET /users ────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, (int)($_GET['per_page'] ?? 50));
    $offset  = ($page - 1) * $perPage;
    $search  = $_GET['search'] ?? null;
    $status  = $_GET['status'] ?? null;

    $where  = ['deleted_at IS NULL'];
    $params = [];

    if ($search) {
        $where[]  = '(email LIKE ? OR display_name LIKE ?)';
        $like     = "%{$search}%";
        $params   = array_merge($params, [$like, $like]);
    }
    if ($status) {
        $where[]  = 'status = ?';
        $params[] = $status;
    }

    $whereStr = implode(' AND ', $where);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$whereStr}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT id, display_name, email, role, status, mfa_enrolled, mfa_factor,
                failed_attempts, last_login_at, last_login_ip, created_at
         FROM users WHERE {$whereStr}
         ORDER BY role DESC, display_name ASC LIMIT ? OFFSET ?"
    );
    $stmt->execute([...$params, $perPage, $offset]);

    echo json_encode(['status' => 'success', 'total' => $total, 'data' => $stmt->fetchAll()]);
    exit;
}

// ── POST /users ───────────────────────────────────────────────────────────────
if ($method === 'POST' && !preg_match('#/users/[^/]+#', $uri)) {
    $displayName = trim((string)($body['display_name'] ?? ''));
    $email       = strtolower(trim((string)($body['email'] ?? '')));
    $userRole    = $body['role'] ?? 'user';

    if (!$displayName || !$email) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'display_name and email required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Invalid email format.']);
        exit;
    }

    // Superadmin required to create admin accounts
    if (in_array($userRole, ['admin', 'superadmin']) && $role !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Only Superadmin can create Admin accounts.']);
        exit;
    }

    $userId = bin2hex(random_bytes(16));
    $stmt   = $pdo->prepare(
        'INSERT INTO users (id, display_name, email, role, status)
         VALUES (?, ?, ?, ?, "pending")'
    );
    $stmt->execute([$userId, $displayName, $email, $userRole]);

    AuditMiddleware::userRecordModified($adminId, $userId, ['action' => 'created', 'role' => $userRole]);

    http_response_code(201);
    echo json_encode(['status' => 'success', 'user_id' => $userId]);
    exit;
}

// ── POST /users/{id}/lock ─────────────────────────────────────────────────────
if ($method === 'POST' && preg_match('#/users/([^/]+)/lock$#', $uri, $m)) {
    $targetId  = $m[1];
    $lockType  = $body['lock_type'] ?? 'hard';
    $reason    = $body['reason']    ?? 'Admin action';

    $pdo->prepare("UPDATE users SET status = 'locked' WHERE id = ?")->execute([$targetId]);
    AuditMiddleware::accountLocked($targetId, $lockType, $reason, $adminId);

    echo json_encode(['status' => 'success', 'message' => 'Account locked.']);
    exit;
}

// ── POST /users/{id}/unlock ───────────────────────────────────────────────────
if ($method === 'POST' && preg_match('#/users/([^/]+)/unlock$#', $uri, $m)) {
    $targetId     = $m[1];
    $justification = $body['justification'] ?? 'Admin unlock';

    $pdo->prepare(
        "UPDATE users SET status = 'active', failed_attempts = 0, last_failed_at = NULL WHERE id = ?"
    )->execute([$targetId]);

    AuditMiddleware::accountUnlocked($targetId, $adminId, $justification);

    echo json_encode(['status' => 'success', 'message' => 'Account unlocked.']);
    exit;
}

// ── PUT /users/{id}/role ──────────────────────────────────────────────────────
if ($method === 'PUT' && preg_match('#/users/([^/]+)/role$#', $uri, $m)) {
    if ($role !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'code' => 'FORBIDDEN', 'message' => 'Superadmin only.']);
        exit;
    }

    $targetId = $m[1];
    $newRole  = $body['role'] ?? null;

    if (!in_array($newRole, ['user', 'manager', 'admin', 'superadmin'])) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Invalid role.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $old = $stmt->fetchColumn();

    $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $targetId]);
    AuditMiddleware::roleChanged($adminId, $targetId, $old, $newRole);

    echo json_encode(['status' => 'success', 'message' => "Role changed from {$old} to {$newRole}."]);
    exit;
}

// ── PUT /users/{id} ───────────────────────────────────────────────────────────
if ($method === 'PUT' && preg_match('#/users/([^/]+)$#', $uri, $m)) {
    $targetId = $m[1];
    $allowed  = ['display_name', 'email', 'status'];
    $updates  = [];
    $params   = [];

    foreach ($allowed as $field) {
        if (isset($body[$field])) {
            $updates[] = "{$field} = ?";
            $params[]  = $body[$field];
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'No updatable fields provided.']);
        exit;
    }

    $params[] = $targetId;
    $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
    AuditMiddleware::userRecordModified($adminId, $targetId, array_keys(array_intersect_key($body, array_flip($allowed))));

    echo json_encode(['status' => 'success', 'message' => 'User updated.']);
    exit;
}

http_response_code(404);
echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND']);
