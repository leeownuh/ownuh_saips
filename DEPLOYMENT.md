# DEPLOYMENT.md — Ownuh SAIPS Server Setup & Deployment Guide

---

## Server Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |
| CPU | 2 vCPU | 4 vCPU |
| RAM | 4 GB | 8 GB |
| Disk | 40 GB SSD | 100 GB SSD (audit log growth) |
| PHP | 8.2 | 8.3 |
| MySQL | 8.0 | 8.0.35+ |
| Redis | 7.0 | 7.2 |
| Web Server | Nginx 1.24 | Nginx 1.26 |
| SSL | Let's Encrypt / DigiCert | DigiCert EV |

---

## 1. Server Hardening

```bash
# Disable root SSH, use key-based auth only
sed -i 's/PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart sshd

# UFW firewall — allow only 80, 443, 22
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable

# Fail2ban for SSH
apt install fail2ban -y
systemctl enable fail2ban
```

---

## 2. Install Dependencies

```bash
# PHP 8.3 + extensions
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php
apt update
apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-redis \
    php8.3-mbstring php8.3-json php8.3-bcmath php8.3-gd \
    php8.3-curl php8.3-zip php8.3-xml php8.3-sodium

# MySQL 8.0
apt install -y mysql-server
mysql_secure_installation

# Redis 7
apt install -y redis-server
# Set a strong Redis password in /etc/redis/redis.conf:
# requirepass your_redis_password_here
systemctl enable redis-server

# Nginx
apt install -y nginx
systemctl enable nginx
```

---

## 3. Nginx Configuration

Create `/etc/nginx/sites-available/ownuh-saips`:

```nginx
# Redirect HTTP → HTTPS
server {
    listen 80;
    server_name saips.your-domain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name saips.your-domain.com;

    root /var/www/ownuh-saips;
    index index.html auth-signin.html;

    # TLS 1.3 only
    ssl_certificate     /etc/letsencrypt/live/saips.your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/saips.your-domain.com/privkey.pem;
    ssl_protocols       TLSv1.3;
    ssl_ciphers         TLS_AES_256_GCM_SHA384:TLS_CHACHA20_POLY1305_SHA256;
    ssl_prefer_server_ciphers on;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_stapling        on;
    ssl_stapling_verify on;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

    # PHP-FPM for API
    location /api/ {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        # Strip passwords from logs
        fastcgi_param HTTP_AUTHORIZATION "";
    }

    # Static assets
    location /assets/ {
        expires 7d;
        add_header Cache-Control "public, immutable";
    }

    # Block access to sensitive files
    location ~* \.(md|sql|env|log|sh)$ {
        deny all;
        return 404;
    }

    location ~ /\. {
        deny all;
    }

    access_log /var/log/nginx/saips-access.log;
    error_log  /var/log/nginx/saips-error.log;
}
```

```bash
ln -s /etc/nginx/sites-available/ownuh-saips /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

---

## 4. Let's Encrypt SSL

```bash
apt install certbot python3-certbot-nginx -y
certbot --nginx -d saips.your-domain.com
# Auto-renewal
systemctl enable certbot.timer
```

---

## 5. MySQL Setup

```sql
-- Run as root
CREATE DATABASE ownuh_saips CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE ownuh_credentials CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'saips_app'@'localhost' IDENTIFIED BY 'STRONG_APP_PASSWORD_HERE';
GRANT SELECT, INSERT, UPDATE, DELETE ON ownuh_saips.* TO 'saips_app'@'localhost';
-- Restrict audit_log: INSERT only (no UPDATE/DELETE)
REVOKE UPDATE, DELETE ON ownuh_saips.audit_log FROM 'saips_app'@'localhost';

CREATE USER 'saips_auth'@'localhost' IDENTIFIED BY 'STRONG_AUTH_PASSWORD_HERE';
GRANT SELECT, UPDATE ON ownuh_credentials.credentials TO 'saips_auth'@'localhost';

FLUSH PRIVILEGES;
```

```bash
# Run schema
mysql -u root -p ownuh_saips < /var/www/ownuh-saips/database/schema.sql
```

---

## 6. MySQL Encryption at Rest (InnoDB)

```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
# InnoDB tablespace encryption
early-plugin-load=keyring_file.so
keyring_file_data=/var/lib/mysql-keyring/keyring
innodb_encrypt_tables=ON
innodb_encrypt_log=ON
```

```bash
# Rotate master key after setup
mysql -u root -p -e "ALTER INSTANCE ROTATE INNODB MASTER KEY;"
```

---

## 7. Redis Configuration

```bash
# /etc/redis/redis.conf — key settings
requirepass YOUR_REDIS_STRONG_PASSWORD
bind 127.0.0.1
maxmemory 512mb
maxmemory-policy allkeys-lru
```

---

## 8. PHP-FPM Hardening

```ini
# /etc/php/8.3/fpm/php.ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/saips-error.log
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
```

---

## 9. Cron Jobs

```bash
crontab -e
# Purge expired login attempts (hourly)
0 * * * * mysql -u saips_app -p'APP_PASSWORD' ownuh_saips -e "CALL sp_purge_login_attempts();"

# Purge expired sessions (hourly)
30 * * * * mysql -u saips_app -p'APP_PASSWORD' ownuh_saips -e "CALL sp_purge_expired_sessions();"

# Update threat intelligence feeds (every 6 hours per SRS §3.3)
0 */6 * * * /var/www/ownuh-saips/backend/scripts/update-threat-feeds.sh

# Auto-renew SSL
0 0 * * * certbot renew --quiet
```

---

## 10. Post-Deployment Checklist (SRS §7)

- [ ] TLS 1.3 verified: `openssl s_client -connect saips.your-domain.com:443 -tls1_3`
- [ ] HSTS header present: `curl -I https://saips.your-domain.com`
- [ ] CSP header configured
- [ ] CSRF protection active
- [ ] bcrypt cost 12 in config
- [ ] Audit log INSERT-only permissions verified
- [ ] Admin accounts enrolled with FIDO2 hardware keys
- [ ] Default seed passwords changed
- [ ] Geo-block list configured
- [ ] Redis password set
- [ ] Cron jobs running
- [ ] Monitoring/alerting connected (webhook URL in `.env`)
- [ ] Penetration test scheduled

---

## File Permissions

```bash
chown -R www-data:www-data /var/www/ownuh-saips
chmod -R 755 /var/www/ownuh-saips
chmod 600 /var/www/ownuh-saips/backend/config/.env
chmod 600 /etc/saips/keys/private.pem
```

---

*See `SECURITY.md` for the full hardening and compliance checklist.*
