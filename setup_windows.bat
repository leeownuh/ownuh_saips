@echo off
REM ============================================================
REM Ownuh SAIPS v1.1.0 — Windows Setup (Batch)
REM ============================================================
REM Prefer setup_windows.ps1 if you have PowerShell.
REM Usage: Double-click or run from project folder:
REM   cd "C:\path\to\ownuh_saips_fixed"
REM   setup_windows.bat
REM ============================================================

setlocal enabledelayedexpansion
title Ownuh SAIPS Setup

echo.
echo ============================================================
echo   Ownuh SAIPS v1.1.0 -- Windows Setup
echo ============================================================
echo.

REM ── Locate MySQL ──────────────────────────────────────────────
set MYSQL=
where mysql >nul 2>&1 && set MYSQL=mysql
if not defined MYSQL if exist "C:\xampp\mysql\bin\mysql.exe"       set MYSQL=C:\xampp\mysql\bin\mysql.exe
if not defined MYSQL if exist "C:\laragon\bin\mysql\mysql-8.0-winx64\bin\mysql.exe" set MYSQL=C:\laragon\bin\mysql\mysql-8.0-winx64\bin\mysql.exe
if not defined MYSQL if exist "C:\laragon\bin\mysql\mysql-8.4-winx64\bin\mysql.exe" set MYSQL=C:\laragon\bin\mysql\mysql-8.4-winx64\bin\mysql.exe
if not defined MYSQL if exist "C:\wamp64\bin\mysql\mysql8.0.31\bin\mysql.exe"       set MYSQL=C:\wamp64\bin\mysql\mysql8.0.31\bin\mysql.exe
if not defined MYSQL if exist "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" set MYSQL=C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe
if not defined MYSQL if exist "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" set MYSQL=C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe

if not defined MYSQL (
    echo [ERR] MySQL not found.
    echo       Install XAMPP: https://www.apachefriends.org/
    echo       or Laragon:    https://laragon.org/
    pause & exit /b 1
)
echo   MySQL : %MYSQL%

REM ── Locate PHP ────────────────────────────────────────────────
set PHP=
where php >nul 2>&1 && set PHP=php
if not defined PHP if exist "C:\xampp\php\php.exe"  set PHP=C:\xampp\php\php.exe
if not defined PHP if exist "C:\laragon\bin\php\php.exe" set PHP=C:\laragon\bin\php\php.exe
if not defined PHP if exist "C:\php\php.exe"         set PHP=C:\php\php.exe

if not defined PHP (
    echo [ERR] PHP not found. Install XAMPP or Laragon.
    pause & exit /b 1
)
echo   PHP   : %PHP%
echo.

REM ── MySQL password ─────────────────────────────────────────────
set /p DBPASS="  MySQL root password (press Enter if blank): "

REM ── Test connection ────────────────────────────────────────────
if "%DBPASS%"=="" (
    "%MYSQL%" -u root -e "SELECT 1;" >nul 2>&1
) else (
    "%MYSQL%" -u root -p%DBPASS% -e "SELECT 1;" >nul 2>&1
)
if %errorlevel% neq 0 (
    echo [ERR] Cannot connect to MySQL. Is it running? Check your password.
    pause & exit /b 1
)
echo [OK] MySQL connected

REM ── Create databases ───────────────────────────────────────────
echo [1/6] Creating databases...
if "%DBPASS%"=="" (
    "%MYSQL%" -u root -e "CREATE DATABASE IF NOT EXISTS ownuh_saips CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS ownuh_credentials CHARACTER SET utf8mb4;"
) else (
    "%MYSQL%" -u root -p%DBPASS% -e "CREATE DATABASE IF NOT EXISTS ownuh_saips CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS ownuh_credentials CHARACTER SET utf8mb4;"
)
if %errorlevel% neq 0 ( echo [ERR] Failed to create databases & pause & exit /b 1 )
echo [OK] Databases created

REM ── Import schemas ─────────────────────────────────────────────
echo [2/6] Importing schema.sql...
if "%DBPASS%"=="" (
    powershell -Command "Get-Content 'database\schema.sql' -Raw | & '%MYSQL%' -u root ownuh_saips"
) else (
    powershell -Command "Get-Content 'database\schema.sql' -Raw | & '%MYSQL%' -u root -p%DBPASS% ownuh_saips"
)
if %errorlevel% neq 0 ( echo [ERR] Schema import failed & pause & exit /b 1 )
echo [OK] Schema imported

echo [3/6] Importing credentials schema...
if "%DBPASS%"=="" (
    powershell -Command "Get-Content 'database\migrations\002_credentials.sql' -Raw | & '%MYSQL%' -u root"
) else (
    powershell -Command "Get-Content 'database\migrations\002_credentials.sql' -Raw | & '%MYSQL%' -u root -p%DBPASS%"
)
if %errorlevel% neq 0 ( echo [ERR] Credentials schema failed & pause & exit /b 1 )
echo [OK] Credentials schema imported

echo [4/6] Importing seed data...
if "%DBPASS%"=="" (
    powershell -Command "Get-Content 'database\seed.sql' -Raw | & '%MYSQL%' -u root ownuh_saips" 2>nul
) else (
    powershell -Command "Get-Content 'database\seed.sql' -Raw | & '%MYSQL%' -u root -p%DBPASS% ownuh_saips" 2>nul
)
echo [OK] Seed data applied

REM ── Generate JWT keys via PHP ──────────────────────────────────
echo [5/6] Generating JWT keys and writing .env...
if not exist "keys" mkdir keys

"%PHP%" -r "if(!file_exists('keys/private.pem')){$k=openssl_pkey_new(['digest_alg'=>'sha256','private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);openssl_pkey_export($k,$p);file_put_contents('keys/private.pem',$p);file_put_contents('keys/public.pem',openssl_pkey_get_details($k)['key']);echo 'Keys generated';}else{echo 'Keys exist';}"
echo.

REM ── Write .env ────────────────────────────────────────────────
(
echo # Generated by setup_windows.bat
echo DB_HOST=127.0.0.1
echo DB_PORT=3306
echo DB_NAME=ownuh_saips
echo DB_USER=root
echo DB_PASS=%DBPASS%
echo.
echo DB_AUTH_HOST=127.0.0.1
echo DB_AUTH_PORT=3306
echo DB_AUTH_USER=root
echo DB_AUTH_PASS=%DBPASS%
echo.
echo REDIS_HOST=127.0.0.1
echo REDIS_PORT=6379
echo REDIS_PASS=
echo.
echo JWT_PRIVATE_KEY_PATH=keys/private.pem
echo JWT_PUBLIC_KEY_PATH=keys/public.pem
echo JWT_ISSUER=ownuh-saips
echo JWT_ACCESS_TTL=900
echo JWT_REFRESH_TTL=604800
echo JWT_ADMIN_REFRESH_TTL=28800
echo.
echo APP_ENV=development
echo APP_URL=http://localhost:8080
echo BCRYPT_COST=12
echo.
echo MFA_TOTP_ISSUER=OwnuhSAIPS
echo MFA_EMAIL_OTP_TTL=600
echo MFA_EMAIL_OTP_RATE=5
) > backend\config\.env
echo [OK] .env written

REM ── Run setup.php ─────────────────────────────────────────────
echo [6/6] Running setup.php (hashing passwords, ~10 seconds)...
"%PHP%" setup.php
echo [OK] Setup complete

echo.
echo ============================================================
echo   Ready!
echo.
echo   Start server:  %PHP% -S 0.0.0.0:8080
echo   Open browser:  http://localhost:8080/login.php
echo.
echo   Email    : sophia.johnson@acme.com
echo   Password : Admin@SAIPS2025!
echo.
echo   ngrok (separate terminal): ngrok http 8080
echo.
echo   IMPORTANT: Change password after first login!
echo   IMPORTANT: Delete setup.php before going public!
echo ============================================================
echo.

set /p STARTSERVER="  Start PHP server on port 8080 now? [Y/n]: "
if /i not "%STARTSERVER%"=="n" (
    echo.
    echo   Server running at http://localhost:8080/login.php
    echo   Press Ctrl+C to stop.
    echo.
    "%PHP%" -S 0.0.0.0:8080
)
endlocal
