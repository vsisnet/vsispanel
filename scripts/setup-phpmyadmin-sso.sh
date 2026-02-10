#!/bin/bash
#
# Setup phpMyAdmin SSO for VSISPanel
# Run once on the server: bash /opt/vsispanel/scripts/setup-phpmyadmin-sso.sh
#

set -e

INSTALL_DIR="/opt/vsispanel"
PMA_DIR="/usr/share/phpmyadmin"

echo "=== Setting up phpMyAdmin SSO ==="

# 1. Check phpMyAdmin exists
if [[ ! -d "$PMA_DIR" ]]; then
    echo "ERROR: phpMyAdmin not found at $PMA_DIR"
    exit 1
fi
echo "[OK] phpMyAdmin found at $PMA_DIR"

# 2. Generate secret key
KEY_FILE="${INSTALL_DIR}/storage/app/phpmyadmin_secret.key"
if [[ ! -f "$KEY_FILE" ]]; then
    openssl rand -hex 32 > "$KEY_FILE"
    echo "[OK] Secret key generated"
else
    echo "[OK] Secret key already exists"
fi
chmod 600 "$KEY_FILE"
chown www-data:www-data "$KEY_FILE"

# 3. Deploy signon.php
if [[ -f "${INSTALL_DIR}/deploy/phpmyadmin/signon.php" ]]; then
    cp "${INSTALL_DIR}/deploy/phpmyadmin/signon.php" "${PMA_DIR}/signon.php"
    chown root:root "${PMA_DIR}/signon.php"
    chmod 644 "${PMA_DIR}/signon.php"
    echo "[OK] signon.php deployed to ${PMA_DIR}/signon.php"
else
    echo "ERROR: ${INSTALL_DIR}/deploy/phpmyadmin/signon.php not found"
    exit 1
fi

# 4. Add Server 2 (signon auth) config
mkdir -p /etc/phpmyadmin/conf.d
cat > /etc/phpmyadmin/conf.d/vsispanel-signon.php << 'EOF'
<?php
/**
 * VSISPanel phpMyAdmin SSO Server Configuration
 * Server 2: signon auth for auto-login from panel
 */
$i++;
$cfg['Servers'][$i]['auth_type'] = 'signon';
$cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
$cfg['Servers'][$i]['SignonURL'] = '/phpmyadmin/signon.php';
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['AllowNoPassword'] = false;
EOF
echo "[OK] Server 2 (signon) config created at /etc/phpmyadmin/conf.d/vsispanel-signon.php"

# 5. Restart PHP-FPM to clear opcache
systemctl restart php8.3-fpm
echo "[OK] PHP-FPM restarted"

# 6. Verify all files exist
echo ""
echo "=== Verification ==="
for f in "$KEY_FILE" "${PMA_DIR}/signon.php" "/etc/phpmyadmin/conf.d/vsispanel-signon.php" "/etc/phpmyadmin/config.inc.php"; do
    if [[ -f "$f" ]]; then
        echo "[OK] $f"
    else
        echo "[MISSING] $f"
    fi
done

# 7. Verify phpMyAdmin config loads conf.d
if grep -q "conf.d" /etc/phpmyadmin/config.inc.php 2>/dev/null; then
    echo "[OK] config.inc.php loads conf.d/*.php"
else
    echo "[WARN] config.inc.php may not load conf.d - adding include"
    echo "" >> /etc/phpmyadmin/config.inc.php
    echo "/* Load additional configurations */" >> /etc/phpmyadmin/config.inc.php
    echo "foreach (glob('/etc/phpmyadmin/conf.d/*.php') as \$filename) { include(\$filename); }" >> /etc/phpmyadmin/config.inc.php
    echo "[OK] Added conf.d include to config.inc.php"
fi

echo ""
echo "=== phpMyAdmin SSO setup complete ==="
echo "Test: click phpMyAdmin button on a database row in the panel"
