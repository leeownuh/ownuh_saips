<?php
/**
 * Ownuh SAIPS — /ips/rate-limits endpoints
 * GET  /ips/rate-limits      — list all rate limit configs (Admin+)
 * PUT  /ips/rate-limits/{id} — update a rate limit rule (Admin+)
 * SRS §3.4 — Rate Limiting Policy
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

// ── GET /ips/rate-limits ─────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $pdo->query(
        'SELECT id, endpoint, requests_limit, window_seconds, scope,
                action_on_breach, is_active, updated_at
         FROM rate_limit_config
         ORDER BY endpoint ASC'
    );
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    exit;
}

// ── PUT /ips/rate-limits/{id} ────────────────────────────────────────────────
if ($method === 'PUT' && preg_match('#/rate-(?:limits?|config)/([a-f0-9-]{36})#', $uri, $m)) {
    $id = $m[1];

    $allowed = ['requests_limit', 'window_seconds', 'scope', 'action_on_breach', 'is_active'];
    $updates = [];
    $params  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $updates[] = "{$field} = ?";
            $params[]  = $body[$field];
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'No updatable fields provided.']);
        exit;
    }

    // Validate ranges
    if (isset($body['requests_limit']) && ($body['requests_limit'] < 1 || $body['requests_limit'] > 10000)) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'requests_limit must be 1–10000.']);
        exit;
    }

    $updates[] = 'updated_by = ?';
    $params[]  = $adminId;
    $params[]  = $id;

    $pdo->prepare('UPDATE rate_limit_config SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);

    // Flush Redis rate limit counters for this endpoint
    $stmtRow = $pdo->prepare('SELECT endpoint FROM rate_limit_config WHERE id = ?');
    $stmtRow->execute([$id]);
    if ($ep = $stmtRow->fetchColumn()) {
        foreach ($redis->keys("saips:rl:{$ep}:*") as $k) {
            $redis->del($k);
        }
    }

    AuditMiddleware::log('CFG-002', 'Rate Limit Updated', null, null, null, null, null, null, [
        'rule_id' => $id, 'changes' => array_keys(array_flip($allowed)), 'admin_id' => $adminId,
    ], $adminId);

    echo json_encode(['status' => 'success', 'message' => 'Rate limit rule updated.']);
    exit;
}

http_response_code(404);
echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND']);
