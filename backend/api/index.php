<?php
/**
 * Ownuh SAIPS — API Router
 * Entry point for all /api/v1/* requests.
 * Routes requests to the appropriate handler.
 *
 * Nginx rewrites: /api/v1/* → /backend/api/index.php
 */

declare(strict_types=1);

// Security headers on every API response
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Load environment
$envFile = __DIR__ . '/../config/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
        putenv(trim($key) . '=' . trim($val));
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$uri = preg_replace('#^/api/v1#', '', $uri);

// ── Route table ───────────────────────────────────────────────────────────────
$routes = [
    // Auth
    '#^/auth/login$#'                    => __DIR__ . '/auth/login.php',
    '#^/auth/logout$#'                   => __DIR__ . '/auth/logout.php',
    '#^/auth/token/refresh$#'            => __DIR__ . '/auth/refresh.php',
    '#^/auth/mfa/verify$#'               => __DIR__ . '/auth/mfa-verify.php',
    '#^/auth/mfa/setup$#'                => __DIR__ . '/auth/mfa-setup.php',
    '#^/auth/mfa/bypass$#'               => __DIR__ . '/auth/mfa-bypass.php',
    '#^/auth/password/reset$#'           => __DIR__ . '/auth/password-reset.php',
    '#^/auth/password/change$#'          => __DIR__ . '/auth/password-change.php',

    // Sessions
    '#^/sessions(/.*)?$#'                => __DIR__ . '/sessions/list.php',

    // Audit
    '#^/audit/log(/.*)?$#'              => __DIR__ . '/audit/log.php',

    // Incidents
    '#^/incidents(/.*)?$#'              => __DIR__ . '/incidents/list.php',

    // IPS
    '#^/ips/blocked(/.*)?$#'            => __DIR__ . '/ips/blocked-ips.php',
    '#^/ips/geo-rules(/.*)?$#'          => __DIR__ . '/ips/geo-rules.php',
    '#^/ips/rate-limits(/.*)?$#'        => __DIR__ . '/ips/rate-config.php',

    // Users
    '#^/users(/.*)?$#'                  => __DIR__ . '/users/list.php',
];

foreach ($routes as $pattern => $handler) {
    if (preg_match($pattern, $uri)) {
        if (!file_exists($handler)) {
            http_response_code(501);
            echo json_encode(['status' => 'error', 'code' => 'NOT_IMPLEMENTED', 'message' => 'Endpoint handler not yet implemented.']);
            exit;
        }
        require $handler;
        exit;
    }
}

// 404
http_response_code(404);
echo json_encode([
    'status'  => 'error',
    'code'    => 'NOT_FOUND',
    'message' => "Endpoint not found: {$uri}",
]);
