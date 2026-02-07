#!/bin/bash
# VSISPanel - Test Cron Job Script
# Usage: /opt/vsispanel/scripts/test-cron.sh
# Log: /var/log/vsispanel/test-cron.log

LOG_DIR="/var/log/vsispanel"
LOG_FILE="$LOG_DIR/test-cron.log"
MAX_LOG_SIZE=1048576 # 1MB

mkdir -p "$LOG_DIR"

# Rotate log if too large
if [ -f "$LOG_FILE" ] && [ "$(stat -f%z "$LOG_FILE" 2>/dev/null || stat -c%s "$LOG_FILE" 2>/dev/null)" -gt "$MAX_LOG_SIZE" ]; then
    mv "$LOG_FILE" "$LOG_FILE.old"
fi

{
    echo "----------------------------------------"
    echo "Test Cron - $(date '+%Y-%m-%d %H:%M:%S')"
    echo "----------------------------------------"
    echo "Hostname : $(hostname)"
    echo "Uptime   : $(uptime -p 2>/dev/null || uptime)"
    echo "Load     : $(cat /proc/loadavg 2>/dev/null | cut -d' ' -f1-3)"
    echo "Memory   : $(free -h 2>/dev/null | awk '/^Mem:/{print $3 "/" $2 " used"}')"
    echo "Disk /   : $(df -h / 2>/dev/null | awk 'NR==2{print $3 "/" $2 " (" $5 " used)"}')"
    echo "[OK] Cron job executed successfully."
    echo ""
} >> "$LOG_FILE" 2>&1

# Output to stdout for cron capture
tail -n 10 "$LOG_FILE"
