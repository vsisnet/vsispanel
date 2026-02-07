# VSISPanel

A modern, open-source web hosting control panel built with Laravel and Vue.js.

## Features

- **Domain Management** — Nginx virtual hosts, subdomains, SSL certificates
- **Database Management** — MySQL databases and users with phpMyAdmin
- **Email System** — Postfix/Dovecot email accounts and domains
- **DNS Management** — PowerDNS zones and records
- **SSL/TLS** — Let's Encrypt auto-provisioning and custom certificates
- **FTP Accounts** — ProFTPD/Pure-FTPd management
- **File Manager** — Web-based file browser with upload/download
- **Backup & Restore** — Restic incremental backups with remote sync (S3, FTP, Google Drive, OneDrive, Dropbox)
- **Firewall** — UFW rules and Fail2Ban jail management
- **Monitoring** — Real-time server metrics, service status, configurable alerts
- **Cron Jobs** — Per-user cron job manager
- **App Manager** — One-click installs (WordPress, Laravel, Node.js, etc.)
- **Web Terminal** — Browser-based terminal access
- **Hosting Plans** — Resource quotas with reseller support
- **Multi-language** — Vietnamese and English (extensible)
- **Dark Mode** — System and manual toggle
- **RBAC** — Admin, Reseller, User roles with granular permissions
- **2FA** — TOTP two-factor authentication

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11, PHP 8.3 |
| Frontend | Vue 3, Vite, Tailwind CSS 3, Pinia |
| Database | MySQL 8.0 |
| Cache/Queue | Redis 7, Laravel Horizon |
| WebSocket | Laravel Reverb |
| Web Server | Nginx |
| DNS | PowerDNS |
| Mail | Postfix, Dovecot |
| Backup | Restic, Rclone |
| Firewall | UFW, Fail2Ban |

## Quick Install

```bash
curl -sSL https://raw.githubusercontent.com/vsisnet/vsispanel/main/scripts/install.sh | bash
```

Or manually:

```bash
git clone https://github.com/vsisnet/vsispanel.git /opt/vsispanel
cd /opt/vsispanel
bash scripts/install.sh
```

## System Requirements

- Ubuntu 22.04 or 24.04 LTS
- 2 GB RAM minimum (4 GB recommended)
- 10 GB disk space minimum
- Root access

## Post-Install

Access the panel at `https://your-server-ip:8443`

Default credentials are displayed after installation.

## CLI Commands

```bash
# Optimize for production
php artisan vsispanel:optimize

# Clear all caches
php artisan vsispanel:optimize --clear

# Update panel
php artisan vsispanel:update

# Interactive CLI install
php artisan vsispanel:install
```

## Development

```bash
# Install dependencies
composer install
npm install

# Run dev server
npm run dev

# Build for production
npm run build
```

## License

VSISPanel is open-source software licensed under the [GPL-3.0 License](LICENSE).
