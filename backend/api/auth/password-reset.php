<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 1) . '/../../vendor/autoload.php';

session_start();

use SAIPS\Middleware\AuditMiddleware;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrf)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

AuditMiddleware::init(get_audit_pdo());

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

$email = strtolower(trim((string)($body['email'] ?? '')));
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'status'  => 'success',
        'message' => 'If that email is registered, a reset link has been sent.'
    ]);
    exit;
}

$db = Database::getInstance();

try {
    $user = $db->fetchOne(
        'SELECT id, email, role, status, deleted_at
         FROM users
         WHERE email = ? AND deleted_at IS NULL',
        [$email]
    );

    // Always generic response to avoid enumeration
    $genericResponse = [
        'status'  => 'success',
        'message' => 'If that email is registered, a reset link has been sent.'
    ];

    if (!$user) {
        AuditMiddleware::log('AUTH-010', 'Password Reset Requested', null, $ip, null, null, null, null, [
            'email' => $email,
            'outcome' => 'email_not_found'
        ]);
        echo json_encode($genericResponse);
        exit;
    }

    // Block self-service reset for admins/superadmins
    if (in_array($user['role'], ['admin', 'superadmin'], true)) {
        AuditMiddleware::log('AUTH-010', 'Password Reset Requested', $user['id'], $ip, null, null, null, null, [
            'email' => $email,
            'outcome' => 'admin_self_service_blocked'
        ]);
        echo json_encode($genericResponse);
        exit;
    }

    if (!in_array($user['status'], ['active', 'pending'], true)) {
        AuditMiddleware::log('AUTH-010', 'Password Reset Requested', $user['id'], $ip, null, null, null, null, [
            'email' => $email,
            'outcome' => 'status_blocked',
            'status' => $user['status']
        ]);
        echo json_encode($genericResponse);
        exit;
    }

    $plainToken = bin2hex(random_bytes(32));
    $tokenHash  = hash('sha256', $plainToken);

    // single active token per user
    $db->execute(
        'UPDATE password_resets
         SET used_at = NOW()
         WHERE user_id = ? AND used_at IS NULL',
        [$user['id']]
    );

    $db->execute(
        'INSERT INTO password_resets (
            user_id, token_hash, requested_at, expires_at, used_at, requested_ip
         ) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL, ?)',
        [$user['id'], $tokenHash, $ip]
    );

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = dirname(dirname(dirname(dirname($scriptName))));
    $basePath = $basePath === '/' || $basePath === '\\' ? '' : rtrim($basePath, '/');

    $resetLink = $scheme . $host . $basePath . '/reset-password.php?token=' . urlencode($plainToken);

    // Replace this with actual email sending later
    error_log('[SAIPS RESET LINK] ' . $user['email'] . ' => ' . $resetLink);

    AuditMiddleware::log('AUTH-010', 'Password Reset Requested', $user['id'], $ip, null, null, null, null, [
        'email' => $email,
        'outcome' => 'reset_link_issued'
    ]);

    echo json_encode($genericResponse);
} catch (Throwable $e) {
    error_log('[SAIPS] Password reset request failed: ' . $e->getMessage());

    echo json_encode([
        'status'  => 'success',
        'message' => 'If that email is registered, a reset link has been sent.'
    ]);
}
