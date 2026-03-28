<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function fileText(string $path): string
{
    return file_exists($path) ? (string)file_get_contents($path) : '';
}

$schema = fileText($root . '/database/schema.sql');
$selfReset = fileText($root . '/backend/api/auth/password-reset.php');
$confirmReset = fileText($root . '/backend/api/auth/reset-confirm.php');
$adminReset = fileText($root . '/backend/api/users/password-reset.php');
$setupPs1 = fileText($root . '/setup_windows.ps1');
$installSh = fileText($root . '/install.sh');
$readme = fileText($root . '/README.md');
$quickstart = fileText($root . '/QUICKSTART.md');
$deployment = fileText($root . '/DEPLOYMENT.md');
$setupPhp = fileText($root . '/setup.php');
$authCreateHtml = fileText($root . '/auth-create-password.html');
$loginHtml = fileText($root . '/auth-signin.html');
$forgotHtml = fileText($root . '/auth-reset-password.html');
$otpHtml = fileText($root . '/auth-two-step-verify.html');
$auth401Html = fileText($root . '/auth-401.html');
$auth404Html = fileText($root . '/auth-404.html');
$auth500Html = fileText($root . '/auth-500.html');
$authSignupHtml = fileText($root . '/auth-signup.html');
$authLockscreenHtml = fileText($root . '/auth-lockscreen.html');
$maintenanceHtml = fileText($root . '/under-maintenance.html');
$indexHtml = fileText($root . '/index.html');
$usersListHtml = fileText($root . '/users-list.html');
$auditHtml = fileText($root . '/audit-log.html');
$migration005 = fileText($root . '/database/migrations/005_add_executive_report_storage.sql');

assertTrue(str_contains($schema, 'CREATE TABLE IF NOT EXISTS password_resets'), 'schema.sql must define password_resets.', $failures);
assertTrue(!str_contains($schema, 'CREATE TABLE IF NOT EXISTS password_reset_tokens'), 'schema.sql must not define password_reset_tokens.', $failures);
assertTrue(str_contains($schema, 'CREATE TABLE IF NOT EXISTS executive_reports'), 'schema.sql must define executive_reports.', $failures);

assertTrue(str_contains($selfReset, 'password_resets'), 'Self-service password reset must use password_resets.', $failures);
assertTrue(str_contains($confirmReset, 'password_resets'), 'Reset confirmation must use password_resets.', $failures);
assertTrue(str_contains($adminReset, 'password_resets'), 'Admin password reset must use password_resets.', $failures);
assertTrue(!str_contains($adminReset, 'password_reset_tokens'), 'Admin password reset must no longer use password_reset_tokens.', $failures);

assertTrue(str_contains($setupPs1, 'portfolio_seed.sql'), 'Windows setup must default to portfolio_seed.sql.', $failures);
assertTrue(str_contains($installSh, 'portfolio_seed.sql'), 'Linux setup must default to portfolio_seed.sql.', $failures);
assertTrue(str_contains($setupPs1, 'lucia.alvarez@ownuh-saips.com'), 'Windows setup must print the primary demo account.', $failures);
assertTrue(str_contains($installSh, 'lucia.alvarez@ownuh-saips.com'), 'Linux setup must print the primary demo account.', $failures);

assertTrue(str_contains($readme, 'setup_windows.ps1'), 'README must document Windows setup script.', $failures);
assertTrue(str_contains($quickstart, 'install.sh'), 'QUICKSTART must document Linux setup script.', $failures);
assertTrue(str_contains($deployment, 'setup_windows.ps1'), 'DEPLOYMENT must document scripted setup.', $failures);

assertTrue(str_contains($setupPhp, 'setup.php is retired.'), 'setup.php must be clearly retired.', $failures);
assertTrue(str_contains($authCreateHtml, 'auth-create-password.php'), 'auth-create-password.html must redirect to auth-create-password.php.', $failures);
assertTrue(str_contains($loginHtml, 'login.php'), 'auth-signin.html must redirect to login.php.', $failures);
assertTrue(str_contains($forgotHtml, 'forgot-password.php'), 'auth-reset-password.html must redirect to forgot-password.php.', $failures);
assertTrue(str_contains($otpHtml, 'otp-verify.php'), 'auth-two-step-verify.html must redirect to otp-verify.php.', $failures);
assertTrue(str_contains($auth401Html, 'auth-401.php'), 'auth-401.html must redirect to auth-401.php.', $failures);
assertTrue(str_contains($auth404Html, 'auth-404.php'), 'auth-404.html must redirect to auth-404.php.', $failures);
assertTrue(str_contains($auth500Html, 'auth-500.php'), 'auth-500.html must redirect to auth-500.php.', $failures);
assertTrue(str_contains($authSignupHtml, 'auth-signup.php'), 'auth-signup.html must redirect to auth-signup.php.', $failures);
assertTrue(str_contains($authLockscreenHtml, 'auth-lockscreen.php'), 'auth-lockscreen.html must redirect to auth-lockscreen.php.', $failures);
assertTrue(str_contains($maintenanceHtml, 'under-maintenance.php'), 'under-maintenance.html must redirect to under-maintenance.php.', $failures);
assertTrue(str_contains($indexHtml, 'login.php'), 'index.html must redirect to login.php.', $failures);
assertTrue(str_contains($usersListHtml, 'users.php'), 'users-list.html must redirect to users.php.', $failures);
assertTrue(str_contains($auditHtml, 'audit-log.php'), 'audit-log.html must redirect to audit-log.php.', $failures);
assertTrue(str_contains($migration005, 'executive_reports'), 'Migration 005 must create executive_reports.', $failures);

if ($failures !== []) {
    fwrite(STDERR, "Repo guard checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "All repo guard checks passed.\n");
