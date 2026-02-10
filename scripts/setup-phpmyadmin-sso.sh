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

# 4. Ensure Server 1 (cookie auth) exists + Add signon server config
# Use a single conf.d file that handles both servers properly
mkdir -p /etc/phpmyadmin/conf.d
cat > /etc/phpmyadmin/conf.d/vsispanel-signon.php << 'PMAEOF'
<?php
/**
 * VSISPanel phpMyAdmin SSO Configuration
 *
 * Ensures Server 1 (cookie auth for manual login) exists,
 * and adds a signon server for auto-login from the panel.
 */

// Ensure Server 1 exists with cookie auth (for manual login)
if (empty($cfg['Servers'][1]['auth_type'])) {
    $cfg['Servers'][1]['auth_type'] = 'cookie';
    $cfg['Servers'][1]['host'] = 'localhost';
}

// Find next available server index for signon
$signonIdx = max(array_keys($cfg['Servers'] ?? [1 => true])) + 1;

$cfg['Servers'][$signonIdx]['auth_type'] = 'signon';
$cfg['Servers'][$signonIdx]['SignonSession'] = 'SignonSession';
$cfg['Servers'][$signonIdx]['SignonURL'] = '/phpmyadmin/signon.php';
$cfg['Servers'][$signonIdx]['host'] = 'localhost';
$cfg['Servers'][$signonIdx]['AllowNoPassword'] = false;
$cfg['Servers'][$signonIdx]['verbose'] = 'Auto-Login (VSISPanel)';
PMAEOF
echo "[OK] Signon server config created at /etc/phpmyadmin/conf.d/vsispanel-signon.php"

# 5. Ensure phpMyAdmin config loads conf.d
if [[ -f /etc/phpmyadmin/config.inc.php ]]; then
    if ! grep -q "conf.d" /etc/phpmyadmin/config.inc.php 2>/dev/null; then
        echo "" >> /etc/phpmyadmin/config.inc.php
        echo "/* Load additional configurations */" >> /etc/phpmyadmin/config.inc.php
        echo "foreach (glob('/etc/phpmyadmin/conf.d/*.php') as \$filename) { include(\$filename); }" >> /etc/phpmyadmin/config.inc.php
        echo "[OK] Added conf.d include to config.inc.php"
    else
        echo "[OK] config.inc.php already loads conf.d/*.php"
    fi
elif [[ -f "${PMA_DIR}/config.inc.php" ]]; then
    if ! grep -q "conf.d" "${PMA_DIR}/config.inc.php" 2>/dev/null; then
        echo "" >> "${PMA_DIR}/config.inc.php"
        echo "foreach (glob('/etc/phpmyadmin/conf.d/*.php') as \$filename) { include(\$filename); }" >> "${PMA_DIR}/config.inc.php"
        echo "[OK] Added conf.d include to ${PMA_DIR}/config.inc.php"
    fi
else
    # No config exists - create minimal one
    cat > "${PMA_DIR}/config.inc.php" << 'CFGEOF'
<?php
$cfg['blowfish_secret'] = '$(openssl rand -hex 16)';
$cfg['Servers'][1]['auth_type'] = 'cookie';
$cfg['Servers'][1]['host'] = 'localhost';
$cfg['TempDir'] = '/tmp';
foreach (glob('/etc/phpmyadmin/conf.d/*.php') as $filename) { include($filename); }
CFGEOF
    echo "[OK] Created minimal config.inc.php"
fi

# 6. Ensure session directory is writable
mkdir -p /var/lib/php/sessions
chmod 1733 /var/lib/php/sessions

# 7. Restart PHP-FPM to clear opcache
systemctl restart php8.3-fpm
echo "[OK] PHP-FPM restarted"

# 8. Verify
echo ""
echo "=== Verification ==="
for f in "$KEY_FILE" "${PMA_DIR}/signon.php" "/etc/phpmyadmin/conf.d/vsispanel-signon.php"; do
    if [[ -f "$f" ]]; then
        echo "[OK] $f"
    else
        echo "[MISSING] $f"
    fi
done

# Test: load phpMyAdmin config and show server list
php -r "
\$cfg = [];
\$i = 0;
\$dbname = ''; \$dbserver = ''; \$dbport = ''; \$dbuser = ''; \$dbpass = '';
foreach (['/etc/phpmyadmin/config.inc.php', '/usr/share/phpmyadmin/config.inc.php'] as \$f) {
    if (file_exists(\$f)) { include \$f; break; }
}
echo PHP_EOL . 'phpMyAdmin Servers:' . PHP_EOL;
foreach (\$cfg['Servers'] ?? [] as \$idx => \$srv) {
    echo \"  Server \$idx: auth_type=\" . (\$srv['auth_type'] ?? 'N/A') . \", host=\" . (\$srv['host'] ?? 'N/A') . PHP_EOL;
}
"

echo ""
echo "=== phpMyAdmin SSO setup complete ==="
