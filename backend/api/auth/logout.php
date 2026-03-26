<?php
/**
 * Ownuh SAIPS — POST /auth/logout
 * Invalidates the current session's refresh token.
 * SRS §3.4 — Session Management
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\AuthMiddleware;

header('Content-Type: application/json');

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
$userId  = $payload['sub'];

$body         = json_decode(file_get_contents('php://input'), true) ?? [];
$refreshToken = (string)($body['refresh_token'] ?? '');

if ($refreshToken) {
    $hash = hash('sha256', $refreshToken);

    // Invalidate in DB
    $stmt = $pdo->prepare(
        'UPDATE sessions
         SET invalidated_at = NOW(), invalidation_reason = "user_logout"
         WHERE refresh_token_hash = ? AND user_id = ?'
    );
    $stmt->execute([$hash, $userId]);

    // Remove from Redis
    $redis->del("saips:session:{$hash}");
}

AuditMiddleware::log('SES-002', 'Session Expired', $userId, null, null, null, null, null, [
    'reason' => 'user_logout',
]);

http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Logged out successfully.']);