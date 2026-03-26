<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — One-time Setup Script
 * Run ONCE after importing schema.sql and seed.sql.
 * Generates real bcrypt hashes for seed users and inserts into ownuh_credentials.
 * DELETE THIS FILE after running in production.
 *
 * Usage: php setup.php  OR  visit http://localhost/setup.php (delete after)
 */

// Basic protection - only run from CLI or with secret token
$cliMode = php_sapi_name() === 'cli';
$token   = $_GET['token'] ?? '';
$secret  = 'CHANGE_THIS_SETUP_TOKEN_BEFORE_USE';

if (!$cliMode && $token !== $secret) {
    http_response_code(403);
    echo "<h2>403 Forbidden</h2><p>Pass ?token=CHANGE_THIS_SETUP_TOKEN_BEFORE_USE to run setup.</p>";
    exit;
}

require_once __DIR__ . '/backend/bootstrap.php';

$output  = [];
$errors  = [];
$success = true;

echo $cliMode ? '' : '<!DOCTYPE html><html><body><pre>';

function out(string $msg) {
    echo $msg . PHP_EOL;
    flush();
}

out("=== Ownuh SAIPS Setup ===");
out("Started: " . date('Y-m-d H:i:s'));
out("");

// ── Step 1: Generate real bcrypt hashes for seed users ───────────────────────
out("Step 1: Generating bcrypt hashes for seed users...");

$devPassword = 'Admin@SAIPS2025!';
$cost        = 12;

$seedUsers = [
    'usr-001-0000-0000-0000-000000000001',
    'usr-002-0000-0000-0000-000000000002',
    'usr-003-0000-0000-0000-000000000003',
    'usr-004-0000-0000-0000-000000000004',
    'usr-005-0000-0000-0000-000000000005',
];

$dbConfig = require __DIR__ . '/backend/config/database.php';

try {
    $pdoAuth = new PDO(
        "mysql:host={$dbConfig['auth']['host']};dbname=ownuh_credentials;charset=utf8mb4",
        $dbConfig['auth']['user'],
        $dbConfig['auth']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );

    $stmt = $pdoAuth->prepare(
        'INSERT INTO credentials (user_id, password_hash, bcrypt_cost)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
    );

    foreach ($seedUsers as $userId) {
        $hash = password_hash($devPassword, PASSWORD_BCRYPT, ['cost' => $cost]);
        $stmt->execute([$userId, $hash, $cost]);
        out("  ✓ Hash generated for {$userId}");
    }

    out("  ✓ All seed credentials inserted into ownuh_credentials.credentials");

} catch (PDOException $e) {
    out("  ✗ Could not connect to ownuh_credentials: " . $e->getMessage());
    out("  → Falling back to user_credentials table in main DB...");

    try {
        $db = Database::getInstance();
        $hash = password_hash($devPassword, PASSWORD_BCRYPT, ['cost' => $cost]);
        foreach ($seedUsers as $userId) {
            $db->execute(
                'INSERT INTO credentials (user_id, password_hash, bcrypt_cost)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)',
                [$userId, $hash, $cost]
            );
            out("  ✓ Fallback hash stored for {$userId}");
        }
    } catch (Exception $e2) {
        out("  ✗ Fallback also failed: " . $e2->getMessage());
        $success = false;
    }
}

// ── Step 2: Verify JWT key files exist ───────────────────────────────────────
out("");
out("Step 2: Checking JWT key files...");

$privatePath = $_ENV['JWT_PRIVATE_KEY_PATH'] ?? __DIR__ . '/keys/private.pem';
$publicPath  = $_ENV['JWT_PUBLIC_KEY_PATH']  ?? __DIR__ . '/keys/public.pem';

if (!file_exists($privatePath) || !file_exists($publicPath)) {
    out("  ✗ JWT keys not found. Generating...");
    $keysDir = dirname($privatePath);
    if (!is_dir($keysDir)) mkdir($keysDir, 0700, true);

    $privateKey = openssl_pkey_new([
        'digest_alg'       => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($privateKey, $privateKeyPem);
    $publicKeyPem = openssl_pkey_get_details($privateKey)['key'];

    file_put_contents($privatePath, $privateKeyPem);
    chmod($privatePath, 0600);
    file_put_contents($publicPath, $publicKeyPem);

    out("  ✓ RSA-2048 key pair generated");
    out("  → Private: {$privatePath}");
    out("  → Public:  {$publicPath}");
} else {
    out("  ✓ JWT keys already exist");
}

// ── Step 3: Fix .env JWT key paths to match actual location ──────────────────
out("");
out("Step 3: Updating .env JWT key paths...");

$envPath = __DIR__ . '/backend/config/.env';
if (file_exists($envPath)) {
    $env = file_get_contents($envPath);
    $env = preg_replace('/^JWT_PRIVATE_KEY_PATH=.*/m', 'JWT_PRIVATE_KEY_PATH=' . $privatePath, $env);
    $env = preg_replace('/^JWT_PUBLIC_KEY_PATH=.*/m',  'JWT_PUBLIC_KEY_PATH='  . $publicPath,  $env);
    file_put_contents($envPath, $env);
    out("  ✓ .env updated with correct key paths");
} else {
    out("  ✗ .env not found at {$envPath}");
    $success = false;
}

// ── Step 4: Test database connection ─────────────────────────────────────────
out("");
out("Step 4: Testing database connection...");
try {
    $db = Database::getInstance();
    $count = $db->fetchScalar('SELECT COUNT(*) FROM users');
    out("  ✓ Database connected — {$count} users found");
} catch (Exception $e) {
    out("  ✗ Database error: " . $e->getMessage());
    $success = false;
}

// ── Step 5: Verify seed data ─────────────────────────────────────────────────
out("");
out("Step 5: Verifying seed data...");
try {
    $db    = Database::getInstance();
    $users = $db->fetchAll('SELECT display_name, email, role, status FROM users ORDER BY role');
    foreach ($users as $u) {
        out("  ✓ {$u['display_name']} <{$u['email']}> [{$u['role']}] — {$u['status']}");
    }
} catch (Exception $e) {
    out("  ✗ Could not read users: " . $e->getMessage());
}

// ── Done ──────────────────────────────────────────────────────────────────────
out("");
out("=== Setup " . ($success ? "COMPLETE ✓" : "COMPLETED WITH ERRORS ✗") . " ===");
out("");
out("Next steps:");
out("  1. Visit http://your-host/login.php");
out("  2. Login with: sophia.johnson@acme.com / Admin@SAIPS2025!");
out("  3. CHANGE THE PASSWORD IMMEDIATELY");
out("  4. DELETE this setup.php file");
out("");
out("⚠  SECURITY: Delete setup.php before going to production.");

echo $cliMode ? '' : '</pre></body></html>';
