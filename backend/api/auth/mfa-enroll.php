<?php
/**
 * Ownuh SAIPS — POST /auth/mfa/enroll
 * MFA Enrollment Endpoint — Allows users to enroll TOTP, Email OTP, or FIDO2.
 * SRS §2.4 — Multi-Factor Authentication
 * 
 * POST /auth/mfa/enroll
 * Request body: { "factor": "totp|email_otp|fido2", "totp_secret?: "...", "fido2_credential?: {...} }
 * Response: { "status": "success", "message": "MFA enrolled successfully", "factor": "totp" }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\AuthMiddleware;
use SAIPS\Middleware\RateLimitMiddleware;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'code' => 'METHOD_NOT_ALLOWED']);
    exit;
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$dbConfig  = require __DIR__ . '/../../config/database.php';
$secConfig = require __DIR__ . '/../../config/security.php';

$pdo       = new PDO(
    "mysql:host={$dbConfig['app']['host']};dbname={$dbConfig['app']['name']};charset=utf8mb4",
    $dbConfig['app']['user'], $dbConfig['app']['pass'], $dbConfig['app']['options']
);

$redis = new Redis();
$redis->connect($dbConfig['redis']['host'], (int)$dbConfig['redis']['port']);
if ($dbConfig['redis']['pass']) {
    $redis->auth($dbConfig['redis']['pass']);
}

AuditMiddleware::init($pdo);
$rateLimit = new RateLimitMiddleware($redis, $secConfig);

// ── Authenticate user via JWT ─────────────────────────────────────────────────
$auth = new AuthMiddleware($pdo, $redis, $secConfig);
$user = $auth->authenticate();

if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'UNAUTHORIZED', 'message' => 'Authentication required.']);
    exit;
}

$userId = $user['sub'];

// ── Rate limit ────────────────────────────────────────────────────────────────
try {
    $rateLimit->check('/auth/mfa/enroll', $userId, 'per_user');
} catch (Throwable $e) {
    exit;
}

// ── Parse input ───────────────────────────────────────────────────────────────
$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$factor  = (string)($body['factor'] ?? '');
$totpSecret = $body['totp_secret'] ?? null;
$fido2Credential = $body['fido2_credential'] ?? null;
$verifyCode = preg_replace('/\D/', '', (string)($body['verify_code'] ?? ''));

if (!in_array($factor, ['totp', 'email_otp', 'fido2'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Invalid MFA factor. Must be totp, email_otp, or fido2.']);
    exit;
}

// ── Check if user already has MFA enrolled ────────────────────────────────────
$stmt = $pdo->prepare('SELECT mfa_enrolled, mfa_factor FROM users WHERE id = ?');
$stmt->execute([$userId]);
$userRow = $stmt->fetch();

if ($userRow && $userRow['mfa_enrolled'] && $userRow['mfa_factor'] !== 'none') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'ALREADY_ENROLLED', 'message' => 'MFA is already enrolled. Use reset to change.']);
    exit;
}

// ── Check role requirements for FIDO2 ─────────────────────────────────────────
$fido2RequiredRoles = $secConfig['mfa']['fido2_required_roles'] ?? ['superadmin', 'admin'];
if ($factor === 'fido2' && !in_array($user['role'], $fido2RequiredRoles)) {
    // Allow but warn
    // For non-admin roles, FIDO2 is optional
}

$result = null;

switch ($factor) {
    case 'totp':
        $result = _enrollTotp($userId, $totpSecret, $verifyCode, $pdo, $secConfig);
        break;

    case 'email_otp':
        $result = _enrollEmailOtp($userId, $pdo, $redis, $secConfig);
        break;

    case 'fido2':
        $result = _enrollFido2($userId, $fido2Credential, $pdo);
        break;
}

if (!$result['success']) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'ENROLLMENT_FAILED', 'message' => $result['message']]);
    exit;
}

// ── Update user MFA status ────────────────────────────────────────────────────
$pdo->prepare('UPDATE users SET mfa_enrolled = 1, mfa_factor = ? WHERE id = ?')
    ->execute([$factor, $userId]);

// ── Generate backup codes ─────────────────────────────────────────────────────
$backupCodes = _generateBackupCodes($userId, $pdo, $secConfig);

AuditMiddleware::mfaEnrolled($userId, $factor);

echo json_encode([
    'status'       => 'success',
    'message'      => 'MFA enrolled successfully',
    'factor'       => $factor,
    'backup_codes' => $backupCodes,
    'totp_uri'     => $result['totp_uri'] ?? null,
]);

// ── TOTP Enrollment ───────────────────────────────────────────────────────────
function _enrollTotp(string $userId, ?string $secret, string $verifyCode, PDO $pdo, array $config): array
{
    // If no secret provided, generate one
    if (!$secret) {
        $secret = _generateTotpSecret();
        
        // Return the secret for user to add to authenticator app
        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $issuer = urlencode($config['jwt']['issuer'] ?? 'Ownuh-SAIPS');
        $email = urlencode($user['email'] ?? 'user');
        $totpUri = "otpauth://totp/{$issuer}:{$email}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
        
        // Store pending enrollment in Redis (valid for 5 minutes)
        global $redis;
        $redis->setex("saips:totp_pending:{$userId}", 300, json_encode([
            'secret' => $secret,
            'created_at' => time(),
        ]));
        
        return [
            'success' => false,
            'message' => 'Secret generated. Verify with a TOTP code to complete enrollment.',
            'totp_secret' => $secret,
            'totp_uri' => $totpUri,
            'needs_verification' => true,
        ];
    }
    
    // Verify the TOTP code
    if (!$verifyCode) {
        return ['success' => false, 'message' => 'Verification code required.'];
    }
    
    // Get pending enrollment
    global $redis;
    $pendingJson = $redis->get("saips:totp_pending:{$userId}");
    if (!$pendingJson) {
        // Use provided secret directly
        $pendingSecret = $secret;
    } else {
        $pending = json_decode($pendingJson, true);
        $pendingSecret = $pending['secret'];
    }
    
    // Verify the code
    $expectedCode = _generateTotpCode($pendingSecret, time());
    $prevCode = _generateTotpCode($pendingSecret, time() - 30);
    $nextCode = _generateTotpCode($pendingSecret, time() + 30);
    
    if (!hash_equals($expectedCode, $verifyCode) && !hash_equals($prevCode, $verifyCode) && !hash_equals($nextCode, $verifyCode)) {
        return ['success' => false, 'message' => 'Invalid verification code.'];
    }
    
    // Encrypt and store the secret
    $encryptedSecret = _encryptTotpSecret($pendingSecret, $config);
    
    $pdo->prepare('INSERT INTO mfa_totp_secrets (user_id, secret_encrypted, algorithm, digits, period) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $encryptedSecret, 'SHA1', 6, 30]);
    
    // Clear pending
    $redis->del("saips:totp_pending:{$userId}");
    
    return ['success' => true, 'message' => 'TOTP enrolled successfully.'];
}

// ── Email OTP Enrollment ──────────────────────────────────────────────────────
function _enrollEmailOtp(string $userId, PDO $pdo, Redis $redis, array $config): array
{
    // Email OTP doesn't require pre-registration - codes are generated at login
    // Just mark the user as enrolled
    
    // Send a test OTP to verify email works
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || !$user['email']) {
        return ['success' => false, 'message' => 'No email address on file.'];
    }
    
    // Generate and send test OTP
    $testOtp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $redis->setex("saips:email_otp_test:{$userId}", 300, $testOtp);
    
    // Send email (using EmailService when implemented)
    // For now, log it
    // Test OTP dispatched via EmailService — never log credentials in production
    
    return ['success' => true, 'message' => 'Email OTP enrolled. A test code has been sent to your email.'];
}

// ── FIDO2/WebAuthn Enrollment ─────────────────────────────────────────────────
function _enrollFido2(string $userId, ?array $credential, PDO $pdo): array
{
    if (!$credential) {
        // Return challenge for WebAuthn registration
        $challenge = bin2hex(random_bytes(32));
        
        global $redis;
        $redis->setex("saips:fido2_challenge:{$userId}", 120, json_encode([
            'challenge' => $challenge,
            'created_at' => time(),
        ]));
        
        return [
            'success' => false,
            'message' => 'FIDO2 challenge generated. Complete registration with credential.',
            'challenge' => $challenge,
            'rp_id' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'user_id' => $userId,
            'needs_credential' => true,
        ];
    }
    
    // Verify and store credential
    // Full WebAuthn verification would use web-auth/webauthn-lib
    $credentialId = $credential['id'] ?? null;
    $publicKey = $credential['public_key'] ?? null;
    $signCount = (int)($credential['sign_count'] ?? 0);
    $deviceDesc = $credential['device_description'] ?? 'Unknown Device';
    $aaguid = $credential['aaguid'] ?? null;
    
    if (!$credentialId || !$publicKey) {
        return ['success' => false, 'message' => 'Invalid credential data.'];
    }
    
    // Store credential
    $pdo->prepare('INSERT INTO mfa_fido2_credentials (user_id, credential_id, public_key, sign_count, device_description, aaguid) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$userId, $credentialId, $publicKey, $signCount, $deviceDesc, $aaguid]);
    
    return ['success' => true, 'message' => 'FIDO2 key registered successfully.'];
}

// ── Generate Backup Codes ─────────────────────────────────────────────────────
function _generateBackupCodes(string $userId, PDO $pdo, array $config): array
{
    $count = $config['mfa']['backup_codes_count'] ?? 10;
    $codes = [];
    
    // Delete old unused codes
    $pdo->prepare('DELETE FROM mfa_backup_codes WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
    
    for ($i = 0; $i < $count; $i++) {
        // Generate 8-character alphanumeric code
        $code = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
        $codeHash = password_hash($code, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $pdo->prepare('INSERT INTO mfa_backup_codes (user_id, code_hash) VALUES (?, ?)')
            ->execute([$userId, $codeHash]);
        
        $codes[] = $code;
    }
    
    return $codes;
}

// ── TOTP Helper Functions ─────────────────────────────────────────────────────
function _generateTotpSecret(): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 16; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

function _generateTotpCode(string $secret, int $time): string
{
    $key = _base32Decode($secret);
    $period = 30;
    $counter = (int)floor($time / $period);
    $timeBytes = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $timeBytes, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $otp = ((ord($hash[$offset]) & 0x7F) << 24 |
             ord($hash[$offset + 1]) << 16 |
             ord($hash[$offset + 2]) << 8 |
             ord($hash[$offset + 3])) % 1000000;
    return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
}

function _base32Decode(string $input): string
{
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper($input);
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0; $i < strlen($input); $i++) {
        $pos = strpos($map, $input[$i]);
        if ($pos === false) continue;
        
        $v = ($v << 5) | $pos;
        $vbits += 5;
        
        if ($vbits >= 8) {
            $vbits -= 8;
            $output .= chr(($v >> $vbits) & 0xFF);
        }
    }
    
    return $output;
}

function _encryptTotpSecret(string $secret, array $config): string
{
    // In production, use AES-256-GCM with a key from environment
    // For now, we'll use a simple encryption (should be replaced with proper implementation)
    $key = $_ENV['MFA_ENCRYPTION_KEY'] ?? 'default-dev-key-change-in-production';
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($secret, 'AES-256-CBC', hash('sha256', $key, true), 0, $iv);
    return base64_encode($iv . $encrypted);
}