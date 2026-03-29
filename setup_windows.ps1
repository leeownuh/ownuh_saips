#Requires -Version 5.1
param(
    [string]$DBHost = "127.0.0.1",
    [int]$DBPort = 3306,
    [string]$DBUser = "root",
    [string]$DBPass = "",
    [string]$AppUrl = "http://localhost/ownuh_saips_fixed",
    [ValidateSet("demo", "production")]
    [string]$Profile = "demo",
    [string]$AppDBUser = "saips_app",
    [string]$AppDBPass = "",
    [string]$AuthDBUser = "saips_auth",
    [string]$AuthDBPass = "",
    [ValidateSet("portfolio", "dev", "test")]
    [string]$Seed = "portfolio",
    [string]$MySQLExe = "",
    [string]$PHPExe = "",
    [switch]$SkipEnv,
    [switch]$SkipKeys
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Write-Step([string]$Message) { Write-Host "`n=== $Message ===" -ForegroundColor Yellow }
function Write-Info([string]$Message) { Write-Host "  [...] $Message" -ForegroundColor Cyan }
function Write-OK([string]$Message)   { Write-Host "  [OK]  $Message" -ForegroundColor Green }
function Fail([string]$Message)       { Write-Host "`n  [ERR] $Message" -ForegroundColor Red; exit 1 }
function New-RandomHexSecret([int]$Bytes = 24) {
    -join ((1..$Bytes) | ForEach-Object { '{0:x2}' -f (Get-Random -Maximum 256) })
}

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ProjectRoot

if (-not (Test-Path (Join-Path $ProjectRoot "login.php"))) {
    Fail "Run this script from the project root."
}

function Resolve-Executable {
    param(
        [string]$Preferred,
        [string[]]$Candidates,
        [string]$Label
    )

    if ($Preferred) {
        if (Test-Path $Preferred) { return $Preferred }
        Fail "$Label not found at $Preferred"
    }

    foreach ($candidate in $Candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    Fail "$Label not found. Install XAMPP or pass an explicit path."
}

function New-AuthArgs {
    $args = @("-h", $DBHost, "-P", $DBPort.ToString(), "-u", $DBUser, "--default-character-set=utf8mb4")
    if ($DBPass -ne "") {
        $args += "-p$DBPass"
    }
    return ,$args
}

function Invoke-MySql {
    param(
        [string]$Sql,
        [string]$Database = ""
    )

    $args = New-AuthArgs
    if ($Database) {
        $args += $Database
    }

    $Sql | & $script:MySqlCli @args
    if ($LASTEXITCODE -ne 0) {
        Fail "MySQL command failed."
    }
}

function Invoke-MySqlFile {
    param(
        [string]$Path
    )

    if (-not (Test-Path $Path)) {
        Fail "Missing SQL file: $Path"
    }

    Write-Info "Importing $(Split-Path $Path -Leaf)"
    Get-Content $Path -Raw | & $script:MySqlCli @(New-AuthArgs)
    if ($LASTEXITCODE -ne 0) {
        Fail "Import failed for $Path"
    }
}

function Get-SeedPath {
    switch ($Seed) {
        "portfolio" { return Join-Path $ProjectRoot "database\portfolio_seed.sql" }
        "dev"       { return Join-Path $ProjectRoot "database\seed.sql" }
        "test"      { return Join-Path $ProjectRoot "database\test_seed.sql" }
        default     { Fail "Unsupported seed selection: $Seed" }
    }
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Green
Write-Host "  Ownuh SAIPS Windows Setup" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green

Write-Step "Locating dependencies"
$script:MySqlCli = Resolve-Executable -Preferred $MySQLExe -Candidates @(
    "C:\xampp\mysql\bin\mysql.exe",
    "C:\laragon\bin\mysql\mysql-8.0-winx64\bin\mysql.exe",
    "C:\laragon\bin\mysql\mysql-8.4-winx64\bin\mysql.exe"
) -Label "MySQL"
$script:PhpCli = Resolve-Executable -Preferred $PHPExe -Candidates @(
    "C:\xampp\php\php.exe",
    "C:\laragon\bin\php\php.exe"
) -Label "PHP"
Write-OK "MySQL: $script:MySqlCli"
Write-OK "PHP: $script:PhpCli"

Write-Step "Testing MySQL connection"
if ($DBPass -eq "") {
    $DBPass = Read-Host "Enter MySQL password for $DBUser at $DBHost (press Enter if blank)"
}
& $script:MySqlCli @(New-AuthArgs) -e "SELECT 1;" | Out-Null
if ($LASTEXITCODE -ne 0) {
    Fail "Could not connect to MySQL with the provided credentials."
}
Write-OK "MySQL connection verified"

Write-Step "Creating databases"
& $script:MySqlCli @(New-AuthArgs) -e @"
CREATE DATABASE IF NOT EXISTS ownuh_saips CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS ownuh_credentials CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"@
if ($LASTEXITCODE -ne 0) {
    Fail "Failed to create databases."
}
Write-OK "Databases ready"

Write-Step "Importing schema and seed"
Invoke-MySqlFile -Path (Join-Path $ProjectRoot "database\schema.sql")
Invoke-MySqlFile -Path (Join-Path $ProjectRoot "database\migrations\002_credentials.sql")
Invoke-MySqlFile -Path (Join-Path $ProjectRoot "database\migrations\003_password_resets_unify.sql")
Invoke-MySqlFile -Path (Get-SeedPath)
Write-OK "Database import complete using '$Seed' seed"

if ($Profile -eq "production") {
    Write-Step "Creating least-privilege database users"

    if ([string]::IsNullOrWhiteSpace($AppDBPass)) {
        $AppDBPass = New-RandomHexSecret
    }
    if ([string]::IsNullOrWhiteSpace($AuthDBPass)) {
        $AuthDBPass = New-RandomHexSecret
    }

    $safeAppPass  = $AppDBPass.Replace("'", "''")
    $safeAuthPass = $AuthDBPass.Replace("'", "''")

    & $script:MySqlCli @(New-AuthArgs) -e @"
CREATE USER IF NOT EXISTS '$AppDBUser'@'127.0.0.1' IDENTIFIED BY '$safeAppPass';
ALTER USER '$AppDBUser'@'127.0.0.1' IDENTIFIED BY '$safeAppPass';
CREATE USER IF NOT EXISTS '$AppDBUser'@'localhost' IDENTIFIED BY '$safeAppPass';
ALTER USER '$AppDBUser'@'localhost' IDENTIFIED BY '$safeAppPass';
CREATE USER IF NOT EXISTS '$AuthDBUser'@'127.0.0.1' IDENTIFIED BY '$safeAuthPass';
ALTER USER '$AuthDBUser'@'127.0.0.1' IDENTIFIED BY '$safeAuthPass';
CREATE USER IF NOT EXISTS '$AuthDBUser'@'localhost' IDENTIFIED BY '$safeAuthPass';
ALTER USER '$AuthDBUser'@'localhost' IDENTIFIED BY '$safeAuthPass';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON ownuh_saips.* TO '$AppDBUser'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON ownuh_saips.* TO '$AppDBUser'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ownuh_credentials.* TO '$AuthDBUser'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON ownuh_credentials.* TO '$AuthDBUser'@'localhost';
FLUSH PRIVILEGES;
"@
    if ($LASTEXITCODE -ne 0) {
        Fail "Failed to create least-privilege users for production profile."
    }
    Write-OK "Least-privilege users created for production profile"
}

Write-Step "Generating JWT keys"
$KeysDir = Join-Path $ProjectRoot "keys"
if (-not (Test-Path $KeysDir)) {
    New-Item -ItemType Directory -Path $KeysDir | Out-Null
}

if (-not $SkipKeys) {
    if (-not (Test-Path (Join-Path $KeysDir "private.pem")) -or -not (Test-Path (Join-Path $KeysDir "public.pem"))) {
        $keyScript = @'
<?php
$config = [
    'digest_alg' => 'sha256',
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];
$key = openssl_pkey_new($config);
openssl_pkey_export($key, $private);
$public = openssl_pkey_get_details($key)['key'];
file_put_contents(__DIR__ . '/keys/private.pem', $private);
file_put_contents(__DIR__ . '/keys/public.pem', $public);
'@
        $temp = Join-Path $ProjectRoot "generate_setup_keys.php"
        Set-Content -Path $temp -Value $keyScript -Encoding ASCII
        & $script:PhpCli $temp
        Remove-Item $temp -Force
        if ($LASTEXITCODE -ne 0) {
            Fail "JWT key generation failed."
        }
        Write-OK "JWT keys generated"
    } else {
        Write-OK "JWT keys already exist"
    }
} else {
    Write-Info "Skipping key generation"
}

if (-not $SkipEnv) {
    Write-Step "Writing backend/config/.env"
    $envPath = Join-Path $ProjectRoot "backend\config\.env"
    $isTunnelUrl = $AppUrl -match 'ngrok|localhost\.run|trycloudflare|\.tunnel'
    $envDbUser = if ($Profile -eq "production") { $AppDBUser } else { $DBUser }
    $envDbPass = if ($Profile -eq "production") { $AppDBPass } else { $DBPass }
    $envAuthUser = if ($Profile -eq "production") { $AuthDBUser } else { $DBUser }
    $envAuthPass = if ($Profile -eq "production") { $AuthDBPass } else { $DBPass }
    $envAppMode = if ($Profile -eq "production") { "production" } else { "development" }
    $envBcrypt = if ($Profile -eq "production") { 14 } else { 12 }
    $envTrustedProxy = if ($Profile -eq "production" -and $isTunnelUrl) { "any" } else { "" }
    $envCookieSameSite = if ($Profile -eq "production" -and $isTunnelUrl) { "Lax" } elseif ($Profile -eq "production") { "Strict" } else { "" }
    @"
DB_HOST=$DBHost
DB_PORT=$DBPort
DB_NAME=ownuh_saips
DB_USER=$envDbUser
DB_PASS=$envDbPass

DB_AUTH_HOST=$DBHost
DB_AUTH_PORT=$DBPort
DB_AUTH_NAME=ownuh_credentials
DB_AUTH_USER=$envAuthUser
DB_AUTH_PASS=$envAuthPass

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASS=

JWT_PRIVATE_KEY_PATH=keys/private.pem
JWT_PUBLIC_KEY_PATH=keys/public.pem
JWT_ISSUER=ownuh-saips
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=604800
JWT_ADMIN_REFRESH_TTL=28800

APP_ENV=$envAppMode
APP_URL=$AppUrl
APP_TIMEZONE=Asia/Kolkata
APP_TIMEZONE_LABEL=IST

BCRYPT_COST=$envBcrypt
TRUSTED_PROXY=$envTrustedProxy
COOKIE_SAMESITE=$envCookieSameSite

MFA_TOTP_ISSUER=OwnuhSAIPS
MFA_EMAIL_OTP_TTL=600
MFA_EMAIL_OTP_RATE=5
"@ | Set-Content -Path $envPath -Encoding ASCII
    Write-OK ".env written"
}

Write-Step "Verifying portfolio counts"
$summarySql = @"
SELECT COUNT(*) AS users FROM ownuh_saips.users;
SELECT COUNT(*) AS sessions FROM ownuh_saips.sessions WHERE invalidated_at IS NULL AND expires_at > NOW();
SELECT COUNT(*) AS incidents FROM ownuh_saips.incidents;
SELECT COUNT(*) AS audit_entries FROM ownuh_saips.audit_log;
"@
& $script:MySqlCli @(New-AuthArgs) -e $summarySql
if ($LASTEXITCODE -ne 0) {
    Fail "Verification queries failed."
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Green
Write-Host "  Setup complete" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Seed mode : $Seed"
Write-Host "Profile   : $Profile"
Write-Host "App URL   : $AppUrl"
Write-Host "Login URL : $AppUrl/login.php"
Write-Host ""
Write-Host "Primary demo account:"
Write-Host "  Email    : lucia.alvarez@ownuh-saips.com"
Write-Host "  Password : Admin@SAIPS2025!"
Write-Host ""
Write-Host "Windows/XAMPP note:"
Write-Host "  Put the project under htdocs and open /ownuh_saips_fixed/login.php"
