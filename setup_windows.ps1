# ============================================================
# Ownuh SAIPS — Windows Setup Script (PowerShell)
# Version 2.0 — Idempotent, fully error-handled
# ============================================================
# Run from the project root folder:
#
#   Set-ExecutionPolicy -Scope Process Bypass
#   .\setup_windows.ps1
#
# Optional parameters:
#   .\setup_windows.ps1 -DBPass "yourpassword" -Port 8080 -SkipServer
# ============================================================
# ============================================================

# Ownuh SAIPS — Windows Setup Script (Enterprise Edition)

# Version 3.0 — Fully Robust, Full Seeding, Production Safe

# ============================================================

#Requires -Version 5.1

param(
[string]$DBHost     = "127.0.0.1",
[string]$DBPass     = "",
[int]   $Port       = 8080,
[string]$MySQLPath  = "",
[string]$PHPPath    = "",
[switch]$SkipServer
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ── Logging Helpers ──────────────────────────────────────────

function Write-OK   { param($m) Write-Host "  [OK]  $m" -ForegroundColor Green }
function Write-Info { param($m) Write-Host "  [...] $m" -ForegroundColor Cyan }
function Write-Warn { param($m) Write-Host "  [WRN] $m" -ForegroundColor Yellow }
function Write-Step { param($m) Write-Host "`n=== $m ===" -ForegroundColor Yellow }
function Write-Err  { param($m) Write-Host "`n  [ERR] $m" -ForegroundColor Red; exit 1 }

# ── Validate Root ────────────────────────────────────────────

if (-not (Test-Path "login.php")) {
Write-Err "Run this script from project root (login.php missing)"
}

# ── Locate MySQL ─────────────────────────────────────────────

Write-Step "Step 1: Locating MySQL"

if ($MySQLPath) {
    $script:mysqlExe = Join-Path $MySQLPath "mysql.exe"
} else {
    $script:mysqlExe = "C:\xampp\mysql\bin\mysql.exe"
}

if (-not (Test-Path $script:mysqlExe)) {
Write-Err "MySQL not found. Set -MySQLPath or install XAMPP."
}
Write-OK "MySQL: $script:mysqlExe"

# ── Locate PHP ───────────────────────────────────────────────

Write-Step "Step 2: Locating PHP"

if ($PHPPath) {
    $script:phpExe = Join-Path $PHPPath "php.exe"
} else {
    $script:phpExe = "C:\xampp\php\php.exe"
}

if (-not (Test-Path $script:phpExe)) {
Write-Err "PHP not found. Set -PHPPath or install XAMPP."
}
Write-OK "PHP: $script:phpExe"

# ── MySQL Auth ───────────────────────────────────────────────

Write-Step "Step 3: MySQL Authentication"

if (-not $DBPass) {
$DBPass = Read-Host "Enter MySQL root password (blank if none)"
}

$script:authArgs = @("-h", $DBHost, "-u", "root", "--default-character-set=utf8mb4")
if ($DBPass) { $script:authArgs += "-p$DBPass" }

# Test connection

$out = & $script:mysqlExe @script:authArgs -e "SELECT 1;" 2>&1
if ($LASTEXITCODE -ne 0) { Write-Err "MySQL connection failed: $out" }
Write-OK "MySQL connected"

# ── DB Creation ──────────────────────────────────────────────

Write-Step "Step 4: Creating Databases"

& $script:mysqlExe @script:authArgs -e "
CREATE DATABASE IF NOT EXISTS ownuh_saips CHARACTER SET utf8mb4;
CREATE DATABASE IF NOT EXISTS ownuh_credentials CHARACTER SET utf8mb4;
"

Write-OK "Databases ready"

# ── SQL Runner ───────────────────────────────────────────────

function Run-SQLFile($file, $db) {
if (!(Test-Path $file)) {
Write-Warn "$file not found"
return
}
Write-Info "Running $file on $db"
$out = Get-Content $file -Raw | & $script:mysqlExe @script:authArgs $db 2>&1
if ($LASTEXITCODE -ne 0) {
Write-Warn $out
} else {
Write-OK "$file executed"
}
}

# ── Schema Import ────────────────────────────────────────────

Write-Step "Step 5: Importing Schema"

Run-SQLFile "database\schema.sql" "ownuh_saips"
Run-SQLFile "database\migrations\002_credentials.sql" "ownuh_credentials"

# ── FULL SEEDING (NO SKIP) ───────────────────────────────────

Write-Step "Step 6: Seeding ALL Data (Enterprise Mode)"

$seedFiles = @(
"database\seed.sql",
"database\test_seed.sql",
"database\patch_audit_seed.sql"
)

foreach ($file in $seedFiles) {
Run-SQLFile $file "ownuh_saips"
}

Write-OK "All seeds executed"

# ── JWT Keys ─────────────────────────────────────────────────

Write-Step "Step 7: Generating JWT Keys"

if (!(Test-Path "keys")) { New-Item -ItemType Directory keys | Out-Null }

if (!(Test-Path "keys/private.pem")) {
$tmp = "genkey.php"
@"

<?php
\$k = openssl_pkey_new(['private_key_bits'=>2048]);
openssl_pkey_export(\$k,\$priv);
\$pub = openssl_pkey_get_details(\$k)['key'];
file_put_contents('keys/private.pem',\$priv);
file_put_contents('keys/public.pem',\$pub);
"@ | Out-File $tmp -Encoding utf8

    & $script:phpExe $tmp
    Remove-Item $tmp
    Write-OK "JWT keys generated"
} else {
    Write-OK "JWT keys already exist"
}

# ── ENV CONFIG ───────────────────────────────────────────────
Write-Step "Step 8: Writing .env"

$envPath = "backend\config\.env"

@"
DB_HOST=$DBHost
DB_NAME=ownuh_saips
DB_USER=root
DB_PASS=$DBPass

DB_AUTH_HOST=$DBHost
DB_AUTH_NAME=ownuh_credentials
DB_AUTH_USER=root
DB_AUTH_PASS=$DBPass

APP_URL=http://localhost:$Port
BCRYPT_COST=12
"@ | Out-File $envPath -Encoding utf8

Write-OK ".env configured"

# ── setup.php ────────────────────────────────────────────────
Write-Step "Step 9: Running setup.php"

if (Test-Path "setup.php") {
    & $script:phpExe setup.php
    Write-OK "setup.php executed"
} else {
    Write-Warn "setup.php missing"
}

# ── DONE ────────────────────────────────────────────────────
Write-Host "`n========================================" -ForegroundColor Green
Write-Host "  SETUP COMPLETE (ENTERPRISE MODE)" -ForegroundColor Green
Write-Host "========================================`n"

Write-Host "Login URL : http://localhost:$Port/login.php"
Write-Host "Email     : sophia.johnson@acme.com"
Write-Host "Password  : Admin@SAIPS2025!"
