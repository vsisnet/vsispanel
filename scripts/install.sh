#!/bin/bash
#=============================================================================
# VSISPanel - Full Installation Script
# Installs all dependencies and sets up VSISPanel on a fresh Ubuntu server.
#
# Usage:
#   curl -sSL https://raw.githubusercontent.com/vsisnet/vsispanel/main/scripts/install.sh | bash
#   OR: sudo bash /opt/vsispanel/scripts/install.sh [OPTIONS]
#
# Options:
#   --skip-mail       Skip mail server (Postfix/Dovecot) installation
#   --skip-dns        Skip DNS server (PowerDNS) installation
#   --non-interactive Skip all prompts, use defaults
#   --uninstall       Remove VSISPanel completely (keeps system packages)
#   --help            Show this help message
#=============================================================================

set -eu

PANEL_DIR="/opt/vsispanel"
REPO_URL="https://github.com/vsisnet/vsispanel.git"
LOG_DIR="/var/log/vsispanel"
LOG_FILE="${LOG_DIR}/install.log"

SKIP_MAIL=false
SKIP_DNS=false
NON_INTERACTIVE=false
ADMIN_EMAIL=""
ADMIN_PASS=""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

log_info()  { echo -e "${CYAN}[INFO]${NC} $1" | tee -a "$LOG_FILE"; }
log_ok()    { echo -e "${GREEN}  ✓${NC} $1" | tee -a "$LOG_FILE"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC} $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"; }

step() {
    echo "" | tee -a "$LOG_FILE"
    echo -e "${BOLD}━━━ Step $1: $2 ━━━${NC}" | tee -a "$LOG_FILE"
}

#-----------------------------------------------------------------------------
# Uninstall VSISPanel
#-----------------------------------------------------------------------------
do_uninstall() {
    echo ""
    echo -e "${RED}╔══════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║     VSISPanel Uninstaller                    ║${NC}"
    echo -e "${RED}╚══════════════════════════════════════════════╝${NC}"
    echo ""

    if [[ $EUID -ne 0 ]]; then
        echo -e "${RED}[ERROR]${NC} Must be run as root"
        exit 1
    fi

    echo -e "${YELLOW}This will remove VSISPanel completely:${NC}"
    echo "  - Stop and remove all vsispanel systemd services"
    echo "  - Remove Nginx site config and SSL certificate"
    echo "  - Remove Supervisor configs"
    echo "  - Drop MySQL database and user 'vsispanel'"
    echo "  - Remove /opt/vsispanel directory"
    echo "  - Remove crontab entry"
    echo "  - Remove sudoers config"
    echo ""
    echo -e "${YELLOW}System packages (PHP, MySQL, Redis, Nginx, etc.) will NOT be removed.${NC}"
    echo ""
    read -rp "Are you sure? (y/N): " confirm
    if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
        echo "Cancelled."
        exit 0
    fi

    echo ""

    # Stop and remove panel systemd services
    echo -e "${CYAN}[1/8]${NC} Stopping panel services..."
    for svc in vsispanel-web vsispanel-horizon vsispanel-reverb vsispanel-terminal; do
        systemctl stop "${svc}.service" 2>/dev/null || true
        systemctl disable "${svc}.service" 2>/dev/null || true
        rm -f "/etc/systemd/system/${svc}.service"
    done
    systemctl daemon-reload
    echo -e "${GREEN}  ✓${NC} Panel services removed"

    # Remove Supervisor configs
    echo -e "${CYAN}[2/8]${NC} Removing Supervisor configs..."
    supervisorctl stop all 2>/dev/null || true
    rm -f /etc/supervisor/conf.d/vsispanel-*.conf
    supervisorctl reread 2>/dev/null || true
    supervisorctl update 2>/dev/null || true
    echo -e "${GREEN}  ✓${NC} Supervisor configs removed"

    # Remove Nginx config and SSL
    echo -e "${CYAN}[3/8]${NC} Removing Nginx config and SSL certificate..."
    rm -f /etc/nginx/sites-enabled/vsispanel.conf
    rm -f /etc/nginx/sites-available/vsispanel.conf
    rm -rf /etc/ssl/vsispanel
    # Restore default site if available
    if [[ -f /etc/nginx/sites-available/default ]]; then
        ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default 2>/dev/null || true
    fi
    systemctl reload nginx 2>/dev/null || true
    echo -e "${GREEN}  ✓${NC} Nginx config and SSL removed"

    # Drop database and user
    echo -e "${CYAN}[4/8]${NC} Dropping database and user..."
    mysql -e "DROP DATABASE IF EXISTS vsispanel; DROP USER IF EXISTS 'vsispanel'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null || true
    echo -e "${GREEN}  ✓${NC} Database 'vsispanel' dropped"

    # Remove crontab entry
    echo -e "${CYAN}[5/8]${NC} Removing crontab entry..."
    crontab -l 2>/dev/null | grep -v "schedule:run" | crontab - 2>/dev/null || true
    echo -e "${GREEN}  ✓${NC} Crontab entry removed"

    # Remove rclone config
    echo -e "${CYAN}[6/8]${NC} Removing backup configs..."
    rm -rf /etc/rclone /var/backups/vsispanel
    echo -e "${GREEN}  ✓${NC} Backup configs removed"

    # Remove sudoers
    echo -e "${CYAN}[7/8]${NC} Removing sudoers config..."
    rm -f /etc/sudoers.d/vsispanel
    echo -e "${GREEN}  ✓${NC} Sudoers config removed"

    # Remove source code
    echo -e "${CYAN}[8/8]${NC} Removing /opt/vsispanel..."
    rm -rf /opt/vsispanel
    echo -e "${GREEN}  ✓${NC} Source code removed"

    echo ""
    echo -e "${GREEN}VSISPanel has been completely removed.${NC}"
    echo -e "System packages (PHP, MySQL, Redis, Nginx, etc.) were kept."
    echo -e "To reinstall: ${CYAN}curl -sSL https://raw.githubusercontent.com/vsisnet/vsispanel/main/scripts/install.sh | bash${NC}"
    echo ""
}

#-----------------------------------------------------------------------------
# Parse arguments
#-----------------------------------------------------------------------------
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --skip-mail) SKIP_MAIL=true ;;
            --skip-dns)  SKIP_DNS=true ;;
            --non-interactive) NON_INTERACTIVE=true ;;
            --uninstall) do_uninstall; exit 0 ;;
            --help)
                echo "Usage: sudo bash install.sh [OPTIONS]"
                echo ""
                echo "Options:"
                echo "  --skip-mail       Skip Postfix/Dovecot installation"
                echo "  --skip-dns        Skip PowerDNS installation"
                echo "  --non-interactive Use default values, no prompts"
                echo "  --uninstall       Remove VSISPanel completely"
                echo "  --help            Show this help"
                exit 0
                ;;
            *) log_error "Unknown option: $1"; exit 1 ;;
        esac
        shift
    done
}

#-----------------------------------------------------------------------------
# Check system requirements
#-----------------------------------------------------------------------------
check_system() {
    step "1/9" "Checking system requirements"

    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi

    # Check OS
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        log_ok "OS: ${PRETTY_NAME}"
        if [[ "$ID" != "ubuntu" ]]; then
            log_warn "Recommended OS: Ubuntu 22.04/24.04. Current: ${ID} ${VERSION_ID}"
        fi
    fi

    # Check RAM (minimum 2GB)
    local total_ram
    total_ram=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
    if [[ $total_ram -lt 1800 ]]; then
        log_error "Minimum 2GB RAM required. Current: ${total_ram}MB"
        exit 1
    fi
    log_ok "RAM: ${total_ram}MB"

    # Check disk (minimum 10GB free)
    local free_disk
    free_disk=$(df -BG / | awk 'NR==2 {gsub(/G/,""); print $4}')
    if [[ $free_disk -lt 10 ]]; then
        log_warn "Recommended 10GB free disk. Current: ${free_disk}GB"
    fi
    log_ok "Disk: ${free_disk}GB free"

    # Create swap if needed (npm requires extra memory)
    local total_swap
    total_swap=$(awk '/SwapTotal/ {print int($2/1024)}' /proc/meminfo)
    if [[ $total_swap -lt 1024 ]] && [[ ! -f /swapfile ]]; then
        log_info "Creating 2GB swap file for build process..."
        fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile >> "$LOG_FILE" 2>&1 && swapon /swapfile
        # Make swap persistent across reboot
        if ! grep -q '/swapfile' /etc/fstab; then
            echo '/swapfile none swap sw 0 0' >> /etc/fstab
        fi
        log_ok "Swap file created (2GB, persistent)"
    fi
}

#-----------------------------------------------------------------------------
# Install system dependencies
#-----------------------------------------------------------------------------
install_dependencies() {
    step "2/9" "Installing system dependencies"

    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq >> "$LOG_FILE" 2>&1

    # Essential packages (including build tools for npm native modules)
    log_info "Installing essential packages..."
    apt-get install -y -qq software-properties-common curl wget git unzip zip \
        apt-transport-https ca-certificates lsb-release gnupg2 \
        build-essential python3 make g++ acl >> "$LOG_FILE" 2>&1
    log_ok "Essential packages installed"
}

#-----------------------------------------------------------------------------
# Install PHP 8.3
#-----------------------------------------------------------------------------
install_php() {
    step "3/9" "Installing PHP 8.3 + Composer + Node.js"

    # Always add PPA and update to ensure PHP 8.3 packages are available
    if ! apt-cache show php8.3-fpm &>/dev/null 2>&1; then
        log_info "Adding PHP PPA..."
        add-apt-repository -y ppa:ondrej/php >> "$LOG_FILE" 2>&1
    fi
    apt-get update -qq >> "$LOG_FILE" 2>&1

    # Fix any broken packages first
    apt-get -f install -y -qq >> "$LOG_FILE" 2>&1 || true

    # Always ensure all required PHP extensions are installed
    log_info "Installing PHP 8.3 and extensions..."
    if ! apt-get install -y -qq php8.3-fpm php8.3-cli php8.3-common php8.3-mysql \
        php8.3-pgsql php8.3-sqlite3 php8.3-redis php8.3-mbstring php8.3-xml \
        php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-intl \
        php8.3-readline php8.3-soap php8.3-imap php8.3-opcache >> "$LOG_FILE" 2>&1; then
        log_error "PHP installation failed. Trying to fix..."
        apt-get update -qq >> "$LOG_FILE" 2>&1
        dpkg --configure -a >> "$LOG_FILE" 2>&1 || true
        apt-get install -y -qq php8.3-fpm php8.3-cli php8.3-common php8.3-mysql \
            php8.3-pgsql php8.3-sqlite3 php8.3-redis php8.3-mbstring php8.3-xml \
            php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-intl \
            php8.3-readline php8.3-soap php8.3-imap php8.3-opcache >> "$LOG_FILE" 2>&1
    fi

    # Verify critical extensions are present
    if ! php -m 2>/dev/null | grep -q "^dom$"; then
        log_error "PHP ext-dom not available after install. Check ${LOG_FILE}"
        exit 1
    fi
    log_ok "PHP 8.3 ready: $(php -v | head -1 | awk '{print $2}')"
}

#-----------------------------------------------------------------------------
# Install Composer
#-----------------------------------------------------------------------------
install_composer() {
    if command -v composer &>/dev/null; then
        log_ok "Composer already installed"
        return
    fi

    log_info "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >> "$LOG_FILE" 2>&1
    log_ok "Composer installed"
}

#-----------------------------------------------------------------------------
# Install Node.js 20
#-----------------------------------------------------------------------------
install_nodejs() {
    if command -v node &>/dev/null && node -v | grep -q "v2[0-9]"; then
        log_ok "Node.js already installed: $(node -v)"
        return
    fi

    log_info "Installing Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >> "$LOG_FILE" 2>&1
    apt-get install -y -qq nodejs >> "$LOG_FILE" 2>&1
    log_ok "Node.js installed: $(node -v)"
}

#-----------------------------------------------------------------------------
# Install services (MySQL, Redis, Nginx, etc.)
#-----------------------------------------------------------------------------
install_services() {
    step "4/9" "Installing services"

    # MySQL
    if command -v mysql &>/dev/null; then
        log_ok "MySQL already installed"
    else
        log_info "Installing MySQL 8.0..."
        apt-get install -y -qq mysql-server >> "$LOG_FILE" 2>&1
        systemctl enable mysql >> "$LOG_FILE" 2>&1
        systemctl start mysql
        log_ok "MySQL installed"
    fi

    # Redis
    if command -v redis-cli &>/dev/null; then
        log_ok "Redis already installed"
    else
        log_info "Installing Redis..."
        apt-get install -y -qq redis-server >> "$LOG_FILE" 2>&1
        systemctl enable redis-server >> "$LOG_FILE" 2>&1
        systemctl start redis-server
        log_ok "Redis installed"
    fi

    # Nginx
    if command -v nginx &>/dev/null; then
        log_ok "Nginx already installed"
    else
        log_info "Installing Nginx..."
        apt-get install -y -qq nginx >> "$LOG_FILE" 2>&1
        systemctl enable nginx >> "$LOG_FILE" 2>&1
        systemctl start nginx
        log_ok "Nginx installed"
    fi

    # phpMyAdmin
    if [[ -d /usr/share/phpmyadmin ]]; then
        log_ok "phpMyAdmin already installed"
    else
        log_info "Installing phpMyAdmin..."
        export DEBIAN_FRONTEND=noninteractive
        # Pre-configure phpmyadmin to avoid interactive prompts
        echo "phpmyadmin phpmyadmin/dbconfig-install boolean false" | debconf-set-selections 2>/dev/null || true
        echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect none" | debconf-set-selections 2>/dev/null || true
        # Attempt 1: standard install
        if ! apt-get install -y -qq phpmyadmin >> "$LOG_FILE" 2>&1; then
            # Attempt 2: fix deps and retry
            log_warn "phpMyAdmin install failed, fixing deps..."
            apt-get update -qq >> "$LOG_FILE" 2>&1
            dpkg --configure -a >> "$LOG_FILE" 2>&1 || true
            apt-get -f install -y >> "$LOG_FILE" 2>&1 || true
            if ! apt-get install -y phpmyadmin >> "$LOG_FILE" 2>&1; then
                # Attempt 3: download directly
                log_warn "phpMyAdmin apt install failed, downloading manually..."
                local pma_ver="5.2.1"
                local pma_dir="/usr/share/phpmyadmin"
                wget -q "https://files.phpmyadmin.net/phpMyAdmin/${pma_ver}/phpMyAdmin-${pma_ver}-all-languages.tar.gz" -O /tmp/pma.tar.gz >> "$LOG_FILE" 2>&1 && {
                    mkdir -p "$pma_dir"
                    tar -xzf /tmp/pma.tar.gz -C "$pma_dir" --strip-components=1
                    mkdir -p "${pma_dir}/tmp"
                    chmod 777 "${pma_dir}/tmp"
                    cp "${pma_dir}/config.sample.inc.php" "${pma_dir}/config.inc.php" 2>/dev/null || true
                    # Set blowfish secret
                    local pma_secret
                    pma_secret=$(openssl rand -hex 16)
                    sed -i "s/\$cfg\['blowfish_secret'\] = .*/\$cfg['blowfish_secret'] = '${pma_secret}';/" "${pma_dir}/config.inc.php" 2>/dev/null || true
                    rm -f /tmp/pma.tar.gz
                } || true
            fi
        fi
        if [[ -d /usr/share/phpmyadmin ]]; then
            log_ok "phpMyAdmin installed"
        else
            log_warn "phpMyAdmin installation failed (panel will work without it)"
        fi
    fi

    # Optional: Mail server
    if [[ "$SKIP_MAIL" == false ]]; then
        log_info "Installing mail services (Postfix, Dovecot, OpenDKIM)..."
        apt-get install -y -qq postfix dovecot-core dovecot-imapd dovecot-pop3d \
            opendkim opendkim-tools >> "$LOG_FILE" 2>&1 || true
        log_ok "Mail services installed"
    else
        log_warn "Skipped mail server installation (--skip-mail)"
    fi

    # Optional: DNS server
    if [[ "$SKIP_DNS" == false ]]; then
        log_info "Installing PowerDNS..."
        apt-get install -y -qq pdns-server pdns-backend-mysql >> "$LOG_FILE" 2>&1 || true
        log_ok "PowerDNS installed"
    else
        log_warn "Skipped DNS server installation (--skip-dns)"
    fi

    # Fail2Ban
    if ! command -v fail2ban-client &>/dev/null; then
        log_info "Installing Fail2Ban..."
        if ! apt-get install -y -qq fail2ban >> "$LOG_FILE" 2>&1; then
            log_warn "Fail2Ban install failed, fixing deps and retrying..."
            apt-get update -qq >> "$LOG_FILE" 2>&1
            dpkg --configure -a >> "$LOG_FILE" 2>&1 || true
            apt-get -f install -y >> "$LOG_FILE" 2>&1 || true
            apt-get install -y fail2ban >> "$LOG_FILE" 2>&1 || true
        fi
        if command -v fail2ban-client &>/dev/null; then
            systemctl enable fail2ban >> "$LOG_FILE" 2>&1
            systemctl start fail2ban 2>/dev/null || true
            # Create default jail config
            if [[ ! -f /etc/fail2ban/jail.local ]]; then
                cat > /etc/fail2ban/jail.local <<'F2BEOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
banaction = iptables-multiport
backend = systemd

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600
F2BEOF
                systemctl restart fail2ban 2>/dev/null || true
            fi
            log_ok "Fail2Ban installed and configured"
        else
            log_warn "Fail2Ban installation failed"
        fi
    else
        log_ok "Fail2Ban already installed"
    fi

    # UFW Firewall
    if ! command -v ufw &>/dev/null; then
        log_info "Installing UFW..."
        apt-get install -y -qq ufw >> "$LOG_FILE" 2>&1
        log_ok "UFW installed"
    else
        log_ok "UFW already installed"
    fi

    # ProFTPD (FTP server)
    if ! command -v proftpd &>/dev/null; then
        log_info "Installing ProFTPD..."
        apt-get install -y -qq proftpd-basic >> "$LOG_FILE" 2>&1
        systemctl enable proftpd >> "$LOG_FILE" 2>&1
        systemctl start proftpd
        log_ok "ProFTPD installed"
    else
        log_ok "ProFTPD already installed"
    fi

    # Certbot for SSL
    if ! command -v certbot &>/dev/null; then
        log_info "Installing Certbot..."
        apt-get install -y -qq certbot python3-certbot-nginx >> "$LOG_FILE" 2>&1
        log_ok "Certbot installed"
    fi

    # Restic for backups
    if ! command -v restic &>/dev/null; then
        log_info "Installing Restic..."
        apt-get install -y -qq restic >> "$LOG_FILE" 2>&1 || {
            log_warn "Restic install failed, retrying..."
            apt-get update -qq >> "$LOG_FILE" 2>&1
            apt-get install -y restic >> "$LOG_FILE" 2>&1 || true
        }
        if command -v restic &>/dev/null; then
            log_ok "Restic installed"
        else
            log_warn "Restic installation failed (backup won't work)"
        fi
    else
        log_ok "Restic already installed"
    fi

    # Rclone for remote sync
    if ! command -v rclone &>/dev/null; then
        log_info "Installing Rclone..."
        # Attempt 1: apt install
        if ! apt-get install -y -qq rclone >> "$LOG_FILE" 2>&1; then
            # Attempt 2: official install script
            log_warn "Rclone apt install failed, trying official installer..."
            curl -sL https://rclone.org/install.sh | bash >> "$LOG_FILE" 2>&1 || {
                # Attempt 3: direct download
                log_warn "Rclone official install failed, downloading binary..."
                local rclone_arch="amd64"
                [[ "$(uname -m)" == "aarch64" ]] && rclone_arch="arm64"
                curl -sL "https://downloads.rclone.org/rclone-current-linux-${rclone_arch}.deb" -o /tmp/rclone.deb >> "$LOG_FILE" 2>&1 && {
                    dpkg -i /tmp/rclone.deb >> "$LOG_FILE" 2>&1 || apt-get -f install -y >> "$LOG_FILE" 2>&1
                    rm -f /tmp/rclone.deb
                } || true
            }
        fi
        if command -v rclone &>/dev/null; then
            log_ok "Rclone installed: $(rclone version 2>/dev/null | head -1 || echo 'OK')"
        else
            log_warn "Rclone installation failed (backup remote sync won't work)"
        fi
    else
        log_ok "Rclone already installed"
    fi

    # Supervisor (for compatibility, not used for panel services)
    if ! command -v supervisord &>/dev/null; then
        log_info "Installing Supervisor..."
        apt-get install -y -qq supervisor >> "$LOG_FILE" 2>&1
        systemctl enable supervisor >> "$LOG_FILE" 2>&1
        log_ok "Supervisor installed"
    fi

    # Verification summary
    log_info "Verifying installed services..."
    local all_ok=true
    for pkg_cmd in "mysql:mysql" "redis-cli:redis" "nginx:nginx" "fail2ban-client:fail2ban" \
                   "restic:restic" "rclone:rclone" "proftpd:proftpd" "certbot:certbot"; do
        local cmd="${pkg_cmd%%:*}"
        local label="${pkg_cmd##*:}"
        if command -v "$cmd" &>/dev/null; then
            log_ok "${label}: OK"
        else
            log_warn "${label}: NOT FOUND"
            all_ok=false
        fi
    done
    if [[ -d /usr/share/phpmyadmin ]]; then
        log_ok "phpMyAdmin: OK"
    else
        log_warn "phpMyAdmin: NOT FOUND"
        all_ok=false
    fi
    if [[ "$all_ok" == false ]]; then
        log_warn "Some packages failed to install. Panel may have limited functionality."
        log_warn "Check ${LOG_FILE} for details. You can install missing packages manually."
    fi
}

#-----------------------------------------------------------------------------
# Clone or update VSISPanel source code
#-----------------------------------------------------------------------------
clone_source() {
    step "5/9" "Downloading VSISPanel source code"

    if [[ -d "${PANEL_DIR}/.git" ]]; then
        log_ok "VSISPanel source already exists at ${PANEL_DIR}"
        log_info "Pulling latest changes..."
        git -C "$PANEL_DIR" pull --ff-only >> "$LOG_FILE" 2>&1 || true
        log_ok "Source updated"
    else
        log_info "Cloning VSISPanel from GitHub..."
        # Remove directory if it exists but is not a git repo (e.g. partial download)
        if [[ -d "$PANEL_DIR" ]]; then
            rm -rf "$PANEL_DIR"
        fi
        git clone "$REPO_URL" "$PANEL_DIR" >> "$LOG_FILE" 2>&1
        log_ok "Source cloned to ${PANEL_DIR}"
    fi
}

#-----------------------------------------------------------------------------
# Setup VSISPanel application
#-----------------------------------------------------------------------------
setup_application() {
    step "6/9" "Setting up VSISPanel application"

    if [[ ! -d "$PANEL_DIR" ]]; then
        log_error "VSISPanel directory not found at ${PANEL_DIR}. Clone failed?"
        exit 1
    fi
    cd "$PANEL_DIR"

    # Environment file
    if [[ ! -f .env ]]; then
        if [[ -f .env.example ]]; then
            cp .env.example .env
            log_ok "Created .env from .env.example"
        elif [[ -f .env.production.example ]]; then
            cp .env.production.example .env
            log_ok "Created .env from .env.production.example"
        else
            log_error ".env.example not found. Is the source code complete?"
            exit 1
        fi
    fi

    # Set production environment
    local server_ip
    server_ip=$(hostname -I | awk '{print $1}')

    sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
    sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
    sed -i "s|^APP_URL=.*|APP_URL=https://${server_ip}:8443|" .env
    sed -i 's/^LOG_LEVEL=.*/LOG_LEVEL=warning/' .env

    # Ensure critical .env settings for production
    sed -i 's/^BROADCAST_CONNECTION=.*/BROADCAST_CONNECTION=reverb/' .env
    sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' .env
    sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=redis/' .env
    sed -i 's/^CACHE_STORE=.*/CACHE_STORE=redis/' .env
    log_ok "Production .env configured"

    # Generate Reverb WebSocket credentials if not set
    if grep -q "^REVERB_APP_ID=$" .env 2>/dev/null || ! grep -q "^REVERB_APP_ID=" .env 2>/dev/null; then
        local reverb_id reverb_key reverb_secret
        reverb_id=$((RANDOM * RANDOM % 900000 + 100000))
        reverb_key=$(openssl rand -hex 16)
        reverb_secret=$(openssl rand -hex 16)

        # Remove existing Reverb lines (may be empty)
        sed -i '/^REVERB_APP_ID/d' .env
        sed -i '/^REVERB_APP_KEY/d' .env
        sed -i '/^REVERB_APP_SECRET/d' .env
        sed -i '/^REVERB_HOST/d' .env
        sed -i '/^REVERB_PORT/d' .env
        sed -i '/^REVERB_SCHEME/d' .env
        sed -i '/^VITE_REVERB_/d' .env

        cat >> .env <<REVERBEOF

REVERB_APP_ID=${reverb_id}
REVERB_APP_KEY=${reverb_key}
REVERB_APP_SECRET=${reverb_secret}
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="\${REVERB_APP_KEY}"
VITE_REVERB_HOST="\${REVERB_HOST}"
VITE_REVERB_PORT="\${REVERB_PORT}"
VITE_REVERB_SCHEME="\${REVERB_SCHEME}"
REVERBEOF
        log_ok "Reverb WebSocket credentials generated"
    fi

    # Set OAuth Proxy Client ID (registered with proxy server)
    local oauth_client_id="vsp_f929aca8527fae26626b7b9340e359dbcb00f39b9b325364"
    if grep -q "^OAUTH_PROXY_CLIENT_ID=" .env 2>/dev/null; then
        sed -i "s|^OAUTH_PROXY_CLIENT_ID=.*|OAUTH_PROXY_CLIENT_ID=${oauth_client_id}|" .env
    else
        # Append after OAUTH_PROXY_URL or at end
        if grep -q "^OAUTH_PROXY_URL=" .env; then
            sed -i "/^OAUTH_PROXY_URL=.*/a OAUTH_PROXY_CLIENT_ID=${oauth_client_id}" .env
        else
            echo "" >> .env
            echo "OAUTH_PROXY_URL=https://app-oauth.vsis.net" >> .env
            echo "OAUTH_PROXY_CLIENT_ID=${oauth_client_id}" >> .env
        fi
    fi
    log_ok "OAuth Proxy Client ID configured"

    # Install PHP dependencies (increase memory limit for low-RAM VPS)
    log_info "Installing PHP dependencies (this may take a few minutes)..."
    if ! COMPOSER_MEMORY_LIMIT=-1 composer install --no-interaction --optimize-autoloader --no-dev >> "$LOG_FILE" 2>&1; then
        log_error "Composer install failed. Last 30 lines of log:"
        tail -30 "$LOG_FILE"
        exit 1
    fi
    log_ok "PHP dependencies installed"

    # Ensure sufficient memory for Node.js build (create swap if needed)
    local total_mem_mb
    total_mem_mb=$(awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo)
    local swap_mb
    swap_mb=$(awk '/SwapTotal/ {printf "%d", $2/1024}' /proc/meminfo)
    if [[ $((total_mem_mb + swap_mb)) -lt 2048 ]]; then
        if [[ ! -f /swapfile ]]; then
            log_info "Low memory detected (${total_mem_mb}MB RAM + ${swap_mb}MB swap). Creating 2GB swap..."
            fallocate -l 2G /swapfile 2>/dev/null || dd if=/dev/zero of=/swapfile bs=1M count=2048 >> "$LOG_FILE" 2>&1
            chmod 600 /swapfile
            mkswap /swapfile >> "$LOG_FILE" 2>&1
            swapon /swapfile >> "$LOG_FILE" 2>&1
            log_ok "Swap created and enabled (2GB)"
        fi
    fi

    # Install Node dependencies & build
    log_info "Installing Node.js dependencies (this may take a few minutes)..."
    export NODE_OPTIONS="--max-old-space-size=1536"
    if ! npm install --no-audit --no-fund >> "$LOG_FILE" 2>&1; then
        log_error "npm install failed. Check ${LOG_FILE} for details."
        exit 1
    fi
    log_ok "Node.js dependencies installed"

    log_info "Building frontend assets (this may take a few minutes)..."
    if ! npm run build >> "$LOG_FILE" 2>&1; then
        # Retry with higher memory limit
        log_warn "Build failed, retrying with higher memory limit..."
        export NODE_OPTIONS="--max-old-space-size=3072"
        if ! npm run build >> "$LOG_FILE" 2>&1; then
            log_error "Frontend build failed. Check ${LOG_FILE} for details."
            exit 1
        fi
    fi
    log_ok "Frontend built"

    # Generate app key
    if grep -q "^APP_KEY=$" .env 2>/dev/null; then
        php artisan key:generate --force
        log_ok "Application key generated"
    fi
}

#-----------------------------------------------------------------------------
# Setup database
#-----------------------------------------------------------------------------
setup_database() {
    step "7/9" "Setting up database"

    cd "$PANEL_DIR"

    local db_name db_user db_pass

    db_name="vsispanel"
    db_user="vsispanel"
    db_pass=$(openssl rand -base64 24 | tr -d '/+=')
    log_info "Generated MySQL password for user '${db_user}'"

    # Create database and user via mysql CLI (uses auth_socket as root)
    log_info "Creating database '${db_name}' and user '${db_user}'..."
    mysql <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';
ALTER USER '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';
GRANT ALL PRIVILEGES ON *.* TO '${db_user}'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOSQL
    log_ok "Database '${db_name}' and user '${db_user}' ready"

    # Update .env with correct credentials (ensure host is 127.0.0.1 for TCP)
    sed -i "s/^DB_HOST=.*/DB_HOST=127.0.0.1/" .env
    sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${db_name}/" .env
    sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${db_user}/" .env
    sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${db_pass}/" .env
    log_ok "Updated .env with database credentials"

    # Clear any cached config (critical: old cache may have wrong credentials)
    php artisan config:clear >> "$LOG_FILE" 2>&1 || true
    rm -f bootstrap/cache/config.php
    log_ok "Config cache cleared"

    # Small delay to ensure MySQL privileges are fully flushed
    sleep 1

    # Run migrations (this also validates the DB connection)
    log_info "Running migrations..."
    if ! php artisan migrate --force >> "$LOG_FILE" 2>&1; then
        log_error "Migrations failed. Last 20 lines of log:"
        tail -20 "$LOG_FILE"
        log_warn ".env database values:"
        grep "^DB_" .env || true
        exit 1
    fi
    log_ok "Migrations complete"

    # Admin email and password
    local admin_email="admin@vsispanel.local"
    local admin_pass=""

    if [[ "$NON_INTERACTIVE" == false ]]; then
        echo ""
        read -rp "Admin email [admin@vsispanel.local]: " input_email
        if [[ -n "$input_email" ]]; then
            admin_email="$input_email"
        fi

        read -rp "Admin password (leave blank for random): " input_pass
        if [[ -n "$input_pass" ]]; then
            admin_pass="$input_pass"
        fi
    fi

    if [[ -z "$admin_pass" ]]; then
        admin_pass=$(openssl rand -base64 12 | tr -d '/+=')
    fi
    ADMIN_PASS="$admin_pass"
    ADMIN_EMAIL="$admin_email"

    sed -i '/^ADMIN_PASSWORD=/d' .env
    sed -i '/^ADMIN_EMAIL=/d' .env
    echo "ADMIN_EMAIL=${ADMIN_EMAIL}" >> .env
    echo "ADMIN_PASSWORD=${ADMIN_PASS}" >> .env

    # Run seeders (includes roles, admin, plans, alert templates, app templates)
    log_info "Running seeders..."
    if ! php artisan db:seed --force >> "$LOG_FILE" 2>&1; then
        log_error "Seeding failed. Last 20 lines of log:"
        tail -20 "$LOG_FILE"
        exit 1
    fi
    log_ok "Seeding complete"

    # Remove admin credentials from .env (no longer needed)
    sed -i '/^ADMIN_PASSWORD=/d' .env
    sed -i '/^ADMIN_EMAIL=/d' .env
}

#-----------------------------------------------------------------------------
# Configure system
#-----------------------------------------------------------------------------
configure_system() {
    step "8/9" "Configuring system"

    cd "$PANEL_DIR"

    # Setup sudoers for www-data (PHP-FPM needs system access for hosting management)
    log_info "Configuring sudoers for panel..."
    cat > /etc/sudoers.d/vsispanel << 'SUDOEOF'
# VSISPanel sudo permissions
# Hosting panel requires system access for service/user/file management
# Security is enforced by the application's allowed_commands whitelist and RBAC
www-data ALL=(ALL) NOPASSWD: ALL
SUDOEOF
    chmod 440 /etc/sudoers.d/vsispanel
    log_ok "Sudoers configured for www-data"

    # Permissions (www-data needs write access for logs, cache, sessions)
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache
    log_ok "Permissions set"

    # Storage link
    if [[ ! -L public/storage ]]; then
        php artisan storage:link
        log_ok "Storage link created"
    fi

    # Directories
    mkdir -p /etc/rclone /var/backups/vsispanel "$LOG_DIR"
    chmod 700 /etc/rclone /var/backups/vsispanel
    touch /etc/rclone/rclone.conf
    chown root:www-data /etc/rclone/rclone.conf
    chmod 660 /etc/rclone/rclone.conf
    log_ok "Directories created"

    # Tune MySQL for low memory VPS (< 4GB RAM)
    local total_ram
    total_ram=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
    if [[ $total_ram -lt 4096 ]]; then
        log_info "Tuning MySQL for low-memory server (${total_ram}MB RAM)..."
        local mysql_tune="/etc/mysql/mysql.conf.d/99-vsispanel-tune.cnf"
        cat > "$mysql_tune" <<'MYSQLEOF'
[mysqld]
# VSISPanel low-memory tuning
innodb_buffer_pool_size = 128M
innodb_log_buffer_size = 8M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
key_buffer_size = 8M
max_connections = 50
thread_cache_size = 4
table_open_cache = 200
tmp_table_size = 16M
max_heap_table_size = 16M
sort_buffer_size = 256K
read_buffer_size = 256K
join_buffer_size = 256K
performance_schema = OFF
MYSQLEOF
        systemctl restart mysql >> "$LOG_FILE" 2>&1 || true
        log_ok "MySQL tuned (128MB buffer pool, perf_schema off)"
    fi

    # Tune Redis memory limit
    log_info "Configuring Redis memory limit..."
    if [[ -f /etc/redis/redis.conf ]]; then
        # Set maxmemory based on total RAM
        local redis_max="64mb"
        if [[ $total_ram -gt 4096 ]]; then
            redis_max="128mb"
        fi
        sed -i '/^# maxmemory <bytes>/a maxmemory '"${redis_max}" /etc/redis/redis.conf 2>/dev/null || true
        sed -i '/^# maxmemory-policy/a maxmemory-policy allkeys-lru' /etc/redis/redis.conf 2>/dev/null || true
        systemctl restart redis-server >> "$LOG_FILE" 2>&1 || true
        log_ok "Redis limited to ${redis_max}"
    fi

    # Crontab for Laravel scheduler
    log_info "Setting up crontab..."
    if command -v crontab &>/dev/null; then
        local has_cron
        has_cron=$(crontab -l 2>/dev/null || true)
        if ! echo "$has_cron" | grep -q "schedule:run"; then
            (echo "$has_cron"; echo "* * * * * cd ${PANEL_DIR} && php artisan schedule:run >> /dev/null 2>&1") | crontab - 2>/dev/null || true
            log_ok "Laravel scheduler added to crontab"
        else
            log_ok "Laravel scheduler already in crontab"
        fi
    else
        log_warn "crontab not found, skipping scheduler setup"
    fi

    # Generate self-signed SSL certificate for panel
    log_info "Generating SSL certificate for panel..."
    mkdir -p /etc/ssl/vsispanel
    if [[ ! -f /etc/ssl/vsispanel/panel.crt ]]; then
        openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
            -keyout /etc/ssl/vsispanel/panel.key \
            -out /etc/ssl/vsispanel/panel.crt \
            -subj "/C=VN/ST=HCM/L=HCM/O=VSISPanel/CN=$(hostname -I | awk '{print $1}')" \
            >> "$LOG_FILE" 2>&1
        chmod 600 /etc/ssl/vsispanel/panel.key
        log_ok "Self-signed SSL certificate generated (10 years)"
    else
        log_ok "SSL certificate already exists"
    fi

    # Configure Nginx for panel (includes phpMyAdmin)
    log_info "Configuring Nginx for panel (port 8443)..."
    cp -f "${PANEL_DIR}/deploy/nginx/vsispanel.conf" /etc/nginx/sites-available/vsispanel.conf
    ln -sf /etc/nginx/sites-available/vsispanel.conf /etc/nginx/sites-enabled/vsispanel.conf
    rm -f /etc/nginx/sites-enabled/default
    nginx -t >> "$LOG_FILE" 2>&1
    systemctl reload nginx
    log_ok "Nginx configured on port 8443 (SSL + phpMyAdmin)"

    # Tune PHP-FPM for low memory
    local fpm_pool="/etc/php/8.3/fpm/pool.d/www.conf"
    if [[ -f "$fpm_pool" ]] && [[ $total_ram -lt 4096 ]]; then
        log_info "Tuning PHP-FPM for low-memory server..."
        sed -i 's/^pm = .*/pm = ondemand/' "$fpm_pool"
        sed -i 's/^pm.max_children = .*/pm.max_children = 5/' "$fpm_pool"
        sed -i '/^;pm.process_idle_timeout/s/^;//' "$fpm_pool"
        sed -i 's/^pm.process_idle_timeout = .*/pm.process_idle_timeout = 10s/' "$fpm_pool"
        log_ok "PHP-FPM set to ondemand (max 5, idle timeout 10s)"
    fi

    # Ensure PHP-FPM is running
    systemctl enable php8.3-fpm >> "$LOG_FILE" 2>&1 || true
    systemctl restart php8.3-fpm
    log_ok "PHP-FPM started"

    # Install VSISPanel systemd services (Horizon, Reverb, Terminal)
    # Note: Web is handled by Nginx + PHP-FPM, no separate web service needed
    log_info "Installing panel systemd services..."

    # vsispanel-horizon (Queue worker — required)
    cat > /etc/systemd/system/vsispanel-horizon.service <<'SVCEOF'
[Unit]
Description=VSISPanel Queue Worker (Laravel Horizon)
After=network.target mysql.service redis-server.service
Wants=mysql.service redis-server.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/vsispanel
ExecStart=/usr/bin/php artisan horizon
ExecStop=/usr/bin/php artisan horizon:terminate
Restart=on-failure
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=vsispanel-horizon
Environment=APP_ENV=production

[Install]
WantedBy=multi-user.target
SVCEOF

    # vsispanel-reverb (WebSocket server)
    cat > /etc/systemd/system/vsispanel-reverb.service <<'SVCEOF'
[Unit]
Description=VSISPanel WebSocket Server (Laravel Reverb)
After=network.target mysql.service redis-server.service
Wants=redis-server.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/vsispanel
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=vsispanel-reverb
Environment=APP_ENV=production

[Install]
WantedBy=multi-user.target
SVCEOF

    # vsispanel-terminal (Terminal WebSocket)
    cat > /etc/systemd/system/vsispanel-terminal.service <<'SVCEOF'
[Unit]
Description=VSISPanel Terminal WebSocket Server
After=network.target redis-server.service
Wants=redis-server.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/vsispanel
ExecStart=/usr/bin/node /opt/vsispanel/terminal-server.cjs
Restart=always
RestartSec=5
Environment=NODE_ENV=production
Environment=TERMINAL_PORT=8022
Environment=REDIS_URL=redis://127.0.0.1:6379

[Install]
WantedBy=multi-user.target
SVCEOF

    # Remove old vsispanel-web service if exists (Nginx+PHP-FPM handles web)
    if [[ -f /etc/systemd/system/vsispanel-web.service ]]; then
        systemctl stop vsispanel-web 2>/dev/null || true
        systemctl disable vsispanel-web 2>/dev/null || true
        rm -f /etc/systemd/system/vsispanel-web.service
        log_ok "Removed redundant vsispanel-web service (Nginx handles web)"
    fi

    systemctl daemon-reload
    log_ok "Panel systemd service files installed"

    # Enable and start panel services
    for svc in vsispanel-horizon vsispanel-reverb vsispanel-terminal; do
        systemctl enable "${svc}.service" >> "$LOG_FILE" 2>&1 || true
        systemctl start "${svc}.service" >> "$LOG_FILE" 2>&1 || true
    done

    # Verify panel services started
    sleep 2
    for svc in vsispanel-horizon vsispanel-reverb vsispanel-terminal; do
        if systemctl is-active --quiet "${svc}.service" 2>/dev/null; then
            log_ok "${svc}: running"
        else
            log_warn "${svc}: failed to start (check: journalctl -u ${svc} -n 20)"
        fi
    done

    # Remove any conflicting supervisor configs (systemd handles these services)
    if command -v supervisorctl &>/dev/null && [[ -d /etc/supervisor/conf.d ]]; then
        if ls /etc/supervisor/conf.d/vsispanel-*.conf &>/dev/null 2>&1; then
            log_info "Removing supervisor configs (systemd handles panel services)..."
            supervisorctl stop vsispanel-horizon vsispanel-reverb 2>/dev/null || true
            rm -f /etc/supervisor/conf.d/vsispanel-*.conf
            supervisorctl reread >> "$LOG_FILE" 2>&1 || true
            supervisorctl update >> "$LOG_FILE" 2>&1 || true
            log_ok "Supervisor configs removed (using systemd instead)"
        fi
    fi

    # Open firewall ports if UFW is active
    if command -v ufw &>/dev/null && ufw status | grep -q "active"; then
        ufw allow 22/tcp >> "$LOG_FILE" 2>&1 || true
        ufw allow 80/tcp >> "$LOG_FILE" 2>&1 || true
        ufw allow 443/tcp >> "$LOG_FILE" 2>&1 || true
        ufw allow 8443/tcp >> "$LOG_FILE" 2>&1 || true
        log_ok "Firewall ports opened (22, 80, 443, 8443)"
    fi

    # Optimize (cache config, routes, views)
    log_info "Optimizing application..."
    php artisan config:cache >> "$LOG_FILE" 2>&1 || true
    php artisan route:cache >> "$LOG_FILE" 2>&1 || true
    php artisan view:cache >> "$LOG_FILE" 2>&1 || true
    php artisan event:cache >> "$LOG_FILE" 2>&1 || true
    log_ok "Application optimized (config/route/view/event cached)"

    # Mark as installed
    touch "${PANEL_DIR}/storage/installed"
    log_ok "Installation marker created"
}

#-----------------------------------------------------------------------------
# Print completion
#-----------------------------------------------------------------------------
print_complete() {
    step "9/9" "Installation complete"

    local server_ip
    server_ip=$(hostname -I | awk '{print $1}')

    echo ""
    echo -e "${GREEN}╔══════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║     VSISPanel Installation Complete!         ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "  Panel URL:      ${CYAN}https://${server_ip}:8443${NC}"
    echo -e "  phpMyAdmin:     ${CYAN}https://${server_ip}:8443/phpmyadmin${NC}"
    echo -e "  Admin Email:    ${CYAN}${ADMIN_EMAIL:-admin@vsispanel.local}${NC}"
    echo -e "  Admin Password: ${CYAN}${ADMIN_PASS}${NC}"
    echo ""
    echo -e "  ${YELLOW}⚠  Please change the default password after first login!${NC}"
    echo -e "  ${YELLOW}⚠  The SSL certificate is self-signed. Your browser will show a warning.${NC}"
    echo -e "  ${YELLOW}⚠  Set your email in Settings > SSL before issuing Let's Encrypt certificates.${NC}"
    echo ""
    echo -e "  ${BOLD}Manage Services:${NC}"
    echo -e "    systemctl status vsispanel-horizon"
    echo -e "    systemctl status vsispanel-reverb"
    echo -e "    systemctl status vsispanel-terminal"
    echo ""
    echo -e "  ${BOLD}Update Panel:${NC}"
    echo -e "    cd ${PANEL_DIR} && php artisan vsispanel:update"
    echo ""
    echo -e "  Log file: ${YELLOW}${LOG_FILE}${NC}"
    echo ""
}

#-----------------------------------------------------------------------------
# Main
#-----------------------------------------------------------------------------
main() {
    parse_args "$@"

    mkdir -p "$LOG_DIR"
    echo "VSISPanel Installation - $(date)" > "$LOG_FILE"

    echo ""
    echo -e "${CYAN}╔══════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║     VSISPanel Installer v1.0.0               ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════╝${NC}"
    echo ""

    check_system
    install_dependencies
    install_php
    install_composer
    install_nodejs
    install_services
    clone_source
    setup_application
    setup_database
    configure_system
    print_complete
}

main "$@"
