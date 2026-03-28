# Ownuh SAIPS - Quick Start

<p align="center">
  <img src="docs/media/saips-mark.svg" alt="Ownuh SAIPS mark" width="72" height="72">
</p>

This quick start is built for momentum. If you want a clean local demo fast, follow the Windows path, log in with the seeded admin account, and head straight to the dashboard and compliance screens.

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

Default behavior:

- creates both MySQL databases
- imports `database/schema.sql`
- imports `database/migrations/002_credentials.sql`
- imports `database/portfolio_seed.sql`
- generates JWT keys
- writes `backend/config/.env`
- leaves the app ready for AI executive reporting once an OpenAI key is added

#### Batch

```bat
cd C:\xampp\htdocs\ownuh_saips_fixed
setup_windows.bat
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
| `lucia.alvarez@ownuh-saips.com` | `Admin@SAIPS2025!` | Admin |
| `sophia.johnson@ownuh-saips.com` | `Admin@SAIPS2025!` | Super Admin |
| `marcus.chen@ownuh-saips.com` | `Admin@SAIPS2025!` | Admin |

Change the default password immediately after first login.

`lucia.alvarez@ownuh-saips.com` is the best primary demo login because the portfolio dataset is shaped for a clean admin walkthrough.

### 6.1 Best first click-through

If you want the fastest "this looks like a real product" walkthrough, open these next:

1. `dashboard.php` for the SOC-style overview
2. `ips-brute-force.php` for defensive visibility
3. `settings-compliance.php` for AI executive reporting and export
4. `settings-alert-rules.php` for live notification rules

### 7. Optional: turn on AI executive reporting

Add these values to `backend/config/.env` if you want the compliance screen to use a live OpenAI-compatible model instead of the built-in fallback summary:

```env
OPENAI_API_KEY=your_key_here
OPENAI_MODEL=gpt-4o-mini
```

Without `OPENAI_API_KEY`, the executive report still works, but it uses a deterministic local summary instead of a live model call.

If you want a free-tier friendly OpenAI-compatible provider such as Groq, use:

```env
OPENAI_API_KEY=your_groq_key_here
OPENAI_BASE_URL=https://api.groq.com/openai/v1
OPENAI_MODEL=openai/gpt-oss-20b
```

---

## Linux

```bash
git clone https://github.com/YOUR_USERNAME/ownuh-saips.git
cd ownuh-saips
bash install.sh
```

Default behavior:

- creates both MySQL databases
- imports schema plus credentials schema
- imports `database/portfolio_seed.sql`
- generates JWT keys
- writes `backend/config/.env`

Then start the app with:

```bash
php -S 0.0.0.0:8080
```

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
| `settings-compliance.php` | Compliance dashboard with AI executive reporting |
| `executive-report-export.php` | Download executive report as HTML or PDF |
| `backend/scripts/send-weekly-executive-report.php` | Weekly admin executive-report email job |
| `backend/Services/AlertDispatcherService.php` | Live alert-rule dispatcher for email, webhook, and Slack-style channels |
| `auth-signup.php` | Shared-shell signup experience using the live registration API |
| `auth-401.php` / `auth-404.php` / `auth-500.php` | Shared-shell error pages |
| `backend/bootstrap.php` | DB class, JWT helpers, session/security helpers |
| `backend/config/.env` | Environment config |

---

## If Something Goes Wrong

| Symptom | Fix |
|---|---|
| White page / maintenance page | Start MySQL from XAMPP |
| Login fails after setup | Re-run `setup_windows.ps1` or `install.sh` |
| Reset link fails | Check `C:\xampp\apache\logs\error.log` and confirm `APP_URL` / path |
| Session not persisting through ngrok | Set `TRUSTED_PROXY=any` and `COOKIE_SAMESITE=Lax` |
| Password change says network error | Ensure the real page is `auth-create-password.php`, not the stale static mockup |
| AI executive report falls back to local summary | Add `OPENAI_API_KEY` to `backend/config/.env` |
| Weekly executive report sends too often or without attachment | Review cadence and attachment settings on `settings-compliance.php` |
| Alert rule exists but no email arrives | Confirm the event code is one of the wired dispatcher events and the recipient address is valid |

---

## GitHub Demo Highlights

- AI executive posture reports turn live compliance, incident, and IPS metrics into leadership-ready summaries
- Executive reports can be exported as HTML or PDF and mailed automatically on a weekly cadence
- Alert rules now back real event-driven notifications for lockouts, blocked IPs, repeated failures, and incident activity
- The app keeps a report history so manual generation, export, and scheduled email runs are visible in the UI
- The overall flow feels cohesive: auth, IPS, compliance, alerts, and reporting all share the same product language instead of looking like disconnected class exercises
