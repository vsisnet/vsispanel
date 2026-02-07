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
# Parse arguments
#-----------------------------------------------------------------------------
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --skip-mail) SKIP_MAIL=true ;;
            --skip-dns)  SKIP_DNS=true ;;
            --non-interactive) NON_INTERACTIVE=true ;;
            --help)
                echo "Usage: sudo bash install.sh [OPTIONS]"
                echo ""
                echo "Options:"
                echo "  --skip-mail       Skip Postfix/Dovecot installation"
                echo "  --skip-dns        Skip PowerDNS installation"
                echo "  --non-interactive Use default values, no prompts"
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
        log_ok "Swap file created (2GB)"
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
        build-essential python3 make g++ >> "$LOG_FILE" 2>&1
    log_ok "Essential packages installed"
}

#-----------------------------------------------------------------------------
# Install PHP 8.3
#-----------------------------------------------------------------------------
install_php() {
    step "3/9" "Installing PHP 8.3 + Composer + Node.js"

    if command -v php &>/dev/null && php -v | grep -q "8.3"; then
        log_ok "PHP 8.3 already installed"
        return
    fi

    add-apt-repository -y ppa:ondrej/php >> "$LOG_FILE" 2>&1
    apt-get update -qq >> "$LOG_FILE" 2>&1
    apt-get install -y -qq php8.3-fpm php8.3-cli php8.3-common php8.3-mysql \
        php8.3-pgsql php8.3-sqlite3 php8.3-redis php8.3-mbstring php8.3-xml \
        php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-intl \
        php8.3-readline php8.3-soap php8.3-imap php8.3-opcache >> "$LOG_FILE" 2>&1
    log_ok "PHP 8.3 installed: $(php -v | head -1 | awk '{print $2}')"
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
# Install services (MySQL, Redis, Nginx)
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

    # Optional: Mail server
    if [[ "$SKIP_MAIL" == false ]]; then
        log_info "Installing mail services (Postfix, Dovecot)..."
        apt-get install -y -qq postfix dovecot-core dovecot-imapd dovecot-pop3d >> "$LOG_FILE" 2>&1 || true
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

    # Certbot for SSL
    if ! command -v certbot &>/dev/null; then
        log_info "Installing Certbot..."
        apt-get install -y -qq certbot python3-certbot-nginx >> "$LOG_FILE" 2>&1
        log_ok "Certbot installed"
    fi

    # Restic for backups
    if ! command -v restic &>/dev/null; then
        log_info "Installing Restic..."
        apt-get install -y -qq restic >> "$LOG_FILE" 2>&1
        log_ok "Restic installed"
    fi

    # Rclone for remote sync
    if ! command -v rclone &>/dev/null; then
        log_info "Installing Rclone..."
        curl -s https://rclone.org/install.sh | bash >> "$LOG_FILE" 2>&1 || apt-get install -y -qq rclone >> "$LOG_FILE" 2>&1
        log_ok "Rclone installed"
    fi

    # Supervisor for queue workers
    if ! command -v supervisord &>/dev/null; then
        log_info "Installing Supervisor..."
        apt-get install -y -qq supervisor >> "$LOG_FILE" 2>&1
        systemctl enable supervisor >> "$LOG_FILE" 2>&1
        log_ok "Supervisor installed"
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

    # Install PHP dependencies
    log_info "Installing PHP dependencies (this may take a few minutes)..."
    if ! composer install --no-interaction --optimize-autoloader --no-dev >> "$LOG_FILE" 2>&1; then
        log_error "Composer install failed. Check ${LOG_FILE} for details."
        exit 1
    fi
    log_ok "PHP dependencies installed"

    # Install Node dependencies & build
    log_info "Installing Node.js dependencies (this may take a few minutes)..."
    export NODE_OPTIONS="--max-old-space-size=512"
    if ! npm install --no-audit --no-fund >> "$LOG_FILE" 2>&1; then
        log_error "npm install failed. Check ${LOG_FILE} for details."
        log_warn "If out of memory, try adding swap: fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile"
        exit 1
    fi
    log_ok "Node.js dependencies installed"

    log_info "Building frontend assets..."
    if ! npm run build >> "$LOG_FILE" 2>&1; then
        log_error "Frontend build failed. Check ${LOG_FILE} for details."
        exit 1
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
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'localhost';
FLUSH PRIVILEGES;
EOSQL
    log_ok "Database '${db_name}' and user '${db_user}' ready"

    # Update .env with correct credentials
    sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${db_name}/" .env
    sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${db_user}/" .env
    sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${db_pass}/" .env
    log_ok "Updated .env with database credentials"

    # Clear any cached config (critical: old cache may have root user)
    php artisan config:clear >> "$LOG_FILE" 2>&1 || true
    rm -f bootstrap/cache/config.php
    log_ok "Config cache cleared"

    # Verify DB connection before running migrations
    log_info "Testing database connection..."
    if php artisan migrate:status >> "$LOG_FILE" 2>&1; then
        log_ok "Database connection verified"
    else
        log_error "Cannot connect to database. .env values:"
        grep "^DB_" .env || true
        exit 1
    fi

    # Run migrations
    log_info "Running migrations..."
    if ! php artisan migrate --force >> "$LOG_FILE" 2>&1; then
        log_error "Migrations failed. Last 20 lines of log:"
        tail -20 "$LOG_FILE"
        exit 1
    fi
    log_ok "Migrations complete"

    # Run seeders
    log_info "Running seeders..."
    if ! php artisan db:seed --force >> "$LOG_FILE" 2>&1; then
        log_error "Seeding failed. Last 20 lines of log:"
        tail -20 "$LOG_FILE"
        exit 1
    fi
    log_ok "Seeding complete"
}

#-----------------------------------------------------------------------------
# Configure system
#-----------------------------------------------------------------------------
configure_system() {
    step "8/9" "Configuring system"

    cd "$PANEL_DIR"

    # Permissions
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
    chmod 600 /etc/rclone/rclone.conf
    log_ok "Directories created"

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

    # Install supervisor configs
    log_info "Setting up Supervisor configs..."
    if command -v supervisord &>/dev/null && [[ -d /etc/supervisor/conf.d ]]; then
        cp -f "${PANEL_DIR}/deploy/supervisor/vsispanel-horizon.conf" /etc/supervisor/conf.d/ 2>/dev/null || true
        cp -f "${PANEL_DIR}/deploy/supervisor/vsispanel-reverb.conf" /etc/supervisor/conf.d/ 2>/dev/null || true
        supervisorctl reread >> "$LOG_FILE" 2>&1 || true
        supervisorctl update >> "$LOG_FILE" 2>&1 || true
        log_ok "Supervisor configs installed"
    else
        log_warn "Supervisor not found, skipping"
    fi

    # Optimize
    log_info "Optimizing application..."
    php artisan vsispanel:optimize >> "$LOG_FILE" 2>&1 || true
    log_ok "Application optimized"

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
    echo -e "  Admin Email:    ${CYAN}admin@vsispanel.local${NC}"
    echo -e "  Admin Password: ${CYAN}(set during database seeding)${NC}"
    echo ""
    echo -e "  ${BOLD}Next Steps:${NC}"
    echo -e "  1. Edit ${YELLOW}/opt/vsispanel/.env${NC} with your settings"
    echo -e "  2. Open the panel URL and complete the setup wizard"
    echo -e "  3. Change the default admin password"
    echo ""
    echo -e "  ${BOLD}Manage Services:${NC}"
    echo -e "    systemctl status vsispanel-web"
    echo -e "    systemctl status vsispanel-horizon"
    echo -e "    systemctl status vsispanel-reverb"
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
