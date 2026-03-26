<?php
/**
 * Ownuh SAIPS — GET /sessions
 * Returns active sessions. Admins see all; regular users see only their own.
 * SRS §3.4 — Session Management
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json');

use SAIPS\Middleware\AuthMiddleware;

$dbConfig  = require __DIR__ . '/../../config/database.php';
$secConfig = require __DIR__ . '/../../config/security.php';

$pdo = new PDO(
    "mysql:host={$dbConfig['app']['host']};dbname={$dbConfig['app']['name']};charset=utf8mb4",
    $dbConfig['app']['user'], $dbConfig['app']['pass'], $dbConfig['app']['options']
);

$auth    = new AuthMiddleware($secConfig);
$payload = $auth->validate();
$userId  = $payload['sub'];
$role    = $payload['role'];

$page    = max(1, (int)($_GET['page']     ?? 1));
$perPage = min(100, (int)($_GET['per_page'] ?? 50));
$offset  = ($page - 1) * $perPage;

// Admins can see all sessions; users only see their own
$isAdmin = in_array($role, ['admin', 'superadmin']);

if ($isAdmin) {
    $filterUserId = $_GET['user_id'] ?? null;
    if ($filterUserId) {
        $stmt = $pdo->prepare(
            'SELECT s.id, s.user_id, u.display_name, u.email, u.role,
                    s.ip_address, s.device_fingerprint, s.mfa_method,
                    s.created_at, s.expires_at, s.last_used_at,
                    TIMESTAMPDIFF(MINUTE, s.last_used_at, NOW()) as idle_minutes
             FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE s.invalidated_at IS NULL AND s.expires_at > NOW() AND s.user_id = ?
             ORDER BY s.created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$filterUserId, $perPage, $offset]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT s.id, s.user_id, u.display_name, u.email, u.role,
                    s.ip_address, s.device_fingerprint, s.mfa_method,
                    s.created_at, s.expires_at, s.last_used_at,
                    TIMESTAMPDIFF(MINUTE, s.last_used_at, NOW()) as idle_minutes
             FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE s.invalidated_at IS NULL AND s.expires_at > NOW()
             ORDER BY u.role DESC, s.created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$perPage, $offset]);
    }
} else {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.user_id, s.ip_address, s.device_fingerprint, s.mfa_method,
                s.created_at, s.expires_at, s.last_used_at,
                TIMESTAMPDIFF(MINUTE, s.last_used_at, NOW()) as idle_minutes
         FROM sessions s
         WHERE s.user_id = ? AND s.invalidated_at IS NULL AND s.expires_at > NOW()
         ORDER BY s.created_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute([$userId, $perPage, $offset]);
}

$sessions = $stmt->fetchAll();

echo json_encode([
    'status'   => 'success',
    'page'     => $page,
    'per_page' => $perPage,
    'data'     => $sessions,
]);
