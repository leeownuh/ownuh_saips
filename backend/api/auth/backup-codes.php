<?php
/**
 * Ownuh SAIPS — Backup Codes API
 * Generate, list, and regenerate backup codes for MFA recovery.
 * SRS §2.4 — Backup Codes (10 single-use recovery codes)
 * 
 * GET  /auth/mfa/backup-codes - List remaining backup codes count
 * POST /auth/mfa/backup-codes - Generate new backup codes (invalidates old ones)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use SAIPS\Middleware\AuditMiddleware;
use SAIPS\Middleware\AuthMiddleware;
use SAIPS\Middleware\RateLimitMiddleware;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

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

// ── Check if user has MFA enrolled ───────────────────────────────────────────
$stmt = $pdo->prepare('SELECT mfa_enrolled, mfa_factor FROM users WHERE id = ?');
$stmt->execute([$userId]);
$userRow = $stmt->fetch();

if (!$userRow || !$userRow['mfa_enrolled']) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'MFA_NOT_ENROLLED', 'message' => 'MFA must be enrolled before generating backup codes.']);
    exit;
}

// ── Handle GET request - List backup codes status ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN used_at IS NULL THEN 1 ELSE 0 END) as remaining FROM mfa_backup_codes WHERE user_id = ?');
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
    echo json_encode([
        'status'        => 'success',
        'total_codes'   => (int)($stats['total'] ?? 0),
        'remaining'     => (int)($stats['remaining'] ?? 0),
        'used'          => (int)(($stats['total'] ?? 0) - ($stats['remaining'] ?? 0)),
        'warning'       => ($stats['remaining'] ?? 0) < 3 ? 'Low backup codes remaining. Consider generating new codes.' : null,
    ]);
    exit;
}

// ── Handle POST request - Generate new backup codes ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Rate limit
    try {
        $rateLimit->check('/auth/mfa/backup-codes', $userId, 'per_user');
    } catch (Throwable $e) {
        exit;
    }
    
    // Parse input
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $regenerate = (bool)($body['regenerate'] ?? true);
    $password = $body['password'] ?? null;
    
    // Require password confirmation for security
    if (!$password) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'code' => 'PASSWORD_REQUIRED', 'message' => 'Password confirmation required to generate backup codes.']);
        exit;
    }
    
    // Verify password
    $pdoAuth = new PDO(
        "mysql:host={$dbConfig['auth']['host']};dbname=ownuh_credentials;charset=utf8mb4",
        $dbConfig['auth']['user'], $dbConfig['auth']['pass'], $dbConfig['auth']['options']
    );
    
    $stmtCred = $pdoAuth->prepare('SELECT password_hash FROM credentials WHERE user_id = ?');
    $stmtCred->execute([$userId]);
    $cred = $stmtCred->fetch();
    
    if (!$cred || !password_verify($password, $cred['password_hash'])) {
        AuditMiddleware::authFailure($userId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'backup_codes_bad_password', 0);
        http_response_code(401);
        echo json_encode(['status' => 'error', 'code' => 'UNAUTHORIZED', 'message' => 'Invalid password.']);
        exit;
    }
    
    // Generate new backup codes
    $backupCodes = _generateBackupCodes($userId, $pdo, $secConfig);
    
    AuditMiddleware::backupCodesGenerated($userId);
    
    echo json_encode([
        'status'        => 'success',
        'message'       => 'New backup codes generated successfully. Previous unused codes have been invalidated.',
        'backup_codes'  => $backupCodes,
        'warning'       => 'Store these codes securely. They will not be shown again.',
        'count'         => count($backupCodes),
    ]);
    exit;
}

// ── Method not allowed ───────────────────────────────────────────────────────
http_response_code(405);
echo json_encode(['status' => 'error', 'code' => 'METHOD_NOT_ALLOWED']);
exit;

// ── Generate Backup Codes Function ───────────────────────────────────────────
function _generateBackupCodes(string $userId, PDO $pdo, array $config): array
{
    $count = $config['mfa']['backup_codes_count'] ?? 10;
    $codes = [];
    
    // Delete all old codes (including unused ones) when regenerating
    $pdo->prepare('DELETE FROM mfa_backup_codes WHERE user_id = ?')->execute([$userId]);
    
    for ($i = 0; $i < $count; $i++) {
        // Generate 8-character alphanumeric code (easier to read/type)
        $code = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
        $codeHash = password_hash($code, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $pdo->prepare('INSERT INTO mfa_backup_codes (user_id, code_hash) VALUES (?, ?)')
            ->execute([$userId, $codeHash]);
        
        $codes[] = $code;
    }
    
    return $codes;
}