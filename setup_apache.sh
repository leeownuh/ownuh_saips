#!/usr/bin/env bash
# ============================================================
# Ownuh SAIPS — Apache Virtual Host Setup
# Run: sudo bash setup_apache.sh
# ============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VHOST_FILE="/etc/apache2/sites-available/ownuh-saips.conf"
PORT="${1:-8080}"

cat > "$VHOST_FILE" << CONF
<VirtualHost *:${PORT}>
    ServerName localhost
    DocumentRoot ${SCRIPT_DIR}
    DirectoryIndex login.php dashboard.php

    <Directory "${SCRIPT_DIR}">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Block sensitive files
    <FilesMatch "\.(env|sql|md|sh|pem)$">
        Require all denied
    </FilesMatch>

    ErrorLog  \${APACHE_LOG_DIR}/saips-error.log
    CustomLog \${APACHE_LOG_DIR}/saips-access.log combined
</VirtualHost>
CONF

# Enable site and required modules
a2enmod rewrite headers php* 2>/dev/null || true
a2ensite ownuh-saips 2>/dev/null || true

# Add port if not already in ports.conf
grep -q "Listen ${PORT}" /etc/apache2/ports.conf || echo "Listen ${PORT}" >> /etc/apache2/ports.conf

apache2ctl configtest && systemctl reload apache2

echo "Apache configured. Open: http://localhost:${PORT}/login.php"
