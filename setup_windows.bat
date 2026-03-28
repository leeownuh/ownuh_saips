@echo off
setlocal

set SCRIPT_DIR=%~dp0
set PS1=%SCRIPT_DIR%setup_windows.ps1

if not exist "%PS1%" (
    echo [ERR] setup_windows.ps1 not found.
    exit /b 1
)

powershell -ExecutionPolicy Bypass -File "%PS1%" %*
exit /b %errorlevel%
