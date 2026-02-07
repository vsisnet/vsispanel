# Changelog

All notable changes to VSISPanel will be documented in this file.

## [1.0.0] - 2026-02-07

### Added

**Core Platform**
- Laravel 11 + Vue 3 SPA architecture with Vite
- Role-based access control (Admin, Reseller, User) with granular permissions
- Two-factor authentication (TOTP)
- Dark mode with localStorage persistence
- Multilingual support (Vietnamese, English) via Vue I18n
- Real-time notifications via Laravel Reverb WebSocket
- Background job processing with Laravel Horizon

**Web Hosting**
- Domain and subdomain management with Nginx virtual hosts
- Hosting plans with resource quotas (disk, bandwidth, domains, databases, email)
- User subscription management with plan assignment
- Reseller module with plan creation and client management

**Databases**
- MySQL database creation and management
- Database user management with privilege control
- phpMyAdmin integration

**Email**
- Postfix/Dovecot email account management
- Email domain configuration
- Mailbox quota management

**DNS**
- PowerDNS zone management
- DNS record CRUD (A, AAAA, CNAME, MX, TXT, NS, SRV, CAA)
- SOA record configuration

**SSL/TLS**
- Let's Encrypt automatic certificate provisioning
- Custom SSL certificate upload
- Auto-renewal via cron

**FTP**
- FTP account management (ProFTPD/Pure-FTPd)
- Directory and quota configuration

**File Manager**
- Web-based file browser with upload/download
- File editing, permissions, archive operations
- Drag-and-drop upload support

**Backup & Restore**
- Restic-based incremental backup system
- Multiple remote storage support (FTP, S3, Google Drive, OneDrive, Dropbox) via Rclone
- Scheduled backups with cron
- Point-in-time restore

**Security**
- UFW firewall rule management
- Fail2Ban jail configuration and monitoring
- ModSecurity WAF toggle
- Security audit logging
- Security headers middleware

**Monitoring & Alerts**
- Real-time server metrics (CPU, RAM, disk, network)
- Service status monitoring (Nginx, MySQL, Redis, PHP-FPM, etc.)
- Configurable alert rules with email/webhook notifications
- Historical metrics with ApexCharts visualization

**Cron Jobs**
- Cron job manager with preset schedules
- Per-user cron management

**App Manager**
- One-click application installer (WordPress, Laravel, Node.js, etc.)
- Application lifecycle management (start, stop, restart, update)
- Marketplace for available applications

**Terminal**
- Web-based terminal emulator
- Secure shell access from browser

**Administration**
- User management with role assignment
- System settings (server, mail, DNS, security, notification)
- Panel optimization command (`php artisan vsispanel:optimize`)
- Installation wizard (web-based and CLI)

**DevOps**
- Production Nginx, Supervisor, and Logrotate configurations
- Automated install script for Ubuntu 22.04/24.04
- Panel update command (`php artisan vsispanel:update`)
