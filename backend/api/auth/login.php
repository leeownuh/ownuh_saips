<?php
/**
 * Ownuh SAIPS — POST /auth/login
 * Primary authentication endpoint.
 * SRS §2.1 — Authentication Flow Overview
 *
 * Steps:
 * 1. Input validation
 * 2. IP check (blocked / geo / threat intel)
 * 3. Rate limit (60 req/min per IP)
 * 4. Username lookup
 * 5. Password verification (bcrypt)
 * 6. Risk engine evaluation
 * 7. Issue JWT or trigger MFA flow
 * 8. Audit log
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\IpCheckMiddleware;
use SAIPS\Middleware\RateLimitMiddleware;
use SAIPS\Services\EmailService;
use SAIPS\Services\AlertDispatcherService;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'code' => 'METHOD_NOT_ALLOWED']);
    exit;
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
$dbConfig  = require __DIR__ . '/../../config/database.php';
$secConfig = require __DIR__ . '/../../config/security.php';

$pdo       = new PDO(
    "mysql:host={$dbConfig['app']['host']};dbname={$dbConfig['app']['name']};charset=utf8mb4",
    $dbConfig['app']['user'], $dbConfig['app']['pass'], $dbConfig['app']['options']
);
$pdoAuth   = new PDO(
    "mysql:host={$dbConfig['auth']['host']};dbname=ownuh_credentials;charset=utf8mb4",
    $dbConfig['auth']['user'], $dbConfig['auth']['pass'], $dbConfig['auth']['options']
);
$redis = new Redis();
$redis->connect($dbConfig['redis']['host'], (int)$dbConfig['redis']['port']);
if ($dbConfig['redis']['pass']) {
    $redis->auth($dbConfig['redis']['pass']);
}

AuditMiddleware::init($pdo);

$ipCheck   = new IpCheckMiddleware($pdo, $redis, $secConfig);
$rateLimit = new RateLimitMiddleware($redis, $secConfig);
// SECURITY FIX: Only trust proxy IP headers when TRUSTED_PROXY env var is set.
// Trusting X-Forwarded-For from untrusted clients allows IP spoofing of rate limits.
$clientIp = (function(): string {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trustedProxy = $_ENV['TRUSTED_PROXY'] ?? '';

    // Only read proxy headers if REMOTE_ADDR is a trusted proxy
    if ($trustedProxy && $remoteAddr === $trustedProxy) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    // Default: use direct connection IP
    return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
})();
$geo = resolve_geo_from_ip($clientIp);

// ── IP checks ────────────────────────────────────────────────────────────────
$ipCheck->check($clientIp);
$rateLimit->check('/auth/login', $clientIp, 'per_ip');

// ── Parse input ──────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$email     = trim((string)($body['email']     ?? ''));
$password  = (string)($body['password']       ?? '');
$deviceFP  = substr((string)($body['device_fingerprint'] ?? ''), 0, 128);

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'Email and password are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Same generic error — don't reveal whether email exists
    _failAuth($email, $clientIp, 'invalid_format', 0, $rateLimit, $pdo, $geo['region'] ?? null);
}

// ── User lookup ──────────────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT id, display_name, email, role, status, mfa_enrolled, mfa_factor,
            failed_attempts, last_failed_at, last_login_country
     FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

// ── Progressive delay ─────────────────────────────────────────────────────
$delay = $rateLimit->getProgressiveDelay($email);
if ($delay > 0) {
    sleep($delay);
}

// ── Password verification (constant-time regardless of user existence) ────
if (!$user) {
    // Run a dummy bcrypt to prevent timing attacks
    password_verify($password, '$2y$12$dummyhashtopreventtimingattacks1234567890123456789012');
    _failAuth($email, $clientIp, 'user_not_found', 0, $rateLimit, $pdo, $geo['region'] ?? null);
}

// Fetch hash from isolated credentials DB
$stmtCred = $pdoAuth->prepare('SELECT password_hash, bcrypt_cost FROM credentials WHERE user_id = ?');
$stmtCred->execute([$user['id']]);
$cred = $stmtCred->fetch();

if (!$cred || !password_verify($password, $cred['password_hash'])) {
    $rateLimit->recordFailure($email, $clientIp);
    $attempt = $user['failed_attempts'] + 1;

    // Update failed attempt count
    $pdo->prepare('UPDATE users SET failed_attempts = ?, last_failed_at = NOW() WHERE id = ?')
        ->execute([$attempt, $user['id']]);

    // Check lockout thresholds
    _checkLockout($pdo, $user, $attempt, $clientIp, $secConfig);

    AuditMiddleware::authFailure($email, $clientIp, 'bad_password', $attempt, $geo['region'] ?? null);
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'UNAUTHORIZED', 'message' => 'Invalid credentials.']);
    exit;
}

// ── Account status check ─────────────────────────────────────────────────
if ($user['status'] === 'locked') {
    AuditMiddleware::authFailure($email, $clientIp, 'account_locked', 0, $geo['region'] ?? null);
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'ACCOUNT_LOCKED', 'message' => 'Account locked. Contact your administrator.']);
    exit;
}
if ($user['status'] === 'suspended') {
    AuditMiddleware::authFailure($email, $clientIp, 'account_suspended', 0, $geo['region'] ?? null);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'ACCOUNT_SUSPENDED', 'message' => 'Account suspended.']);
    exit;
}

// ── Upgrade bcrypt cost if needed (SRS §2.3) ────────────────────────────
if ($user['status'] === 'pending') {
    AuditMiddleware::authFailure($email, $clientIp, 'account_pending_approval', 0, $geo['region'] ?? null);
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'code' => 'ACCOUNT_PENDING',
        'message' => 'Account pending administrator approval.',
    ]);
    exit;
}

if ($cred['bcrypt_cost'] < $secConfig['password']['bcrypt_upgrade_to']) {
    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $secConfig['password']['bcrypt_upgrade_to']]);
    $pdoAuth->prepare('UPDATE credentials SET password_hash = ?, bcrypt_cost = ? WHERE user_id = ?')
        ->execute([$newHash, $secConfig['password']['bcrypt_upgrade_to'], $user['id']]);
}

// ── Risk engine ─────────────────────────────────────────────────────────
$countryCode = $ipCheck->getCountryCode($clientIp);
$countryCode = $countryCode ?? ($geo['country'] ?? null);
$region = $geo['region'] ?? null;
$isTor       = $ipCheck->isTorExitNode($clientIp);
$riskScore   = _calculateRisk($user, $clientIp, $countryCode, $deviceFP, $isTor, $redis);

// ── Reset failed attempts and record successful login ────────────────────
$pdo->prepare(
    'UPDATE users SET failed_attempts = 0, last_failed_at = NULL,
     last_login_at = NOW(), last_login_ip = ?, last_login_country = ? WHERE id = ?'
)->execute([$clientIp, $countryCode ?? null, $user['id']]);
$rateLimit->resetFailures($email);

// ── High risk — block ────────────────────────────────────────────────────
if ($riskScore >= 80) {
    AuditMiddleware::authFailure($email, $clientIp, 'high_risk_blocked', 0, $region);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'ACCESS_DENIED', 'message' => 'Login denied. Contact your administrator.']);
    exit;
}

// ── Medium risk or MFA enrolled — require MFA ────────────────────────────
if ($riskScore >= 40 || $user['mfa_enrolled']) {
    $mfaToken = bin2hex(random_bytes(32));
    $redis->setex("saips:mfa_pending:{$mfaToken}", 300, json_encode([
        'user_id'     => $user['id'],
        'risk_score'  => $riskScore,
        'ip'          => $clientIp,
        'country'     => $countryCode,
        'region'      => $region,
        'device_fp'   => $deviceFP,
    ]));

    // Generate email OTP if that's the MFA factor
    if ($user['mfa_factor'] === 'email_otp') {
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $redis->setex("saips:email_otp:{$user['id']}", 600, $otp);
        dispatch_email_otp(
            (string)$user['email'],
            (string)($user['display_name'] ?? $user['email'] ?? ''),
            $otp,
            600
        );
        // OTP dispatched via EmailService in production.
    }

    echo json_encode([
        'status'      => 'mfa_required',
        'mfa_token'   => $mfaToken,
        'mfa_factors' => [$user['mfa_factor'], 'backup_code'],
        'risk_level'  => $riskScore >= 40 ? 'medium' : 'low',
    ]);
    exit;
}

// ── Issue tokens ─────────────────────────────────────────────────────────
[$accessToken, $refreshToken] = _issueTokens($user, $clientIp, $deviceFP, null, $pdo, $redis, $secConfig);

AuditMiddleware::authSuccess($user['id'], $clientIp, $countryCode ?? 'XX', 'none', $riskScore, $region);

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
        'mfa_method'   => $user['mfa_factor'],
    ],
]);

// ── Helpers ──────────────────────────────────────────────────────────────────

function _failAuth(string $email, string $ip, string $reason, int $attempt, $rl, $db, ?string $region = null): never
{
    try {
        $db->prepare(
            'INSERT INTO login_attempts (username, ip_address, success, failure_reason, attempted_at)
             VALUES (?, ?, 0, ?, NOW(3))'
        )->execute([$email, $ip, $reason]);
    } catch (Throwable $e) {
        error_log('[SAIPS] Failed to record login_attempt: ' . $e->getMessage());
    }
    AuditMiddleware::authFailure($email, $ip, $reason, $attempt, $region);
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'UNAUTHORIZED', 'message' => 'Invalid credentials.']);
    exit;
}

function _checkLockout(\PDO $pdo, array $user, int $attempt, string $ip, array $cfg): void
{
    $perUser    = $cfg['brute_force']['per_user'];
    $perUserHard= $cfg['brute_force']['per_user_hard'];

    if ($attempt >= $perUserHard['failures']) {
        $pdo->prepare("UPDATE users SET status = 'locked' WHERE id = ?")->execute([$user['id']]);
        AuditMiddleware::accountLocked($user['id'], 'hard', "10 failures in 24h window");
        _notifyLockoutAdmins($pdo, $user, $ip, '10 failures in 24h window');
        (new AlertDispatcherService())->dispatch('AUTH-003', [
            'event_code' => 'AUTH-003',
            'summary' => 'User account locked after repeated failed login attempts.',
            'user_email' => (string)($user['email'] ?? ''),
            'ip_address' => $ip,
            'match_count' => $attempt,
        ]);
    } elseif ($attempt >= $perUser['failures']) {
        // Soft lock — auto-expires in Redis
        // Admin alert dispatched by AlertService
        AuditMiddleware::accountLocked($user['id'], 'soft', "5 failures in 15 min");
        (new AlertDispatcherService())->dispatch('AUTH-002', [
            'event_code' => 'AUTH-002',
            'summary' => 'Repeated failed login threshold reached.',
            'user_email' => (string)($user['email'] ?? $user['display_name'] ?? ''),
            'ip_address' => $ip,
            'match_count' => $attempt,
        ]);
    }
}


function _notifyLockoutAdmins(\PDO $pdo, array $user, string $ip, string $reason): void
{
    try {
        $stmt = $pdo->query(
            "SELECT email, display_name
             FROM users
             WHERE deleted_at IS NULL
               AND status = 'active'
               AND role IN ('admin', 'superadmin')
             ORDER BY role, display_name"
        );
        $recipients = $stmt ? $stmt->fetchAll() : [];
        if (!$recipients) {
            return;
        }

        $emailService = new EmailService([
            'provider' => $_ENV['EMAIL_PROVIDER'] ?? 'smtp',
            'app_name' => $_ENV['APP_NAME'] ?? 'Ownuh SAIPS',
            'from_name' => $_ENV['EMAIL_FROM_NAME'] ?? 'Ownuh SAIPS',
            'from_email' => $_ENV['EMAIL_FROM_EMAIL'] ?? 'security@ownuh-saips.com',
            'reply_to' => $_ENV['EMAIL_REPLY_TO'] ?? ($_ENV['EMAIL_FROM_EMAIL'] ?? 'security@ownuh-saips.com'),
            'sendgrid_api_key' => $_ENV['SENDGRID_API_KEY'] ?? '',
            'aws_access_key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',
            'aws_secret_key' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '',
        ]);

        foreach ($recipients as $recipient) {
            $to = (string)($recipient['email'] ?? '');
            if ($to === '') {
                continue;
            }

            $emailService->sendTemplate($to, 'admin_account_locked_alert', [
                'display_name' => (string)($recipient['display_name'] ?? 'Security Admin'),
                'locked_email' => (string)($user['email'] ?? 'unknown'),
                'locked_role' => (string)($user['role'] ?? 'user'),
                'lock_reason' => $reason,
                'ip_address' => $ip,
                'timestamp' => date('Y-m-d H:i:s'),
            ], [
                'queue' => false,
            ]);
        }
    } catch (Throwable $e) {
        error_log('[SAIPS] Failed to send lockout admin alerts: ' . $e->getMessage());
    }
}

function _calculateRisk(array $user, string $ip, ?string $country, string $deviceFP, bool $isTor, \Redis $redis): int
{
    $score = 0;

    // Tor usage
    if ($isTor) {
        $score += 30;
        if (in_array($user['role'], ['admin', 'superadmin'])) $score += 50;
    }

    // New country (not seen before for this user)
    if ($country && $user['last_login_country'] && $country !== $user['last_login_country']) {
        $score += 20;
    }

    // New device fingerprint
    if ($deviceFP && $user['id']) {
        $knownFP = $redis->sIsMember("saips:devices:{$user['id']}", $deviceFP);
        if (!$knownFP) $score += 15;
    }

    // Unusual hour (00:00–05:00 UTC)
    $hour = (int)date('G', time());
    if ($hour >= 0 && $hour < 5) $score += 10;

    return min($score, 100);
}

function _issueTokens(array $user, string $ip, string $deviceFP, ?string $mfaMethod, \PDO $pdo, \Redis $redis, array $cfg): array
{
    $isAdmin        = in_array($user['role'], ['admin', 'superadmin']);
    $refreshTtl     = $isAdmin ? $cfg['jwt']['admin_refresh_ttl'] : $cfg['jwt']['refresh_ttl'];
    $now            = time();

    $accessPayload  = [
        'sub'        => $user['id'],
        'email'      => $user['email'],
        'role'       => $user['role'],
        'mfa_method' => $mfaMethod,
        'iat'        => $now,
        'exp'        => $now + $cfg['jwt']['access_ttl'],
        'iss'        => $cfg['jwt']['issuer'],
        'jti'        => bin2hex(random_bytes(16)),
    ];

    // Sign access token (RS256) using base64url per RFC 7515
    $b64url        = fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $privateKey    = openssl_pkey_get_private('file://' . $cfg['jwt']['private_key_path']);
    $header        = $b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload       = $b64url(json_encode($accessPayload));
    $data          = "{$header}.{$payload}";
    openssl_sign($data, $sig, $privateKey, OPENSSL_ALGO_SHA256);
    $accessToken   = $data . '.' . $b64url($sig);

    // Generate refresh token
    $rawRefresh    = bin2hex(random_bytes(64));
    $refreshHash   = hash('sha256', $rawRefresh);
    $sessionId     = bin2hex(random_bytes(16));

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
