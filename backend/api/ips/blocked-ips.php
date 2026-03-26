<?php
/**
 * Ownuh SAIPS — /ips/blocked endpoints
 * GET    /ips/blocked        — list active blocks (Admin+)
 * POST   /ips/blocked        — manually block an IP (Admin+)
 * DELETE /ips/blocked/{id}   — unblock IP (Admin+)
 * SRS §3.1 + §3.3 — IPS
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

// ── GET /ips/blocked ─────────────────────────────────────────────────────────
if ($method === 'GET') {
    $where  = ['unblocked_at IS NULL', '(expires_at IS NULL OR expires_at > NOW())'];
    $params = [];

    if ($type = $_GET['type'] ?? null) {
        $where[]  = 'block_type = ?';
        $params[] = $type;
    }

    $stmt = $pdo->prepare(
        'SELECT id, ip_address, block_type, trigger_rule, country_code,
                threat_feed, blocked_at, expires_at
         FROM blocked_ips WHERE ' . implode(' AND ', $where) . '
         ORDER BY blocked_at DESC LIMIT 200'
    );
    $stmt->execute($params);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    exit;
}

// ── POST /ips/blocked ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $ip       = trim((string)($body['ip_address']      ?? ''));
    $type     = $body['block_type']                    ?? 'manual';
    $duration = (int)($body['duration_minutes']        ?? 60);
    $reason   = $body['reason']                        ?? 'Manual admin block';

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Invalid IP address.']);
        exit;
    }

    $expires = $duration > 0 ? date('Y-m-d H:i:s', time() + $duration * 60) : null;

    $pdo->prepare(
        'INSERT INTO blocked_ips (ip_address, block_type, trigger_rule, expires_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
         blocked_at = NOW(), expires_at = VALUES(expires_at), unblocked_at = NULL'
    )->execute([$ip, $type, $reason, $expires]);

    // Invalidate Redis cache for this IP
    $redis->del("saips:iprep:{$ip}");

    AuditMiddleware::ipBlocked($ip, $reason, $duration);

    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => "IP {$ip} blocked."]);
    exit;
}

// ── DELETE /ips/blocked/{id} ──────────────────────────────────────────────────
if ($method === 'DELETE' && preg_match('#/ips/blocked/([a-f0-9-]{36})#', $uri, $m)) {
    $id = $m[1];

    $stmt = $pdo->prepare('SELECT ip_address FROM blocked_ips WHERE id = ? AND unblocked_at IS NULL');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND']);
        exit;
    }

    $pdo->prepare('UPDATE blocked_ips SET unblocked_at = NOW(), unblocked_by = ? WHERE id = ?')
        ->execute([$adminId, $id]);

    $redis->del("saips:iprep:{$row['ip_address']}");

    AuditMiddleware::log('IPS-001', 'IP Unblocked', null, $row['ip_address'], null, null, null, null, [
        'action' => 'manual_unblock', 'admin_id' => $adminId,
    ], $adminId);

    echo json_encode(['status' => 'success', 'message' => "IP {$row['ip_address']} unblocked."]);
    exit;
}

http_response_code(404);
echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND']);
