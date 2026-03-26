<?php
/**
 * Ownuh SAIPS — POST /auth/register
 * Self-service account registration.
 * New accounts are created in 'pending' status — admin approval required.
 * SRS §2.1 — Authentication Flow / Registration Gate
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

$dbConfig  = require __DIR__ . '/../../config/database.php';
$secConfig = require __DIR__ . '/../../config/security.php';

$pdo = new PDO(
    "mysql:host={$dbConfig['app']['host']};dbname={$dbConfig['app']['name']};charset=utf8mb4",
    $dbConfig['app']['user'], $dbConfig['app']['pass'], $dbConfig['app']['options']
);
$pdoAuth = new PDO(
    "mysql:host={$dbConfig['auth']['host']};dbname=ownuh_credentials;charset=utf8mb4",
    $dbConfig['auth']['user'], $dbConfig['auth']['pass'], $dbConfig['auth']['options']
);

AuditMiddleware::init($pdo);

// Rate limit: 5 registration attempts per IP per hour
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
try {
    $redis = new Redis();
    $redis->connect($dbConfig['redis']['host'], (int)$dbConfig['redis']['port']);
    if ($dbConfig['redis']['pass']) $redis->auth($dbConfig['redis']['pass']);
    $rlKey = "saips:reg:{$ip}";
    $attempts = (int)$redis->incr($rlKey);
    if ($attempts === 1) $redis->expire($rlKey, 3600);
    if ($attempts > 5) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'code' => 'RATE_LIMITED', 'message' => 'Too many registration attempts. Try again later.']);
        exit;
    }
} catch (\Exception $e) {
    // Redis unavailable — continue without rate limit
}

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$email       = strtolower(trim((string)($body['email']        ?? '')));
$displayName = trim((string)($body['display_name']            ?? ''));
$password    = (string)($body['password']                     ?? '');

// ── Input validation ─────────────────────────────────────────────────────────
$errors = [];

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid work email address is required.';
}
if (!$displayName || mb_strlen($displayName) < 2 || mb_strlen($displayName) > 120) {
    $errors[] = 'Full name must be 2–120 characters.';
}

// SRS §2.2 password policy
$policy = $secConfig['password'] ?? [];
$minLen = $policy['min_length'] ?? 12;
$maxLen = $policy['max_length'] ?? 128;

if (mb_strlen($password) < $minLen) {
    $errors[] = "Password must be at least {$minLen} characters.";
} elseif (mb_strlen($password) > $maxLen) {
    $errors[] = "Password must be no more than {$maxLen} characters.";
} else {
    // 3-of-4 character classes
    $classes = 0;
    if (preg_match('/[A-Z]/', $password)) $classes++;
    if (preg_match('/[a-z]/', $password)) $classes++;
    if (preg_match('/[0-9]/', $password)) $classes++;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $classes++;
    if ($classes < 3) {
        $errors[] = 'Password must contain at least 3 of: uppercase, lowercase, digit, special character.';
    }
    // Username similarity check (Levenshtein)
    $namePart = strtolower(explode(' ', $displayName)[0]);
    $emailPart = strtolower(explode('@', $email)[0]);
    if (
        similar_text(strtolower($password), $namePart) / max(1, strlen($namePart)) > 0.6 ||
        similar_text(strtolower($password), $emailPart) / max(1, strlen($emailPart)) > 0.6
    ) {
        $errors[] = 'Password cannot closely resemble your name or email.';
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'code' => 'VALIDATION_ERROR', 'errors' => $errors]);
    exit;
}

// ── Duplicate email check ────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    // Generic response to prevent enumeration
    http_response_code(200);
    echo json_encode([
        'status'  => 'success',
        'message' => 'If this email is not already registered, your account request has been submitted for admin approval.',
    ]);
    exit;
}

// ── Create user (pending approval) ──────────────────────────────────────────
$userId    = bin2hex(random_bytes(16));
$pwHash    = password_hash($password, PASSWORD_BCRYPT, ['cost' => $secConfig['password']['bcrypt_cost'] ?? 12]);

// Insert into main users table
$pdo->prepare(
    'INSERT INTO users (id, display_name, email, role, status, created_at)
     VALUES (?, ?, ?, "user", "pending", NOW())'
)->execute([$userId, $displayName, $email]);

// Insert password into credentials store
try {
    $pdoAuth->prepare(
        'INSERT INTO credentials (user_id, password_hash, bcrypt_cost, created_at)
         VALUES (?, ?, ?, NOW())'
    )->execute([$userId, $pwHash, $secConfig['password']['bcrypt_cost'] ?? 12]);
} catch (\Exception $e) {
    // Credentials DB may use different schema — store in main DB fallback
    $pdo->prepare(
        'INSERT INTO password_history (id, user_id, password_hash)
         VALUES (UUID(), ?, ?)'
    )->execute([$userId, $pwHash]);
}

AuditMiddleware::log(
    'AUTH-000', 'User Registration', $userId,
    $ip, null, null, null, null,
    ['display_name' => $displayName, 'status' => 'pending_approval']
);

http_response_code(201);
echo json_encode([
    'status'  => 'success',
    'message' => 'Account request submitted. An administrator will review and approve your account. You will receive an email once approved.',
]);
