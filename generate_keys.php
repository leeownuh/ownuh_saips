<?php
/**
 * Ownuh SAIPS — RSA Key Generation Utility
 * Cross-platform: auto-detects openssl.cnf on Linux, macOS, Windows/XAMPP.
 * Usage (CLI): php generate_keys.php [--force]
 * Usage (web): navigate to generate_keys.php  — DELETE after use.
 */
declare(strict_types=1);

function find_openssl_cnf(): ?string {
    $env = getenv('OPENSSL_CONF') ?: getenv('SSLEAY_CONF');
    if ($env && file_exists($env)) return $env;
    $candidates = [
        '/etc/ssl/openssl.cnf',
        '/etc/pki/tls/openssl.cnf',
        '/usr/local/etc/openssl/openssl.cnf',
        '/usr/local/etc/openssl@3/openssl.cnf',
        '/usr/local/ssl/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
        'C:/xampp/apache/conf/openssl.cnf',
        'C:/laragon/etc/ssl/openssl.cnf',
        dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
        dirname(PHP_BINARY) . '/../ssl/openssl.cnf',
    ];
    foreach ($candidates as $p) { if (file_exists($p)) return $p; }
    return null;
}

$keysDir     = __DIR__ . '/keys';
$privatePath = $keysDir . '/private.pem';
$publicPath  = $keysDir . '/public.pem';
$cliForce    = in_array('--force', $argv ?? [], true);
$webForce    = ($_GET['force'] ?? '') === '1';

if (!is_dir($keysDir) && !mkdir($keysDir, 0750, true)) die("ERROR: Cannot create keys/ directory.\n");

if ((file_exists($privatePath) || file_exists($publicPath)) && !$cliForce && !$webForce) {
    $msg = "Keys already exist. Use --force (CLI) or ?force=1 (web) to regenerate.";
    echo PHP_SAPI === 'cli' ? $msg . "\n"
        : "<p style='font-family:monospace;color:#856404;background:#fff3cd;padding:1em;'>$msg</p>";
    exit(0);
}

$configPath = find_openssl_cnf();
$config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
if ($configPath) $config['config'] = $configPath;

$key = openssl_pkey_new($config);
if ($key === false) {
    $errs = [];
    while ($m = openssl_error_string()) $errs[] = $m;
    $detail = implode("\n", $errs) ?: '(check openssl extension is enabled)';
    if (!$configPath) $detail .= "\n\nHint: openssl.cnf not found — set OPENSSL_CONF env var.";
    if (PHP_SAPI === 'cli') fwrite(STDERR, "ERROR: Key generation failed.\n$detail\n");
    else echo "<pre style='color:red'>ERROR: Key generation failed.\n" . htmlspecialchars($detail) . "</pre>";
    exit(1);
}

$exportOpts = $configPath ? ['config' => $configPath] : [];
openssl_pkey_export($key, $privateKey, null, $exportOpts ?: null);
$details   = openssl_pkey_get_details($key);
$publicKey = $details['key'];

file_put_contents($privatePath, $privateKey);
file_put_contents($publicPath,  $publicKey);
@chmod($privatePath, 0600);
@chmod($publicPath,  0644);

$report = "Keys generated successfully!\n"
    . "  Private key : keys/private.pem  (chmod 600)\n"
    . "  Public  key : keys/public.pem   (chmod 644)\n"
    . "  OpenSSL cnf : " . ($configPath ?? '(compiled-in default)') . "\n\n"
    . "Next steps:\n"
    . "  1. Ensure backend/config/.env has JWT_PRIVATE_KEY_PATH and JWT_PUBLIC_KEY_PATH.\n"
    . "  2. DELETE this file — it must not be publicly accessible in production.";

if (PHP_SAPI === 'cli') echo $report . "\n";
else echo "<pre style='font-family:monospace;background:#d1e7dd;color:#0a3622;padding:1.2em;border-radius:6px;'>"
    . htmlspecialchars($report) . "</pre>"
    . "<p style='font-family:monospace;color:#842029;'>⚠ <strong>Delete this file after use.</strong></p>";
