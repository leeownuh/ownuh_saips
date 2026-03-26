<?php
/**
 * Ownuh SAIPS — POST /auth/password/reset
 * Sends a password reset link via email.
 * Always returns HTTP 200 regardless of whether email exists (prevents enumeration).
 * SRS §2.2 + §6.1 (admins cannot self-reset)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\RateLimitMiddleware;

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

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$email = strtolower(trim((string)($body['email'] ?? '')));

// Always return success to prevent email enumeration
$genericResponse = ['status' => 'success', 'message' => 'If an account exists for that email, a reset link has been sent.'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode($genericResponse);
    exit;
}

$stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE email = ? AND deleted_at IS NULL');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Timing attack mitigation — sleep same duration as real path
    usleep(random_int(100000, 300000));
    echo json_encode($genericResponse);
    exit;
}

// Admin accounts cannot use self-service reset (SRS §6.1)
if (in_array($user['role'], ['admin', 'superadmin'])) {
    // Log attempt silently but return generic success
    AuditMiddleware::log('AUTH-005', 'Password Reset Attempted (Admin — Blocked)', $user['id'], null, null, null, null, null, [
        'reason' => 'admin_self_service_not_permitted',
    ]);
    usleep(random_int(100000, 300000));
    echo json_encode($genericResponse);
    exit;
}

// Generate secure reset token (valid 15 minutes)
$token     = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$ttl       = 900; // 15 minutes

$redis->setex("saips:pwreset:{$tokenHash}", $ttl, $user['id']);

// Send email (implementation via configured SMTP / SES / Sendgrid)
// SECURITY FIX: Token is delivered in email body — page uses JS to POST it to
// /auth/password/confirm. Never include token in query string (leaks via Referer/logs).
// The linked page reads the fragment (#token=...) client-side only, never sent to server.
$resetUrl = ($_ENV['APP_URL'] ?? '') . "/auth-create-password.html#token={$token}";
_sendResetEmail($email, $resetUrl, $secConfig);

AuditMiddleware::log('AUTH-005', 'Password Reset Requested', $user['id'], null, null, null, null, null, [
    'method' => 'self_service_email',
]);

echo json_encode($genericResponse);

function _sendResetEmail(string $to, string $url, array $cfg): void
{
    // In production: use PHPMailer / Symfony Mailer / AWS SES
    // Stub implementation:
    error_log("[SAIPS] Password reset email would be sent to {$to} with URL: {$url}");
}
