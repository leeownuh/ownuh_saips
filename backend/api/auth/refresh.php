<?php
/**
 * Ownuh SAIPS — POST /auth/token/refresh
 * Sliding window token rotation: old refresh token → new access + refresh pair.
 * Reuse of a revoked token triggers full session revocation + security alert.
 * SRS §3.4 — Session Management (token rotation)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\IpCheckMiddleware;

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

$body         = json_decode(file_get_contents('php://input'), true) ?? [];
$refreshToken = (string)($body['refresh_token'] ?? '');

if (!$refreshToken) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'message' => 'refresh_token is required.']);
    exit;
}

$hash = hash('sha256', $refreshToken);

// Check Redis first (fast path)
$sessionJson = $redis->get("saips:session:{$hash}");
if (!$sessionJson) {
    // Not in Redis — check DB (may have been evicted)
    $stmt = $pdo->prepare(
        'SELECT s.*, u.role, u.status, u.email, u.display_name, u.mfa_factor
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.refresh_token_hash = ?'
    );
    $stmt->execute([$hash]);
    $session = $stmt->fetch();

    if (!$session) {
        // Token unknown — could be theft attempt; revoke all sessions for safety
        _handleTokenTheft(null, $pdo, $redis);
    }

    if ($session['invalidated_at']) {
        // Already invalidated — possible token reuse attack
        _handleTokenTheft($session['user_id'], $pdo, $redis);
    }

    if (strtotime($session['expires_at']) < time()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'code' => 'TOKEN_EXPIRED', 'message' => 'Refresh token has expired.']);
        exit;
    }

    $sessionData = $session;
} else {
    $cached      = json_decode($sessionJson, true);
    $stmt        = $pdo->prepare(
        'SELECT s.*, u.role, u.status, u.email, u.display_name, u.mfa_factor, u.id as user_id
         FROM sessions s JOIN users u ON u.id = s.user_id
         WHERE s.id = ? AND s.invalidated_at IS NULL'
    );
    $stmt->execute([$cached['session_id']]);
    $sessionData = $stmt->fetch();

    if (!$sessionData) {
        $redis->del("saips:session:{$hash}");
        http_response_code(401);
        echo json_encode(['status' => 'error', 'code' => 'INVALID_TOKEN', 'message' => 'Session not found.']);
        exit;
    }
}

if ($sessionData['status'] !== 'active') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'ACCOUNT_LOCKED', 'message' => 'Account is not active.']);
    exit;
}

// Invalidate old refresh token (rotation) and record last use time
$pdo->prepare(
    'UPDATE sessions SET last_used_at = NOW(), invalidated_at = NOW(), invalidation_reason = "rotated" WHERE refresh_token_hash = ?'
)->execute([$hash]);
$redis->del("saips:session:{$hash}");

// Issue new token pair
$isAdmin     = in_array($sessionData['role'], ['admin', 'superadmin']);
$refreshTtl  = $isAdmin ? $secConfig['jwt']['admin_refresh_ttl'] : $secConfig['jwt']['refresh_ttl'];
$now         = time();

$accessPayload = [
    'sub'   => $sessionData['user_id'],
    'email' => $sessionData['email'],
    'role'  => $sessionData['role'],
    'iat'   => $now,
    'exp'   => $now + $secConfig['jwt']['access_ttl'],
    'iss'   => $secConfig['jwt']['issuer'],
    'jti'   => bin2hex(random_bytes(16)),
];

// RFC 7515 base64url encoding
$b64url       = fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
$privateKey   = openssl_pkey_get_private('file://' . $secConfig['jwt']['private_key_path']);
$header       = $b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
$payloadB64   = $b64url(json_encode($accessPayload));
$data         = "{$header}.{$payloadB64}";
openssl_sign($data, $sig, $privateKey, OPENSSL_ALGO_SHA256);
$newAccess    = $data . '.' . $b64url($sig);

$rawRefresh   = bin2hex(random_bytes(64));
$newHash      = hash('sha256', $rawRefresh);
$newSessionId = bin2hex(random_bytes(16));

$pdo->prepare(
    'INSERT INTO sessions (id, user_id, refresh_token_hash, ip_address, device_fingerprint, mfa_method, expires_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
)->execute([
    $newSessionId,
    $sessionData['user_id'],
    $newHash,
    $sessionData['ip_address'],
    $sessionData['device_fingerprint'],
    $sessionData['mfa_method'],
    date('Y-m-d H:i:s', $now + $refreshTtl),
]);

$redis->setex("saips:session:{$newHash}", $refreshTtl, json_encode([
    'session_id' => $newSessionId,
    'user_id'    => $sessionData['user_id'],
    'role'       => $sessionData['role'],
]));

echo json_encode([
    'status'        => 'success',
    'access_token'  => $newAccess,
    'refresh_token' => $rawRefresh,
    'expires_in'    => $secConfig['jwt']['access_ttl'],
]);

function _handleTokenTheft(?string $userId, \PDO $pdo, \Redis $redis): never
{
    if ($userId) {
        // Revoke ALL sessions for this user — possible token theft
        $pdo->prepare(
            'UPDATE sessions SET invalidated_at = NOW(), invalidation_reason = "token_reuse_attack"
             WHERE user_id = ? AND invalidated_at IS NULL'
        )->execute([$userId]);

        // Clear all Redis sessions for user
        $keys = $redis->keys("saips:session:*");
        foreach ($keys as $key) {
            $val = $redis->get($key);
            if ($val) {
                $d = json_decode($val, true);
                if (($d['user_id'] ?? null) === $userId) {
                    $redis->del($key);
                }
            }
        }

        AuditMiddleware::log('SES-003', 'Session Invalidated', $userId, null, null, null, null, null, [
            'reason' => 'token_reuse_detected_all_sessions_revoked',
        ]);
    }

    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'code'    => 'INVALID_TOKEN',
        'message' => 'Token invalid. All sessions have been revoked for security. Please log in again.',
    ]);
    exit;
}
