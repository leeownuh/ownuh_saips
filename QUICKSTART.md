# Ownuh SAIPS — Quick Start

## Windows (XAMPP or Laragon)

### Step 1 — Install a local server stack
Pick one:
- **XAMPP** (recommended): https://www.apachefriends.org/ — installs Apache + PHP + MySQL
- **Laragon**: https://laragon.org/ — lightweight, auto-detects PHP/MySQL

### Step 2 — Place the project files
Copy the `ownuh_saips_fixed` folder into your web root:
- XAMPP → `C:\xampp\htdocs\ownuh_saips_fixed\`
- Laragon → `C:\laragon\www\ownuh_saips_fixed\`

### Step 3 — Run the setup script

**Option A — PowerShell (recommended)**
```powershell
cd C:\xampp\htdocs\ownuh_saips_fixed
Set-ExecutionPolicy -Scope Process Bypass
.\setup_windows.ps1
```
The script will ask for your MySQL root password (blank if XAMPP default), then handle everything automatically — databases, schema, seed data, JWT keys, `.env`, and password hashing.

**Option B — Batch file**
```
cd C:\xampp\htdocs\ownuh_saips_fixed
setup_windows.bat
```

**Option C — Manual (PHP CLI)**
```
cd C:\xampp\htdocs\ownuh_saips_fixed
php setup.php
```

### Step 4 — Start the server

The setup script offers to start PHP's built-in server automatically.
Or start it manually:
```
php -S 0.0.0.0:8080
```

### Step 5 — Open in browser
```
http://localhost:8080/login.php
```

### Step 6 — Log in
| Email | Password | Role |
|-------|----------|------|
| `sophia.johnson@acme.com` | `Admin@SAIPS2025!` | Super Admin |
| `marcus.chen@acme.com` | `Admin@SAIPS2025!` | Admin |

> ⚠ **Change the password immediately after first login.**

---

## ngrok (Share Publicly)

```
# In a second terminal / PowerShell window:
ngrok http 8080
```
Copy the `https://xxxx.ngrok-free.app` URL — it works immediately.

---

## Linux / macOS

```bash
bash install.sh          # prompts for MySQL creds, does everything
php -S 0.0.0.0:8080
```

---

## If Something Goes Wrong

| Symptom | Fix |
|---------|-----|
| White page / "under maintenance" | MySQL not running — start it in XAMPP Control Panel |
| "Invalid email or password" after setup | Re-run `php setup.php` — bcrypt hashes may not have been written |
| Cookie/JWT error in logs | Confirm `keys/private.pem` and `keys/public.pem` exist |
| 403 on `.env` | Normal — `.htaccess` blocks it intentionally |
| Session not persisting | PHP session directory must be writable |

---

## Key Files

| File | Purpose |
|------|---------|
| `login.php` | Sign-in form |
| `logout.php` | Clears JWT cookie and session |
| `dashboard.php` | Live dashboard (requires admin login) |
| `otp-verify.php` | MFA / OTP verification |
| `setup.php` | First-run setup (delete after use) |
| `backend/bootstrap.php` | DB class, JWT functions, helpers |
| `backend/config/.env` | Credentials (auto-generated, git-ignored) |
| `keys/private.pem` | JWT signing key (auto-generated, git-ignored) |
