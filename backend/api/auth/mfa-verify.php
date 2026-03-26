<?php
/**
 * Ownuh SAIPS — POST /auth/mfa/verify
 * Verifies MFA code after mfa_required response from /auth/login.
 * SRS §2.4 — Multi-Factor Authentication
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\RateLimitMiddleware;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'code' => 'METHOD_NOT_ALLOWED']);
    exit;
}

// ── Bootstrap ─────────────────────────────────────────────────────────────
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

// ── Parse input ───────────────────────────────────────────────────────────
$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$mfaToken  = (string)($body['mfa_token'] ?? '');
$factor    = (string)($body['factor']    ?? '');
$code      = preg_replace('/\D/', '', (string)($body['code'] ?? ''));

if (!$mfaToken || !$factor || !$code) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'mfa_token, factor, and code are required.']);
    exit;
}

// ── Load pending MFA session from Redis ───────────────────────────────────
$pendingJson = $redis->get("saips:mfa_pending:{$mfaToken}");
if (!$pendingJson) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'INVALID_TOKEN', 'message' => 'MFA session expired or invalid. Please log in again.']);
    exit;
}

$pending = json_decode($pendingJson, true);
$userId  = $pending['user_id'];

// ── Rate limit: 5 attempts per 15 min per user (SRS §2.4) ────────────────
try {
    $rateLimit->check('/auth/mfa/verify', $userId, 'per_user');
} catch (Throwable $e) {
    // Rate limit exceeded - abort already handled
    exit;
}

// ── Fetch user + MFA config ───────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, email, role, mfa_factor, mfa_enrolled, display_name, last_login_country FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !$user['mfa_enrolled']) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'UNAUTHORIZED']);
    exit;
}

$verified = false;

switch ($factor) {
    case 'totp':
        $verified = _verifyTotp($userId, $code, $pdo);
        break;

    case 'email_otp':
        $verified = _verifyEmailOtp($userId, $code, $redis);
        break;

    case 'backup_code':
        $verified = _verifyBackupCode($userId, $code, $pdo);
        break;

    case 'fido2':
        $verified = _verifyFido2($userId, $body, $pdo);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'code' => 'UNSUPPORTED_FACTOR']);
        exit;
}

if (!$verified) {
    AuditMiddleware::authFailure($user['id'], $pending['ip'], "mfa_failed:{$factor}", 0);
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'UNAUTHORIZED', 'message' => 'Invalid MFA code.']);
    exit;
}

// ── MFA passed — consume pending token and issue JWT pair ─────────────────
$redis->del("saips:mfa_pending:{$mfaToken}");

// Update last login timestamp and location now that auth is fully complete
$pdo->prepare(
    'UPDATE users SET last_login_at = NOW(), last_login_ip = ?, last_login_country = ?,
     failed_attempts = 0, last_failed_at = NULL WHERE id = ?'
)->execute([$pending['ip'], $pending['country'] ?? null, $userId]);

[$accessToken, $refreshToken] = _issueTokens(
    $user, $pending['ip'], $pending['device_fp'] ?? '', $factor,
    $pdo, $redis, $secConfig
);

AuditMiddleware::authSuccess($user['id'], $pending['ip'], $pending['country'] ?? 'XX', $factor, $pending['risk_score'] ?? 0);

echo json_encode([
    'status'        => 'success',
    'access_token'  => $accessToken,
    'refresh_token' => $refreshToken,
    'expires_in'    => $secConfig['jwt']['access_ttl'],
    'user'          => [
        'id'           => $user['id'],
        'display_name' => $user['display_name'],
        'email'        => $user['email'],
        'role'         => $user['role'],
        'mfa_method'   => $factor,
    ],
]);

// ── TOTP verification (RFC 6238, ±1 step tolerance) ──────────────────────
function _verifyTotp(string $userId, string $code, PDO $pdo): bool
{
    $stmt = $pdo->prepare('SELECT secret_encrypted, period FROM mfa_totp_secrets WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return false;

    $secret = $row['secret_encrypted']; // In production, decrypt this
    $period = (int)$row['period'];
    $time   = time();

    // Check current window and ±1 step (tolerance per SRS §2.4)
    for ($i = -1; $i <= 1; $i++) {
        $counter   = (int)floor(($time + $i * $period) / $period);
        $expected  = _generateTotp($secret, $counter);
        if (hash_equals($expected, $code)) {
            $pdo->prepare('UPDATE mfa_totp_secrets SET last_used_at = NOW() WHERE user_id = ?')->execute([$userId]);
            return true;
        }
    }
    return false;
}

function _generateTotp(string $secret, int $counter): string
{
    // Base32 decode
    $key = _base32Decode($secret);
    $time = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $otp = ((ord($hash[$offset]) & 0x7F) << 24 |
             ord($hash[$offset + 1]) << 16 |
             ord($hash[$offset + 2]) << 8  |
             ord($hash[$offset + 3])) % 1_000_000;
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

// ── Email OTP verification ───────────────────────────────────────────────
function _verifyEmailOtp(string $userId, string $code, Redis $redis): bool
{
    $key = "saips:email_otp:{$userId}";
    $stored = $redis->get($key);
    if (!$stored) return false;

    if (hash_equals($stored, $code)) {
        $redis->del($key);
        return true;
    }
    return false;
}

// ── Backup code verification ─────────────────────────────────────────────
function _verifyBackupCode(string $userId, string $code, PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        'SELECT id, code_hash FROM mfa_backup_codes WHERE user_id = ? AND used_at IS NULL'
    );
    $stmt->execute([$userId]);
    $codes = $stmt->fetchAll();

    foreach ($codes as $row) {
        if (password_verify($code, $row['code_hash'])) {
            $pdo->prepare('UPDATE mfa_backup_codes SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
            return true;
        }
    }
    return false;
}

// ── FIDO2/WebAuthn assertion ─────────────────────────────────────────────
function _verifyFido2(string $userId, array $body, PDO $pdo): bool
{
    global $redis;
    
    require_once __DIR__ . '/../../Services/WebAuthnService.php';
    
    $webauthn = new \SAIPS\Services\WebAuthnService(
        $pdo,
        $redis,
        [
            'rp_id' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'rp_name' => 'Ownuh SAIPS',
        ]
    );
    
    $result = $webauthn->verifyAuthentication(
        $userId,
        $body['credential_id'] ?? '',
        $body['client_data_json'] ?? '',
        $body['authenticator_data'] ?? '',
        $body['signature'] ?? ''
    );
    
    return $result['success'] ?? false;
}

// ── Issue JWT tokens ─────────────────────────────────────────────────────
function _issueTokens(array $user, string $ip, string $deviceFP, ?string $mfaMethod, PDO $pdo, Redis $redis, array $cfg): array
{
    $isAdmin = in_array($user['role'], ['admin', 'superadmin']);
    $refreshTtl = $isAdmin ? $cfg['jwt']['admin_refresh_ttl'] : $cfg['jwt']['refresh_ttl'];
    $now = time();

    $accessPayload = [
        'sub'        => $user['id'],
        'email'      => $user['email'],
        'role'       => $user['role'],
        'mfa_method' => $mfaMethod,
        'iat'        => $now,
        'exp'        => $now + $cfg['jwt']['access_ttl'],
        'iss'        => $cfg['jwt']['issuer'],
        'jti'        => bin2hex(random_bytes(16)),
    ];

    // Sign access token (RS256) using base64url encoding per RFC 7515
    $b64url = fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $privateKey = openssl_pkey_get_private('file://' . $cfg['jwt']['private_key_path']);
    $header  = $b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = $b64url(json_encode($accessPayload));
    $data    = "{$header}.{$payload}";
    openssl_sign($data, $sig, $privateKey, OPENSSL_ALGO_SHA256);
    $accessToken = $data . '.' . $b64url($sig);

    // Generate refresh token
    $rawRefresh = bin2hex(random_bytes(64));
    $refreshHash = hash('sha256', $rawRefresh);
    $sessionId = bin2hex(random_bytes(16));

    // Store session
    $pdo->prepare(
        'INSERT INTO sessions (id, user_id, refresh_token_hash, ip_address, device_fingerprint, mfa_method, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $sessionId,
        $user['id'],
        $refreshHash,
        $ip,
        $deviceFP,
        $mfaMethod,
        date('Y-m-d H:i:s', $now + $refreshTtl),
    ]);

    // Store in Redis (fast revocation path)
    $redis->setex("saips:session:{$refreshHash}", $refreshTtl, json_encode([
        'session_id' => $sessionId,
        'user_id'    => $user['id'],
        'role'       => $user['role'],
    ]));

    AuditMiddleware::sessionCreated($sessionId, $user['id'], $ip);

    return [$accessToken, $rawRefresh];
}