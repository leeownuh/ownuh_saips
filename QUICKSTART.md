# Ownuh SAIPS - Quick Start

## Windows (XAMPP or Laragon)

### 1. Install a local stack

- XAMPP: https://www.apachefriends.org/
- Laragon: https://laragon.org/

### 2. Place the project

```text
XAMPP   -> C:\xampp\htdocs\ownuh_saips_fixed
Laragon -> C:\laragon\www\ownuh_saips_fixed
```

### 3. Run setup

#### PowerShell

```powershell
cd C:\xampp\htdocs\ownuh_saips_fixed
Set-ExecutionPolicy -Scope Process Bypass
.\setup_windows.ps1
```

#### Batch

```bat
cd C:\xampp\htdocs\ownuh_saips_fixed
setup_windows.bat
```

#### Manual PHP

```bat
cd C:\xampp\htdocs\ownuh_saips_fixed
php setup.php
```

### 4. Start the server

Either:

```bash
php -S 0.0.0.0:8080
```

Or start Apache + MySQL from the XAMPP Control Panel.

### 5. Open the app

```text
# PHP built-in server
http://localhost:8080/login.php

# XAMPP / Apache
http://localhost/ownuh_saips_fixed/login.php
```

### 6. Log in

| Email | Password | Role |
|---|---|---|
| `sophia.johnson@acme.com` | `Admin@SAIPS2025!` | Super Admin |
| `marcus.chen@acme.com` | `Admin@SAIPS2025!` | Admin |

Change the default password immediately after first login.

---

## ngrok

### Built-in PHP server

```bash
ngrok http 8080
```

Open:

```text
https://xxxx.ngrok-free.app/login.php
```

### XAMPP / Apache

```bash
ngrok http 80
```

Open:

```text
https://xxxx.ngrok-free.app/ownuh_saips_fixed/login.php
```

### Update `backend/config/.env`

```env
APP_URL=https://xxxx.ngrok-free.app
TRUSTED_PROXY=any
COOKIE_SAMESITE=Lax
APP_TIMEZONE=Asia/Kolkata
APP_TIMEZONE_LABEL=IST
```

---

## Key Files

| File | Purpose |
|---|---|
| `login.php` | Sign-in form |
| `forgot-password.php` | Request reset link |
| `reset-password.php` | Token-based password reset page |
| `auth-create-password.php` | Authenticated password change page |
| `otp-verify.php` | MFA verification and bypass-token recovery |
| `users.php` | User management and MFA bypass issuance |
| `backend/bootstrap.php` | DB class, JWT helpers, session/security helpers |
| `backend/config/.env` | Environment config |

---

## If Something Goes Wrong

| Symptom | Fix |
|---|---|
| White page / maintenance page | Start MySQL from XAMPP |
| Login fails after setup | Re-run `php setup.php` |
| Reset link fails | Check `C:\xampp\apache\logs\error.log` and confirm `APP_URL` / path |
| Session not persisting through ngrok | Set `TRUSTED_PROXY=any` and `COOKIE_SAMESITE=Lax` |
| Password change says network error | Ensure the real page is `auth-create-password.php`, not the stale static mockup |
