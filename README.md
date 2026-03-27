# Ownuh SAIPS - Secure Authentication & Intrusion Prevention System

**Version:** 1.2.0  
**Classification:** CONFIDENTIAL  
**Effective:** March 2026

> CAP512 - PHP & MySQL for Dynamic Web Applications  
> BSc (Hons) Computer Science & Cyber Security - Lovely Professional University - 2026

---

## Overview

Ownuh SAIPS is a full-stack security administration platform built with PHP, MySQL, Bootstrap, and Redis-backed security controls. It simulates a real SOC-style environment with authentication, MFA, password recovery, intrusion prevention, audit logging, session management, and compliance reporting.

**Compliance targets:** NIST SP 800-63B, OWASP Top 10, ISO/IEC 27001, GDPR Article 33, SOC 2 Type II

---

## Quick Start

### 1. Install

```bash
git clone https://github.com/YOUR_USERNAME/ownuh-saips.git
cd ownuh-saips
bash install.sh
```

The installer:
- creates `ownuh_saips` and `ownuh_credentials`
- imports schema and seed data
- generates JWT keys in `keys/`
- writes `backend/config/.env`
- hashes the seed credentials

### 2. Start the app

```bash
php -S 0.0.0.0:8080
```

Or serve it with XAMPP/Apache from:

```text
C:\xampp\htdocs\ownuh_saips_fixed
```

### 3. Open in browser

```text
# PHP built-in server
http://localhost:8080/login.php

# XAMPP / Apache
http://localhost/ownuh_saips_fixed/login.php
```

### 4. Seed users

| Email | Password | Role |
|---|---|---|
| `sophia.johnson@acme.com` | `Admin@SAIPS2025!` | Super Admin |
| `marcus.chen@acme.com` | `Admin@SAIPS2025!` | Admin |
| `james.harris@acme.com` | `Admin@SAIPS2025!` | User |

Change all seed passwords immediately after first login.

---

## ngrok

Use ngrok when you need a public HTTPS demo URL.

### Built-in PHP server

```bash
php -S 0.0.0.0:8080
ngrok http 8080
```

Open:

```text
https://YOUR-SUBDOMAIN.ngrok-free.app/login.php
```

### XAMPP / Apache

```bash
ngrok http 80
```

Open:

```text
https://YOUR-SUBDOMAIN.ngrok-free.app/ownuh_saips_fixed/login.php
```

### Required `.env` values for ngrok

```env
APP_URL=https://YOUR-SUBDOMAIN.ngrok-free.app
TRUSTED_PROXY=any
COOKIE_SAMESITE=Lax
APP_TIMEZONE=Asia/Kolkata
APP_TIMEZONE_LABEL=IST
```

`TRUSTED_PROXY=any` allows the app to trust ngrok's forwarded HTTPS headers.  
`COOKIE_SAMESITE=Lax` prevents login/session breakage through the tunnel.

---

## Major Features

- RS256 JWT authentication with secure cookie/session handling
- MFA flows with TOTP, email OTP, backup codes, and admin-issued bypass tokens
- Self-service password reset with token confirmation and session revocation
- Authenticated password-change page using the create-password UI
- Tamper-evident audit logging with direct-table fallback if the stored procedure is absent
- IPS modules for brute-force visibility, blocked IP management, geo rules, and rate-limit configuration
- Session tracking and revocation
- Incident tracking and compliance views
- IST-based application time defaults via environment configuration

---

## Recent Security Features

- `forgot-password.php` + `reset-password.php` + `backend/api/auth/reset-confirm.php` now form a working self-service password reset flow
- `auth-create-password.php` provides the polished password UI for authenticated password changes
- `users.php` can issue MFA bypass tokens and `otp-verify.php` can consume them once for recovery
- Audit writes no longer fail locally when `sp_insert_audit_log` is missing
- IPS pages now match the current database schema and failed login attempts are recorded for brute-force reporting
- Timestamps and dashboard clocks now display as IST by default

---

## Important Pages

- `login.php` - sign in
- `forgot-password.php` - request reset link
- `reset-password.php` - token-based password reset UI
- `auth-create-password.php` - authenticated password change UI
- `otp-verify.php` - MFA verification and bypass-token recovery
- `users.php` - user management and MFA bypass issuance
- `ips-blocked-ips.php` - blocked IP administration
- `ips-brute-force.php` - brute-force monitoring
- `ips-geo-block.php` - geo rule management
- `dashboard.php` - live security dashboard

---

## Security Notes

- Password hashes are isolated in `ownuh_credentials`
- CSRF tokens are used on state-changing browser flows
- JWT keys are generated locally and should stay out of git
- Session cookies adapt correctly for direct localhost vs ngrok/reverse-proxy use
- Password history and similarity checks are enforced on both reset and change flows

---

## Changelog

### v1.2.0 (March 2026)

- Added live password-change page using the create-password UI
- Upgraded reset-password page to the same password UI shell
- Fixed password-reset links for `/ownuh_saips_fixed/` deployments
- Fixed reset confirmation lookup issues caused by mixed collations
- Added MFA bypass issuance and one-time recovery consumption
- Added audit fallback when the stored procedure is missing
- Fixed IPS modules to align with current schema and login-attempt tracking
- Switched app display time defaults to IST

### v1.1.0 (March 2026)

- Fixed JWT base64url handling end to end
- Fixed `require_auth()` redirect flow
- Fixed credentials DB env handling
- Added `logout.php`
- Added installer and Apache setup scripts

---

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for:
- GitHub push steps
- ngrok exposure
- Apache/Linux deployment
- environment variables
- security checklist
