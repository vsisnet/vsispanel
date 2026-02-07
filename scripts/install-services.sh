#!/bin/bash
#=============================================================================
# VSISPanel - Install & Enable Systemd Services
# This script installs all VSISPanel systemd service files and enables them.
# Run this script during initial setup or after updating service configurations.
#
# Usage: sudo bash /opt/vsispanel/scripts/install-services.sh
#=============================================================================

set -euo pipefail

PANEL_DIR="/opt/vsispanel"
SYSTEMD_DIR="/etc/systemd/system"
PHP_BIN="/usr/bin/php"

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

    if [[ ! -d "$PANEL_DIR" ]]; then
        log_error "VSISPanel directory not found at $PANEL_DIR"
        exit 1
    fi

    if [[ ! -x "$PHP_BIN" ]]; then
        log_error "PHP not found at $PHP_BIN"
        exit 1
    fi

    log_ok "Prerequisites check passed"
}

#-----------------------------------------------------------------------------
# Install systemd service files
#-----------------------------------------------------------------------------
install_service_files() {
    log_info "Installing VSISPanel systemd service files..."

    # --- vsispanel-web.service ---
    cat > "${SYSTEMD_DIR}/vsispanel-web.service" <<'EOF'
[Unit]
Description=VSISPanel Web Server (Laravel)
After=network.target mysql.service redis-server.service
Wants=mysql.service redis-server.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/vsispanel
ExecStart=/usr/bin/php artisan serve --host=0.0.0.0 --port=8000
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=vsispanel-web
Environment=APP_ENV=production
NoNewPrivileges=true
ProtectSystem=strict
ReadWritePaths=/opt/vsispanel/storage /opt/vsispanel/bootstrap/cache /var/log

[Install]
WantedBy=multi-user.target
EOF

    # --- vsispanel-horizon.service ---
    cat > "${SYSTEMD_DIR}/vsispanel-horizon.service" <<'EOF'
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
EOF

    # --- vsispanel-reverb.service ---
    cat > "${SYSTEMD_DIR}/vsispanel-reverb.service" <<'EOF'
[Unit]
Description=VSISPanel WebSocket Server (Laravel Reverb)
After=network.target mysql.service redis-server.service
Wants=redis-server.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/vsispanel
ExecStart=/usr/bin/php artisan reverb:start
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=vsispanel-reverb
Environment=APP_ENV=production
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
EOF

    # --- vsispanel-terminal.service ---
    cat > "${SYSTEMD_DIR}/vsispanel-terminal.service" <<'EOF'
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
EOF

    log_ok "Service files installed"
}

#-----------------------------------------------------------------------------
# Remove legacy service files
#-----------------------------------------------------------------------------
remove_legacy_services() {
    if [[ -f "${SYSTEMD_DIR}/horizon.service" ]]; then
        log_warn "Removing legacy horizon.service (replaced by vsispanel-horizon.service)"
        systemctl stop horizon.service 2>/dev/null || true
        systemctl disable horizon.service 2>/dev/null || true
        rm -f "${SYSTEMD_DIR}/horizon.service"
    fi
}

#-----------------------------------------------------------------------------
# Enable and start services
#-----------------------------------------------------------------------------
enable_services() {
    log_info "Reloading systemd daemon..."
    systemctl daemon-reload

    local services=("vsispanel-web" "vsispanel-horizon" "vsispanel-terminal")

    # Only enable Reverb if the package is installed
    if cd "$PANEL_DIR" && $PHP_BIN artisan list 2>/dev/null | grep -q "reverb:start"; then
        services+=("vsispanel-reverb")
    else
        log_warn "Laravel Reverb not installed, skipping vsispanel-reverb.service"
    fi

    for svc in "${services[@]}"; do
        log_info "Enabling ${svc}.service..."
        systemctl enable "${svc}.service"

        if systemctl is-active --quiet "${svc}.service"; then
            log_info "Restarting ${svc}.service..."
            systemctl restart "${svc}.service"
        else
            log_info "Starting ${svc}.service..."
            systemctl start "${svc}.service"
        fi

        # Verify
        sleep 2
        if systemctl is-active --quiet "${svc}.service"; then
            log_ok "${svc}.service is running"
        else
            log_error "${svc}.service failed to start"
            journalctl -u "${svc}.service" --no-pager -n 5
        fi
    done
}

#-----------------------------------------------------------------------------
# Verify system services
#-----------------------------------------------------------------------------
verify_system_services() {
    log_info "Verifying system services..."

    local system_services=("nginx" "mysql" "redis-server" "php8.3-fpm")

    for svc in "${system_services[@]}"; do
        if systemctl is-enabled --quiet "${svc}" 2>/dev/null; then
            log_ok "${svc} is enabled"
        else
            log_warn "${svc} is NOT enabled, enabling now..."
            systemctl enable "${svc}" 2>/dev/null || log_error "Failed to enable ${svc}"
        fi

        if systemctl is-active --quiet "${svc}"; then
            log_ok "${svc} is running"
        else
            log_warn "${svc} is NOT running, starting..."
            systemctl start "${svc}" 2>/dev/null || log_error "Failed to start ${svc}"
        fi
    done
}

#-----------------------------------------------------------------------------
# Print status summary
#-----------------------------------------------------------------------------
print_summary() {
    echo ""
    echo -e "${CYAN}============================================${NC}"
    echo -e "${CYAN} VSISPanel Services Status Summary${NC}"
    echo -e "${CYAN}============================================${NC}"
    echo ""

    local all_services=("nginx" "mysql" "redis-server" "php8.3-fpm" "vsispanel-web" "vsispanel-horizon" "vsispanel-terminal" "vsispanel-reverb")

    printf "%-25s %-12s %-12s\n" "SERVICE" "ENABLED" "STATUS"
    printf "%-25s %-12s %-12s\n" "-------" "-------" "------"

    for svc in "${all_services[@]}"; do
        local enabled=$(systemctl is-enabled "${svc}" 2>/dev/null || echo "unknown")
        local status=$(systemctl is-active "${svc}" 2>/dev/null || echo "unknown")

        local enabled_color="${RED}"
        [[ "$enabled" == "enabled" ]] && enabled_color="${GREEN}"

        local status_color="${RED}"
        [[ "$status" == "active" ]] && status_color="${GREEN}"

        printf "%-25s ${enabled_color}%-12s${NC} ${status_color}%-12s${NC}\n" "$svc" "$enabled" "$status"
    done

    echo ""
    log_ok "VSISPanel services installation complete!"
    echo ""
}

#-----------------------------------------------------------------------------
# Main
#-----------------------------------------------------------------------------
main() {
    echo ""
    echo -e "${CYAN}============================================${NC}"
    echo -e "${CYAN} VSISPanel - Install Services${NC}"
    echo -e "${CYAN}============================================${NC}"
    echo ""

    check_prerequisites
    remove_legacy_services
    install_service_files
    verify_system_services
    enable_services
    print_summary
}

main "$@"
