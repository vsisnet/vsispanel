#!/bin/bash
# VSISPanel - Let's Encrypt SSL Renewal Script
# Usage: /opt/vsispanel/scripts/renew-ssl.sh
# Log: /var/log/vsispanel/ssl-renew.log

LOG_DIR="/var/log/vsispanel"
LOG_FILE="$LOG_DIR/ssl-renew.log"
MAX_LOG_SIZE=5242880 # 5MB

mkdir -p "$LOG_DIR"

# Rotate log if too large
if [ -f "$LOG_FILE" ] && [ "$(stat -f%z "$LOG_FILE" 2>/dev/null || stat -c%s "$LOG_FILE" 2>/dev/null)" -gt "$MAX_LOG_SIZE" ]; then
    mv "$LOG_FILE" "$LOG_FILE.old"
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
    certbot certificates 2>&1 | grep -E "(Certificate Name|Expiry Date|Domains)" | sed 's/^/  /'

    # Run renewal
    echo ""
    echo "[INFO] Running certbot renew..."
    RENEW_OUTPUT=$(certbot renew --deploy-hook "systemctl reload nginx" 2>&1)
    RENEW_EXIT=$?

    echo "$RENEW_OUTPUT"

    if [ $RENEW_EXIT -eq 0 ]; then
        if echo "$RENEW_OUTPUT" | grep -q "No renewals were attempted"; then
            echo ""
            echo "[OK] No certificates due for renewal."
        else
            echo ""
            echo "[OK] Renewal completed successfully. Nginx reloaded."
        fi
    else
        echo ""
        echo "[ERROR] Renewal failed with exit code $RENEW_EXIT"
    fi

    echo ""
    echo "Finished at $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""
} >> "$LOG_FILE" 2>&1

# Also output to stdout for cron job output capture
tail -n 20 "$LOG_FILE"
