<?php
/**
 * Ownuh SAIPS — Database Configuration
 * Credentials loaded from environment variables ONLY.
 * Never hardcode credentials in this file.
 */

return [
    // Application database (users, sessions, audit, incidents)
    'app' => [
        'host'     => $_ENV['DB_HOST']   ?? '127.0.0.1',
        'port'     => $_ENV['DB_PORT']   ?? 3306,
        'name'     => $_ENV['DB_NAME']   ?? 'ownuh_saips',
        'user'     => $_ENV['DB_USER']   ?? 'saips_app',  // NEVER use root in production
        'pass'     => $_ENV['DB_PASS']   ?? '',
        'charset'  => 'utf8mb4',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Enable SSL in production:
            // PDO::MYSQL_ATTR_SSL_CA              => '/etc/ssl/certs/ca-certificates.crt',
            // PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
        ],
    ],
    // Credentials database (password hashes only — isolated)
    'auth' => [
        'host'     => $_ENV['DB_AUTH_HOST'] ?? '127.0.0.1',
        'port'     => $_ENV['DB_AUTH_PORT'] ?? 3306,
        'name'     => 'ownuh_credentials',
        'user'     => $_ENV['DB_AUTH_USER'] ?? 'saips_auth', // NEVER use root in production
        'pass'     => $_ENV['DB_AUTH_PASS'] ?? '',
        'charset'  => 'utf8mb4',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ],
    // Redis (session store + rate limiting)
    'redis' => [
        'host'     => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
        'port'     => $_ENV['REDIS_PORT'] ?? 6379,
        'pass'     => $_ENV['REDIS_PASS'] ?? '',
        'db'       => 0,
        'prefix'   => 'saips:',
    ],
];
