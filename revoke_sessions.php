<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
session_start();

use SAIPS\Middleware\AuditMiddleware;

// Require admin or higher
$user = require_auth('admin');

// Init audit logging
AuditMiddleware::init(get_audit_pdo());

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// CSRF protection
if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid CSRF token');
}

// Inputs
$sessionId = $_POST['session_id'] ?? null;
$userId    = $_POST['user_id'] ?? null;

if (!$sessionId && !$userId) {
    http_response_code(400);
    exit('Missing parameters');
}

$db = Database::getInstance();

try {

    // 🔴 CASE 1: Revoke single session
    if ($sessionId) {

        $db->execute(
            'UPDATE sessions 
             SET invalidated_at = NOW() 
             WHERE id = ?',
            [$sessionId]
        );

        AuditMiddleware::log(
            'SES-002',
            'Session Revoked',
            $user['sub'],
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            null,
            json_encode(['session_id' => $sessionId])
        );

    }

    // 🔴 CASE 2: Revoke ALL sessions for a user
    if ($userId) {

        $db->execute(
            'UPDATE sessions 
             SET invalidated_at = NOW() 
             WHERE user_id = ? AND invalidated_at IS NULL',
            [$userId]
        );

        AuditMiddleware::log(
            'SES-003',
            'All Sessions Revoked',
            $user['sub'],
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            null,
            json_encode(['target_user' => $userId])
        );
    }

    // 🔴 Optional: remove from Redis
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);

        if ($sessionId) {
            $redis->del("saips:session:$sessionId");
        }

    } catch (Throwable $e) {
        error_log('[SAIPS] Redis revoke failed: ' . $e->getMessage());
    }

    // Redirect back
    header('Location: sessions.php?revoked=1');
    exit;

} catch (Throwable $e) {
    error_log('[SAIPS] Revoke session failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Server error');
}