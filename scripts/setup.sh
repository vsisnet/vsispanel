#!/bin/bash
#=============================================================================
# VSISPanel - Initial Setup Script
# This script performs the complete initial setup of VSISPanel.
# Run this once after cloning the repository on a fresh server.
#
# Usage: sudo bash /opt/vsispanel/scripts/setup.sh
#=============================================================================

set -euo pipefail

PANEL_DIR="/opt/vsispanel"
SCRIPTS_DIR="${PANEL_DIR}/scripts"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info()  { echo -e "${CYAN}[INFO]${NC} $1"; }
log_ok()    { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

#-----------------------------------------------------------------------------
# Check prerequisites
#-----------------------------------------------------------------------------
check_prerequisites() {
    log_info "Checking prerequisites..."

    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root"
        exit 1
    fi

    # Check required software
    local required_cmds=("php" "composer" "node" "npm" "mysql" "redis-cli" "nginx")
    for cmd in "${required_cmds[@]}"; do
        if ! command -v "$cmd" &>/dev/null; then
            log_error "${cmd} is not installed. Please install it first."
            exit 1
        fi
    done

    log_ok "All prerequisites met"
    echo "  PHP:       $(php -v | head -1 | awk '{print $2}')"
    echo "  Node:      $(node -v)"
    echo "  Composer:  $(composer --version 2>/dev/null | awk '{print $3}')"
    echo "  MySQL:     $(mysql --version | awk '{print $3}')"
    echo "  Nginx:     $(nginx -v 2>&1 | awk -F/ '{print $2}')"
    echo "  Redis:     $(redis-cli --version | awk '{print $2}')"
}

#-----------------------------------------------------------------------------
# Setup environment file
#-----------------------------------------------------------------------------
setup_env() {
    log_info "Setting up environment file..."

    if [[ ! -f "${PANEL_DIR}/.env" ]]; then
        if [[ -f "${PANEL_DIR}/.env.example" ]]; then
            cp "${PANEL_DIR}/.env.example" "${PANEL_DIR}/.env"
            log_ok "Created .env from .env.example"
            log_warn "Please edit ${PANEL_DIR}/.env with your database credentials and other settings"
        else
            log_error ".env.example not found. Cannot create .env"
            exit 1
        fi
    else
        log_ok ".env already exists"
    fi
}

#-----------------------------------------------------------------------------
# Install PHP dependencies
#-----------------------------------------------------------------------------
install_php_deps() {
    log_info "Installing PHP dependencies..."
    cd "$PANEL_DIR"
    composer install --no-interaction --optimize-autoloader 2>&1 | tail -3
    log_ok "PHP dependencies installed"
}

#-----------------------------------------------------------------------------
# Install Node dependencies & build frontend
#-----------------------------------------------------------------------------
install_node_deps() {
    log_info "Installing Node.js dependencies..."
    cd "$PANEL_DIR"
    npm install 2>&1 | tail -3
    log_ok "Node.js dependencies installed"

    log_info "Building frontend assets..."
    npm run build 2>&1 | tail -5
    log_ok "Frontend assets built"
}

#-----------------------------------------------------------------------------
# Laravel setup
#-----------------------------------------------------------------------------
setup_laravel() {
    log_info "Running Laravel setup..."
    cd "$PANEL_DIR"

    # Generate app key if not set
    if grep -q "^APP_KEY=$" "${PANEL_DIR}/.env" 2>/dev/null || grep -q "^APP_KEY=base64:$" "${PANEL_DIR}/.env" 2>/dev/null; then
        php artisan key:generate --force
        log_ok "Application key generated"
    else
        log_ok "Application key already set"
    fi

    # Run migrations
    log_info "Running database migrations..."
    php artisan migrate --force 2>&1 | tail -5
    log_ok "Database migrations complete"

    # Run seeders
    log_info "Running database seeders..."
    php artisan db:seed --force 2>&1 | tail -5
    log_ok "Database seeding complete"

    # Clear & cache
    log_info "Optimizing Laravel..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan icons:cache 2>/dev/null || true
    log_ok "Laravel optimized"

    # Storage link
    if [[ ! -L "${PANEL_DIR}/public/storage" ]]; then
        php artisan storage:link
        log_ok "Storage link created"
    fi
}

#-----------------------------------------------------------------------------
# Setup directory permissions
#-----------------------------------------------------------------------------
setup_permissions() {
    log_info "Setting directory permissions..."

    chmod -R 775 "${PANEL_DIR}/storage"
    chmod -R 775 "${PANEL_DIR}/bootstrap/cache"

    # Ensure rclone config directory
    mkdir -p /etc/rclone
    chmod 700 /etc/rclone
    touch /etc/rclone/rclone.conf
    chmod 600 /etc/rclone/rclone.conf

    # Ensure backup directory
    mkdir -p /var/backups/vsispanel
    chmod 700 /var/backups/vsispanel

    log_ok "Permissions set"
}

#-----------------------------------------------------------------------------
# Install systemd services
#-----------------------------------------------------------------------------
install_services() {
    log_info "Installing systemd services..."
    bash "${SCRIPTS_DIR}/install-services.sh"
}

#-----------------------------------------------------------------------------
# Print completion message
#-----------------------------------------------------------------------------
print_complete() {
    local server_ip
    server_ip=$(hostname -I | awk '{print $1}')

    echo ""
    echo -e "${GREEN}============================================${NC}"
    echo -e "${GREEN} VSISPanel Setup Complete!${NC}"
    echo -e "${GREEN}============================================${NC}"
    echo ""
    echo -e "  Panel URL:    ${CYAN}http://${server_ip}:8000${NC}"
    echo -e "  Horizon:      ${CYAN}http://${server_ip}:8000/horizon${NC}"
    echo ""
    echo -e "  Manage services:"
    echo -e "    ${YELLOW}systemctl status vsispanel-web${NC}"
    echo -e "    ${YELLOW}systemctl status vsispanel-horizon${NC}"
    echo -e "    ${YELLOW}systemctl status vsispanel-reverb${NC}"
    echo ""
    echo -e "  View logs:"
    echo -e "    ${YELLOW}journalctl -u vsispanel-web -f${NC}"
    echo -e "    ${YELLOW}journalctl -u vsispanel-horizon -f${NC}"
    echo -e "    ${YELLOW}journalctl -u vsispanel-reverb -f${NC}"
    echo ""
}

#-----------------------------------------------------------------------------
# Main
#-----------------------------------------------------------------------------
main() {
    echo ""
    echo -e "${CYAN}============================================${NC}"
    echo -e "${CYAN} VSISPanel - Initial Setup${NC}"
    echo -e "${CYAN}============================================${NC}"
    echo ""

    check_prerequisites
    setup_env
    install_php_deps
    install_node_deps
    setup_laravel
    setup_permissions
    install_services
    print_complete
}

main "$@"
