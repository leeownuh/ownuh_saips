# DEPLOYMENT.md - Ownuh SAIPS Production, ngrok & GitHub Setup

## Contents

1. GitHub push
2. Local serving
3. ngrok exposure
4. Linux/Apache deployment
5. Environment variables
6. Security checklist

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

---

## 2. Local serving

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

## 3. Expose with ngrok

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
```

Why:
- `TRUSTED_PROXY=any` allows forwarded HTTPS headers from ngrok
- `COOKIE_SAMESITE=Lax` keeps login/session cookies working through the tunnel
- `APP_URL` should match the public URL used in password-reset flows

---

## 4. Linux / Apache deployment

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
```

---

## 5. Environment variables

| Variable | Default | Purpose |
|---|---|---|
| `APP_ENV` | `development` | Development or production mode |
| `APP_URL` | `http://localhost:8080` | Public/base URL |
| `APP_TIMEZONE` | `Asia/Kolkata` | PHP/app timezone |
| `APP_TIMEZONE_LABEL` | `IST` | Display suffix for timestamps |
| `DB_HOST` | `127.0.0.1` | Main DB host |
| `DB_AUTH_HOST` | `127.0.0.1` | Credentials DB host |
| `JWT_PRIVATE_KEY_PATH` | `keys/private.pem` | Private signing key |
| `JWT_PUBLIC_KEY_PATH` | `keys/public.pem` | Public verify key |
| `TRUSTED_PROXY` | blank | Set to `any` for ngrok/tunnels |
| `COOKIE_SAMESITE` | auto | Set `Lax` for ngrok, `Strict` for direct HTTPS |
| `BCRYPT_COST` | `12` | Password hashing cost |

---

## 6. Security checklist

- [ ] `backend/config/.env` is not tracked by git
- [ ] `keys/private.pem` is not tracked by git
- [ ] `APP_URL` matches the real public URL
- [ ] For ngrok: `TRUSTED_PROXY=any` and `COOKIE_SAMESITE=Lax` are set
- [ ] Password reset flow tested end to end
- [ ] `auth-create-password.php` tested after sign-in
- [ ] MFA recovery tested with admin-issued bypass token
- [ ] IPS pages checked with fresh failed-login data
- [ ] Stored procedures installed or audit fallback confirmed in logs
