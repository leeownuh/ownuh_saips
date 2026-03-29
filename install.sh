#!/usr/bin/env bash
set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

ok()   { echo -e "${GREEN}  [OK]  $*${NC}"; }
info() { echo -e "${BLUE}  [...] $*${NC}"; }
warn() { echo -e "${YELLOW}  [WRN] $*${NC}"; }
err()  { echo -e "${RED}  [ERR] $*${NC}"; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SEED_MODE="${SEED_MODE:-portfolio}"
INSTALL_PROFILE="${INSTALL_PROFILE:-demo}"
APP_URL="${APP_URL:-http://localhost}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
APP_DB_USER="${APP_DB_USER:-saips_app}"
APP_DB_PASS="${APP_DB_PASS:-}"
AUTH_DB_USER="${AUTH_DB_USER:-saips_auth}"
AUTH_DB_PASS="${AUTH_DB_PASS:-}"

case "$SEED_MODE" in
    portfolio) SEED_FILE="$SCRIPT_DIR/database/portfolio_seed.sql" ;;
    dev)       SEED_FILE="$SCRIPT_DIR/database/seed.sql" ;;
    test)      SEED_FILE="$SCRIPT_DIR/database/test_seed.sql" ;;
    *)         err "Unsupported SEED_MODE '$SEED_MODE'. Use portfolio, dev, or test." ;;
esac

case "$INSTALL_PROFILE" in
    demo|production) ;;
    *) err "Unsupported INSTALL_PROFILE '$INSTALL_PROFILE'. Use demo or production." ;;
esac

echo ""
echo "============================================================"
echo "  Ownuh SAIPS Linux Setup"
echo "============================================================"
echo ""

[[ -f "$SCRIPT_DIR/login.php" ]] || err "Run this script from the project root."
command -v php >/dev/null 2>&1 || err "PHP CLI is required."
command -v mysql >/dev/null 2>&1 || err "MySQL client is required."
command -v openssl >/dev/null 2>&1 || err "OpenSSL is required."

info "Using seed: $(basename "$SEED_FILE")"
info "Install profile: $INSTALL_PROFILE"
info "Project root: $SCRIPT_DIR"

if [[ -z "$DB_PASS" ]]; then
    read -r -p "  MySQL host [$DB_HOST]: " input_host
    DB_HOST="${input_host:-$DB_HOST}"
    read -r -p "  MySQL port [$DB_PORT]: " input_port
    DB_PORT="${input_port:-$DB_PORT}"
    read -r -p "  MySQL user [$DB_USER]: " input_user
    DB_USER="${input_user:-$DB_USER}"
    read -r -s -p "  MySQL password [blank]: " DB_PASS
    echo
fi

MYSQL_ARGS=(-h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" --default-character-set=utf8mb4)
if [[ -n "$DB_PASS" ]]; then
    MYSQL_ARGS+=(-p"$DB_PASS")
fi

info "Testing MySQL connection..."
mysql "${MYSQL_ARGS[@]}" -e "SELECT 1;" >/dev/null || err "Could not connect to MySQL."
ok "MySQL connection verified"

info "Creating databases..."
mysql "${MYSQL_ARGS[@]}" <<'SQL'
CREATE DATABASE IF NOT EXISTS ownuh_saips CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS ownuh_credentials CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL
ok "Databases ready"

info "Importing schema..."
mysql "${MYSQL_ARGS[@]}" < "$SCRIPT_DIR/database/schema.sql"
mysql "${MYSQL_ARGS[@]}" < "$SCRIPT_DIR/database/migrations/002_credentials.sql"
mysql "${MYSQL_ARGS[@]}" < "$SCRIPT_DIR/database/migrations/003_password_resets_unify.sql"
ok "Schema import complete"

info "Importing seed..."
mysql "${MYSQL_ARGS[@]}" < "$SEED_FILE"
ok "Seed import complete"

if [[ "$INSTALL_PROFILE" == "production" ]]; then
    info "Creating least-privilege database users..."
    APP_DB_PASS="${APP_DB_PASS:-$(openssl rand -hex 24)}"
    AUTH_DB_PASS="${AUTH_DB_PASS:-$(openssl rand -hex 24)}"

    mysql "${MYSQL_ARGS[@]}" <<SQL
CREATE USER IF NOT EXISTS '$APP_DB_USER'@'127.0.0.1' IDENTIFIED BY '$APP_DB_PASS';
ALTER USER '$APP_DB_USER'@'127.0.0.1' IDENTIFIED BY '$APP_DB_PASS';
CREATE USER IF NOT EXISTS '$APP_DB_USER'@'localhost' IDENTIFIED BY '$APP_DB_PASS';
ALTER USER '$APP_DB_USER'@'localhost' IDENTIFIED BY '$APP_DB_PASS';
CREATE USER IF NOT EXISTS '$AUTH_DB_USER'@'127.0.0.1' IDENTIFIED BY '$AUTH_DB_PASS';
ALTER USER '$AUTH_DB_USER'@'127.0.0.1' IDENTIFIED BY '$AUTH_DB_PASS';
CREATE USER IF NOT EXISTS '$AUTH_DB_USER'@'localhost' IDENTIFIED BY '$AUTH_DB_PASS';
ALTER USER '$AUTH_DB_USER'@'localhost' IDENTIFIED BY '$AUTH_DB_PASS';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON ownuh_saips.* TO '$APP_DB_USER'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON ownuh_saips.* TO '$APP_DB_USER'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON ownuh_credentials.* TO '$AUTH_DB_USER'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON ownuh_credentials.* TO '$AUTH_DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    ok "Least-privilege users created"
fi

KEY_DIR="$SCRIPT_DIR/keys"
mkdir -p "$KEY_DIR"

info "Generating JWT keys..."
if [[ ! -f "$KEY_DIR/private.pem" || ! -f "$KEY_DIR/public.pem" ]]; then
    openssl genrsa -out "$KEY_DIR/private.pem" 2048 >/dev/null 2>&1
    openssl rsa -in "$KEY_DIR/private.pem" -pubout -out "$KEY_DIR/public.pem" >/dev/null 2>&1
    chmod 600 "$KEY_DIR/private.pem"
    chmod 644 "$KEY_DIR/public.pem"
    ok "JWT keys generated"
else
    ok "JWT keys already exist"
fi

ENV_FILE="$SCRIPT_DIR/backend/config/.env"
info "Writing backend/config/.env..."
IS_TUNNEL_URL=0
if [[ "$APP_URL" == *ngrok* || "$APP_URL" == *localhost.run* || "$APP_URL" == *trycloudflare* || "$APP_URL" == *".tunnel"* ]]; then
    IS_TUNNEL_URL=1
fi

ENV_DB_USER="$DB_USER"
ENV_DB_PASS="$DB_PASS"
ENV_AUTH_USER="$DB_USER"
ENV_AUTH_PASS="$DB_PASS"
ENV_APP_MODE="development"
ENV_BCRYPT="12"
ENV_TRUSTED_PROXY=""
ENV_COOKIE_SAMESITE=""

if [[ "$INSTALL_PROFILE" == "production" ]]; then
    ENV_DB_USER="$APP_DB_USER"
    ENV_DB_PASS="$APP_DB_PASS"
    ENV_AUTH_USER="$AUTH_DB_USER"
    ENV_AUTH_PASS="$AUTH_DB_PASS"
    ENV_APP_MODE="production"
    ENV_BCRYPT="14"
    if [[ "$IS_TUNNEL_URL" -eq 1 ]]; then
        ENV_TRUSTED_PROXY="any"
        ENV_COOKIE_SAMESITE="Lax"
    else
        ENV_COOKIE_SAMESITE="Strict"
    fi
fi

cat > "$ENV_FILE" <<ENV
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_NAME=ownuh_saips
DB_USER=$ENV_DB_USER
DB_PASS=$ENV_DB_PASS

DB_AUTH_HOST=$DB_HOST
DB_AUTH_PORT=$DB_PORT
DB_AUTH_NAME=ownuh_credentials
DB_AUTH_USER=$ENV_AUTH_USER
DB_AUTH_PASS=$ENV_AUTH_PASS

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASS=

JWT_PRIVATE_KEY_PATH=keys/private.pem
JWT_PUBLIC_KEY_PATH=keys/public.pem
JWT_ISSUER=ownuh-saips
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=604800
JWT_ADMIN_REFRESH_TTL=28800

APP_ENV=$ENV_APP_MODE
APP_URL=$APP_URL
APP_TIMEZONE=Asia/Kolkata
APP_TIMEZONE_LABEL=IST

BCRYPT_COST=$ENV_BCRYPT
TRUSTED_PROXY=$ENV_TRUSTED_PROXY
COOKIE_SAMESITE=$ENV_COOKIE_SAMESITE

MFA_TOTP_ISSUER=OwnuhSAIPS
MFA_EMAIL_OTP_TTL=600
MFA_EMAIL_OTP_RATE=5
ENV
chmod 600 "$ENV_FILE"
ok ".env written"

info "Verifying install counts..."
mysql "${MYSQL_ARGS[@]}" -e "
SELECT COUNT(*) AS users FROM ownuh_saips.users;
SELECT COUNT(*) AS sessions FROM ownuh_saips.sessions WHERE invalidated_at IS NULL AND expires_at > NOW();
SELECT COUNT(*) AS incidents FROM ownuh_saips.incidents;
SELECT COUNT(*) AS audit_entries FROM ownuh_saips.audit_log;
"
ok "Verification queries completed"

echo ""
echo "============================================================"
echo "  Setup complete"
echo "============================================================"
echo ""
echo "Seed mode : $SEED_MODE"
echo "Profile   : $INSTALL_PROFILE"
echo "App URL   : $APP_URL"
echo "Login URL : $APP_URL/login.php"
echo ""
echo "Primary demo account:"
echo "  Email    : lucia.alvarez@ownuh-saips.com"
echo "  Password : Admin@SAIPS2025!"
echo ""
echo "Quick start:"
echo "  php -S 0.0.0.0:8080 -t \"$SCRIPT_DIR\""
echo "  Then open: http://localhost:8080/login.php"
