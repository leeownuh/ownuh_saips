# Ownuh SAIPS — Secure Authentication & Intrusion Prevention System

**Version:** 1.1.0 | **Classification:** CONFIDENTIAL | **Effective:** March 2026

> **CAP512 — PHP & MySQL for Dynamic Web Applications**  
> BSc (Hons) Computer Science & Cyber Security · Lovely Professional University · 2026

---

## Overview

A full-stack cybersecurity admin dashboard built with **PHP 8**, **MySQL 8**, and **Bootstrap 5**.  
SAIPS simulates a real enterprise Security Operations Centre (SOC) — covering authentication, multi-factor auth, intrusion prevention, session management, tamper-evident audit logging, and compliance reporting.

**Compliance targets:** NIST SP 800-63B · OWASP Top 10 · ISO/IEC 27001 · GDPR Article 33 · SOC 2 Type II

---

## Quick Start (5 minutes)

### Prerequisites
| Tool | Minimum | Install |
|------|---------|---------|
| PHP | 8.2+ | `sudo apt install php php-mysqli php-mbstring php-openssl php-gd` |
| MySQL | 8.0+ | `sudo apt install mysql-server` |
| OpenSSL | any | pre-installed on most Linux distros |

### 1. One-command install
```bash
git clone https://github.com/YOUR_USERNAME/ownuh-saips.git
cd ownuh-saips
bash install.sh
```

The script will:
- Prompt for your MySQL credentials
- Create both databases (`ownuh_saips`, `ownuh_credentials`)
- Run all migrations and seed data
- Generate an RSA-2048 JWT key pair in `keys/`
- Write `backend/config/.env`
- Generate real bcrypt hashes for seed users

### 2. Start the development server
```bash
php -S 0.0.0.0:8080
```

### 3. Open in browser
```
http://localhost:8080/login.php
```

### 4. Login with seed credentials
| Email | Password | Role |
|-------|----------|------|
| `sophia.johnson@acme.com` | `Admin@SAIPS2025!` | Super Admin |
| `marcus.chen@acme.com` | `Admin@SAIPS2025!` | Admin |
| `james.harris@acme.com` | `Admin@SAIPS2025!` | User |

> ⚠ **Change all passwords immediately after first login.**

---

## ngrok (Public Tunnel)

To expose the app on a public URL (e.g. for demo or submission):

```bash
# Terminal 1 — start PHP server
cd /path/to/ownuh-saips
php -S 0.0.0.0:8080

# Terminal 2 — start ngrok tunnel
ngrok http 8080
```

ngrok will print a public URL like `https://abc123.ngrok-free.app`.  
Open that URL in any browser — no extra config needed.

> The app uses `'secure' => false` on cookies so it works over both HTTP and HTTPS tunnels without extra config.

---

## Apache / Nginx Deployment

See **DEPLOYMENT.md** for full production hardening steps.

**Apache quick setup:**
```bash
sudo bash setup_apache.sh 8080
# then open http://localhost:8080/login.php
```

---

## Project Structure

```
ownuh-saips/
├── login.php                  # Authentication entry point
├── logout.php                 # Session teardown + cookie clear
├── dashboard.php              # Live security dashboard (DB-driven)
├── otp-verify.php             # MFA / OTP verification
├── audit-log.php              # Tamper-evident event log
├── users.php                  # User management
├── incidents-*.php            # Incident lifecycle
├── ips-*.php                  # Intrusion prevention
├── sessions-*.php             # Session management
├── settings-*.php             # Configuration panels
├── backend/
│   ├── bootstrap.php          # DB class, JWT, helper functions
│   ├── config/
│   │   ├── .env               # ← created by install.sh (git-ignored)
│   │   ├── .env.example       # Template — copy to .env
│   │   ├── database.php       # DB connection config
│   │   └── security.php       # Security thresholds
│   ├── api/auth/              # REST API endpoints
│   ├── Middleware/            # Auth, rate-limit, audit, IP-check
│   ├── Services/              # Email, SMS, WebAuthn, Webhooks
│   └── partials/              # Header, sidebar, mobile-sidebar
├── database/
│   ├── schema.sql             # Full MySQL schema
│   ├── seed.sql               # Development seed data
│   └── migrations/            # Incremental migration files
├── keys/                      # RSA key pair (git-ignored)
├── assets/                    # CSS, JS, images, fonts
├── install.sh                 # One-command setup (Linux/macOS)
├── setup.php                  # PHP setup script (run once)
├── setup_apache.sh            # Apache vhost setup
└── cap512-demo.php            # CAP512 syllabus demonstration page
```

---

## Authentication Flow

```
POST login.php
  ↓ Input validation + CSRF check
  ↓ User lookup (ownuh_saips.users)
  ↓ Password verify (ownuh_credentials.credentials — bcrypt cost 12)
  ↓ Failed attempt tracking → soft lock (5) → hard lock (10)
  ↓ MFA enrolled?
     YES → $_SESSION['mfa_pending'] → otp-verify.php
     NO  → create_jwt_token() → RS256 JWT → cookie
  ↓ dashboard.php
```

JWT tokens are **RS256-signed**, **base64url-encoded** (RFC 7515), and expire in 15 minutes.  
The cookie is `HttpOnly`, `SameSite=Lax`.

---

## CAP512 Syllabus Coverage

| Unit | Topic | Key Files |
|------|-------|-----------|
| I | PHP basics, variables | `login.php`, `bootstrap.php` |
| II | Control flow, loops | `dashboard.php`, `users.php` |
| III | Functions | `bootstrap.php` (20+ functions) |
| IV | String handling | `otp-verify.php`, `bootstrap.php` |
| V | Arrays | `bootstrap.php` (`paginate()`, `array_group_by()`) |
| VI | OOP / Classes | `bootstrap.php` (`Database` class) |
| VII | mysqli + DB | All `*.php` pages, `backend/api/` |
| VII | Graphics (GD/SVG) | `bootstrap.php` (`generate_score_gauge()`, `generate_avatar_image()`) |

---

## Default Credentials & Security Notes

- **Dev password** (all seed users): `Admin@SAIPS2025!`
- JWT private key is stored at `keys/private.pem` (chmod 600, git-ignored)
- Passwords stored in a **separate database** (`ownuh_credentials`) — never in `ownuh_saips`
- Timing-safe comparison used throughout (no early exit on password mismatch)
- CSRF tokens on all state-changing forms

---

## Changelog

### v1.1.0 (March 2026)
- **Fix:** JWT tokens now correctly use base64url encoding (RFC 7515) end-to-end — sign-in was always failing due to base64/base64url mismatch
- **Fix:** `require_auth()` now redirects to `login.php` instead of the static `auth-signin.html`
- **Fix:** Credentials DB connection uses env vars consistently — no more hardcoded `saips_auth` fallback that broke fresh installs
- **Fix:** Removed `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` from local dev options (caused connection failures on localhost/ngrok)
- **New:** `logout.php` at project root — clears JWT cookie and session properly
- **New:** `install.sh` — one-command setup for Linux/macOS
- **New:** `setup_apache.sh` — Apache vhost configurator
- **New:** `backend/config/.env.example` — documented environment template
- **New:** Header partial now shows logged-in user name, role, and Sign Out dropdown
- **Removed:** `debug_sig.php`, `debug_token.php`, `debug_token2.php` — not for production
