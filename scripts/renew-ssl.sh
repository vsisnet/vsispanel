#!/bin/bash
# VSISPanel - Let's Encrypt SSL Renewal Script
# Runs as any user — uses sudo for certbot + nginx if needed
# Log: /opt/vsispanel/storage/logs/ssl-renew.log

PANEL_DIR="$(cd "$(dirname "$0")/.." && pwd)"
LOG_FILE="$PANEL_DIR/storage/logs/ssl-renew.log"
MAX_LOG_SIZE=5242880 # 5MB

# Rotate log if too large
if [ -f "$LOG_FILE" ] && [ "$(stat -c%s "$LOG_FILE" 2>/dev/null || echo 0)" -gt "$MAX_LOG_SIZE" ]; then
    mv "$LOG_FILE" "$LOG_FILE.old"
fi

# Use sudo if not root
SUDO=""
if [ "$(id -u)" -ne 0 ]; then
    SUDO="sudo"
fi

{
    echo "========================================"
    echo "SSL Renewal - $(date '+%Y-%m-%d %H:%M:%S')"
    echo "========================================"

    # Check certbot exists
    if ! command -v certbot &>/dev/null; then
        echo "[ERROR] certbot not found. Install with: apt install certbot python3-certbot-nginx"
        exit 1
    fi

    # List certificates before renewal
    echo ""
    echo "[INFO] Current certificates:"
    $SUDO certbot certificates 2>&1 | grep -E "(Certificate Name|Expiry Date|Domains)" | sed 's/^/  /'

    # Run renewal
    echo ""
    echo "[INFO] Running certbot renew..."
    RENEW_OUTPUT=$($SUDO certbot renew --deploy-hook "$SUDO systemctl reload nginx" 2>&1)
    RENEW_EXIT=$?

    echo "$RENEW_OUTPUT"

    if [ $RENEW_EXIT -eq 0 ]; then
        if echo "$RENEW_OUTPUT" | grep -q "No renewals were attempted"; then
            echo ""
            echo "[OK] No certificates due for renewal."
        else
            echo ""
            echo "[OK] Renewal completed successfully. Nginx reloaded."

            # Update VsisPanel database with new cert info
            cd "$PANEL_DIR" && php artisan ssl:sync-certificates 2>/dev/null || true
        fi
    else
        echo ""
        echo "[ERROR] Renewal failed with exit code $RENEW_EXIT"
    fi

    echo ""
    echo "Finished at $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""
} >> "$LOG_FILE" 2>&1

# Output to stdout for cron job capture
tail -n 20 "$LOG_FILE"
