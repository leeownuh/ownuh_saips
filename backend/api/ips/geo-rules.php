<?php
/**
 * Ownuh SAIPS — /ips/geo-rules endpoints
 * GET    /ips/geo-rules         — list geo block rules (Admin+)
 * POST   /ips/geo-rules         — add country block (Admin+)
 * DELETE /ips/geo-rules/{id}    — remove country block (Admin+)
 * SRS §3.3 — IP Reputation and Geo-Blocking
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

// ── GET /ips/geo-rules ───────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $pdo->query(
        'SELECT id, country_code, country_name, rule_type, created_at, created_by
         FROM geo_rules
         ORDER BY country_code ASC'
    );
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    exit;
}

// ── POST /ips/geo-rules ──────────────────────────────────────────────────────
if ($method === 'POST') {
    $countryCode = strtoupper(trim((string)($body['country_code'] ?? '')));
    $countryName = trim((string)($body['country_name'] ?? $countryCode));
    $ruleType    = in_array($body['rule_type'] ?? '', ['deny','allow']) ? $body['rule_type'] : 'deny';

    if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'country_code must be a 2-letter ISO code.']);
        exit;
    }

    $pdo->prepare(
        'INSERT INTO geo_rules (id, country_code, country_name, rule_type, created_by)
         VALUES (UUID(), ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rule_type = VALUES(rule_type)'
    )->execute([$countryCode, $countryName, $ruleType, $adminId]);

    $redis->del("saips:geo:{$countryCode}");

    AuditMiddleware::log('IPS-003', 'Geo Block Added', null, null, null, null, null, null, [
        'country_code' => $countryCode, 'rule_type' => $ruleType, 'admin_id' => $adminId,
    ], $adminId);

    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => "Geo rule added for {$countryCode}."]);
    exit;
}

// ── DELETE /ips/geo-rules/{code} ─────────────────────────────────────────────
if ($method === 'DELETE' && preg_match('#/geo-rules/([A-Za-z0-9-]{2,36})#', $uri, $m)) {
    $ref = strtoupper($m[1]);

    // Try by UUID first, then by country code
    $stmt = $pdo->prepare('SELECT id, country_code FROM geo_rules WHERE id = ? OR country_code = ?');
    $stmt->execute([$ref, $ref]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND']);
        exit;
    }

    $pdo->prepare('DELETE FROM geo_rules WHERE id = ?')->execute([$row['id']]);
    $redis->del("saips:geo:{$row['country_code']}");

    AuditMiddleware::log('IPS-004', 'Geo Block Removed', null, null, null, null, null, null, [
        'country_code' => $row['country_code'], 'admin_id' => $adminId,
    ], $adminId);

    echo json_encode(['status' => 'success', 'message' => "Geo rule for {$row['country_code']} removed."]);
    exit;
}

http_response_code(404);
echo json_encode(['status' => 'error', 'code' => 'NOT_FOUND']);
