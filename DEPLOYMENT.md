# DEPLOYMENT.md - Ownuh SAIPS Production, ngrok & GitHub Setup

This guide is written for two goals: getting the app online safely, and making sure the public-facing demo keeps its polish once it leaves localhost.

## Contents

1. GitHub push
2. Scripted setup
3. Local serving
4. ngrok exposure
5. Linux/Apache deployment
6. Environment variables
7. Security checklist

---

## 1. GitHub push

### First push

```bash
git init
git add .
git commit -m "docs: ngrok + feature updates"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/ownuh-saips.git
git push -u origin main
```

### Important

Never commit:
- `backend/config/.env`
- `keys/private.pem`
- local tunnel URLs

If a private key was committed in the past, rotate it and clean history before pushing publicly.

For a stronger GitHub first impression, make sure the repo lands with:
- seeded demo data that matches the screenshots and walkthrough
- a working public URL or clear local quick start
- executive reporting and email features configured honestly, not just described
- the Demo lane enabled if you are sharing via ngrok, so public viewers stay inside the masked, read-only showcase

---

## 2. Scripted setup

### Windows / XAMPP

```powershell
cd C:\xampp\htdocs\ownuh_saips_fixed
Set-ExecutionPolicy -Scope Process Bypass
.\setup_windows.ps1
```

Or:

```bat
cd C:\xampp\htdocs\ownuh_saips_fixed
setup_windows.bat
```

By default this imports the recruiter-focused `database/portfolio_seed.sql` dataset and writes `backend/config/.env`.
Use `.\setup_windows.ps1 -Profile production` to create least-privilege DB users and a production-safe `.env`.

### Linux

```bash
git clone https://github.com/YOUR_USERNAME/ownuh-saips.git /var/www/ownuh-saips
cd /var/www/ownuh-saips
bash install.sh
```

By default this also imports `database/portfolio_seed.sql` and writes `backend/config/.env`.
Use `INSTALL_PROFILE=production bash install.sh` to create least-privilege DB users and a production-safe `.env`.

### Seed modes

Both setup scripts support alternate seed files:

- `portfolio` for recruiter/demo setup
- `dev` for older development seed data
- `test` for deterministic test fixtures

Examples:

```powershell
.\setup_windows.ps1 -Seed portfolio
```

```bash
SEED_MODE=portfolio bash install.sh
```

---

## 3. Local serving

### PHP built-in server

```bash
php -S 0.0.0.0:8080
```

Open:

```text
http://localhost:8080/login.php
```

### XAMPP / Apache

Serve from:

```text
C:\xampp\htdocs\ownuh_saips_fixed
```

Open:

```text
http://localhost/ownuh_saips_fixed/login.php
```

---

## 4. Expose with ngrok

### Install and authenticate

```bash
ngrok config add-authtoken YOUR_AUTH_TOKEN
```

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
APP_ENV=production
APP_URL=https://YOUR-SUBDOMAIN.ngrok-free.app
TRUSTED_PROXY=any
COOKIE_SAMESITE=Lax
APP_TIMEZONE=Asia/Kolkata
APP_TIMEZONE_LABEL=IST
DEMO_MODE=1
```

Why:
- `TRUSTED_PROXY=any` allows forwarded HTTPS headers from ngrok
- `COOKIE_SAMESITE=Lax` keeps login/session cookies working through the tunnel
- `APP_URL` should match the public URL used in password-reset flows
- `DEMO_MODE=1` enables a non-technical Demo vs Production chooser while leaving the core app in production mode

What that buys you:
- public viewers can choose the guided showcase without touching `.env`
- demo identities, IPs, and session-like values stay masked or tokenised where needed
- risky admin controls stay read-only in the public lane

---

## 5. Linux / Apache deployment

### Recommended stack

| Component | Minimum |
|---|---|
| Ubuntu | 22.04 |
| PHP | 8.2 |
| MySQL | 8.0 |
| Redis | 7 |
| Apache | 2.4 |

### Install

```bash
git clone https://github.com/YOUR_USERNAME/ownuh-saips.git /var/www/ownuh-saips
cd /var/www/ownuh-saips
bash install.sh
sudo bash setup_apache.sh 80
```

For portfolio demos, the default scripted install is already the right choice because it loads the fuller sample dataset automatically.

### Production `.env`

```env
APP_ENV=production
APP_URL=https://yourdomain.com
APP_TIMEZONE=Asia/Kolkata
APP_TIMEZONE_LABEL=IST
DB_USER=saips_app
DB_PASS=strong_random_password
REDIS_PASS=strong_redis_password
TRUSTED_PROXY=
COOKIE_SAMESITE=Strict
BCRYPT_COST=14
DEMO_MODE=0
```

---

## 6. Environment variables

| Variable | Default | Purpose |
|---|---|---|
| `APP_ENV` | `production` | Development or production mode |
| `DEMO_MODE` | `0` | Enables recruiter-demo helpers without changing the core runtime mode |
| `APP_URL` | `http://localhost:8080` | Public/base URL |
| `APP_TIMEZONE` | `Asia/Kolkata` | PHP/app timezone |
| `APP_TIMEZONE_LABEL` | `IST` | Display suffix for timestamps |
| `OPENAI_API_KEY` | empty | Enables live AI executive-report generation |
| `OPENAI_BASE_URL` | provider default | Optional OpenAI-compatible base URL, such as Groq |
| `OPENAI_MODEL` | app default | Optional model override for executive reporting |
| `EMAIL_PROVIDER` | `smtp` | Mail backend; `sendgrid` is the easiest fully supported option |
| `EMAIL_FROM_EMAIL` | app default | Sender used for executive reports and security alerts |
| `SENDGRID_API_KEY` | empty | Required when `EMAIL_PROVIDER=sendgrid` |
| `GEOIP_DB_PATH` | empty | Optional GeoLite2 City `.mmdb` path for local region/country lookup |
| `DB_HOST` | `127.0.0.1` | Main DB host |
| `DB_AUTH_HOST` | `127.0.0.1` | Credentials DB host |
| `JWT_PRIVATE_KEY_PATH` | `keys/private.pem` | Private signing key |
| `JWT_PUBLIC_KEY_PATH` | `keys/public.pem` | Public verify key |
| `TRUSTED_PROXY` | blank | Set to `any` for ngrok/tunnels |
| `COOKIE_SAMESITE` | auto | Set `Lax` for ngrok, `Strict` for direct HTTPS |
| `BCRYPT_COST` | `12` | Password hashing cost |

---

## 7. Security checklist

- [ ] `backend/config/.env` is not tracked by git
- [ ] `keys/private.pem` is not tracked by git
- [ ] `APP_URL` matches the real public URL
- [ ] Windows setup tested with `setup_windows.ps1` if sharing with Windows reviewers
- [ ] Linux setup tested with `install.sh` if sharing with Linux reviewers
- [ ] For ngrok: `TRUSTED_PROXY=any` and `COOKIE_SAMESITE=Lax` are set
- [ ] Password reset flow tested end to end
- [ ] Executive report generation, history, and export tested once after deploy
- [ ] PDF export reviewed once to confirm the branded executive layout renders cleanly
- [ ] Scheduled executive-report cadence and attachment settings reviewed in `settings-compliance.php`
- [ ] Weekly executive-report email tested with the chosen provider
- [ ] GeoLite2 database installed if region accuracy is required
- [ ] At least one alert-rule event tested end to end for email delivery
- [ ] `auth-create-password.php` tested after sign-in
- [ ] MFA recovery tested with admin-issued bypass token
- [ ] IPS pages checked with fresh failed-login data
- [ ] Stored procedures installed or audit fallback confirmed in logs
