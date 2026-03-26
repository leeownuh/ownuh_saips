<?php
declare(strict_types=1);
/**
 * Ownuh SAIPS — Simple Logout
 * Clears the JWT cookie and session, then redirects to login.
 * CAP512 Unit II: Sessions, Cookies
 */

session_start();

// Destroy PHP session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}
session_destroy();

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
         || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

$cookieOpts = [
    'expires'  => time() - 86400,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',  // SECURITY FIX: was Lax
];

// Clear the JWT access cookie
setcookie('saips_access', '', $cookieOpts);

// Also clear any refresh token cookie if set
setcookie('saips_refresh', '', $cookieOpts);

// Redirect to login
header('Location: login.php');
exit;
