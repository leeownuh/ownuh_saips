<?php
/**
 * Ownuh SAIPS — Database Configuration
 * Credentials loaded from environment variables ONLY.
 * Never hardcode credentials in this file.
 */

// ── PDO options shared by all connections ─────────────────────────────────────
// SSL/CA options are only added in production (APP_ENV=production) to avoid
// connection failures on local XAMPP/Laragon setups that lack CA bundles.
$isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';

$basePdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Keep connection alive across long PHP processes / persistent connections
    PDO::ATTR_PERSISTENT         => false,
    // MySQL-specific: reconnect on "server has gone away" (2006)
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

if ($isProduction) {
    $caBundlePaths = [
        '/etc/ssl/certs/ca-certificates.crt',       // Debian/Ubuntu
        '/etc/pki/tls/certs/ca-bundle.crt',         // RHEL/CentOS
        '/usr/local/etc/openssl/cert.pem',           // macOS Homebrew
    ];
    foreach ($caBundlePaths as $ca) {
        if (file_exists($ca)) {
            $basePdoOptions[PDO::MYSQL_ATTR_SSL_CA]                  = $ca;
            $basePdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]  = true;
            break;
        }
    }
}

return [
    // Application database (users, sessions, audit, incidents)
    'app' => [
        'host'    => $_ENV['DB_HOST']   ?? '127.0.0.1',
        'port'    => (int)($_ENV['DB_PORT']   ?? 3306),
        'name'    => $_ENV['DB_NAME']   ?? 'ownuh_saips',
        'user'    => $_ENV['DB_USER']   ?? 'root',
        'pass'    => $_ENV['DB_PASS']   ?? '',
        'charset' => 'utf8mb4',
        'options' => $basePdoOptions,
    ],
    // Credentials database (password hashes only — isolated)
    'auth' => [
        'host'    => $_ENV['DB_AUTH_HOST'] ?? '127.0.0.1',
        'port'    => (int)($_ENV['DB_AUTH_PORT'] ?? 3306),
        'name'    => $_ENV['DB_AUTH_NAME'] ?? 'ownuh_credentials',
        'user'    => $_ENV['DB_AUTH_USER'] ?? 'root',
        'pass'    => $_ENV['DB_AUTH_PASS'] ?? '',
        'charset' => 'utf8mb4',
        'options' => $basePdoOptions,
    ],
    // Redis (session store + rate limiting)
    'redis' => [
        'host'   => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
        'port'   => (int)($_ENV['REDIS_PORT'] ?? 6379),
        'pass'   => $_ENV['REDIS_PASS'] ?? '',
        'db'     => 0,
        'prefix' => 'saips:',
    ],
];
