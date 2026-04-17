# VSISPanel - Web Hosting Control Panel

## Mô tả dự án
VSISPanel là web hosting control panel tương tự Plesk Panel, viết bằng PHP/Laravel với giao diện CloudStack-inspired.

## Tech Stack Bắt Buộc
- **Backend:** Laravel 11.x, PHP 8.3+, MySQL 8.0, Redis 7.x
- **Frontend:** Vue 3 (Composition API) + Vite + Tailwind CSS 3 + Pinia
- **UI Style:** CloudStack-inspired (fixed sidebar 260px, top navbar, card-based dashboard)
- **i18n:** Vue I18n (multilingual support - vi, en default)
- **API:** RESTful với Laravel Sanctum, JSON Resources
- **Queue:** Laravel Horizon + Redis
- **WebSocket:** Laravel Reverb
- **Testing:** Pest PHP + Cypress

## Kiến Trúc Module (23 modules)

```
app/Modules/
├── AppManager/    # One-click service installer + multi-version runtime management (ĐÃ CODE)
├── Auth/          # Authentication, RBAC, 2FA
├── Backup/        # Simple backup flow (tar+mysqldump+rclone, KHÔNG dùng Restic)
├── Base/          # Base API controller & utilities
├── Cron/          # Cron job manager
├── DNS/           # PowerDNS zone/record management
├── Database/      # MySQL database/user management
├── Domain/        # Domain, subdomain management
├── FTP/           # ProFTPD/Pure-FTPd accounts
├── FileManager/   # Web file manager
├── Firewall/      # UFW/Fail2Ban + WAF + Malware Scan
├── Hosting/       # Plans, subscriptions, quotas
├── Mail/          # Postfix/Dovecot + Rspamd spam filter
├── Marketplace/   # Quick Deploy: WordPress/Laravel/Joomla/Drupal/PrestaShop/Express (ĐÃ CODE)
├── Migration/     # Plesk + SSH FULL; cPanel/DirectAdmin/aaPanel STUB (delegate SSH)
├── Monitoring/    # Metrics + 7 Alert Evaluators (Resource, SSL, Service, Fail2Ban, Backup...)
├── Reseller/      # Customer lifecycle + branding + reports (ĐÃ CODE)
├── SSL/           # Let''s Encrypt + custom SSL
├── Security/      # Security Score (0-100, 8 checks) + Audit Log + Fail2Ban mgmt (ĐÃ CODE)
├── Server/        # Server info, SSH terminal, service management
├── Settings/      # System settings + Gmail OAuth (MỘT PHẦN — UI chưa hoàn thiện)
├── Task/          # Background task tracking + polling output (ĐÃ CODE)
└── WebServer/     # Nginx + PHP-FPM vhost management
```

## Cấu Trúc Mỗi Module (Standard)
```
app/Modules/{ModuleName}/
├── Models/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Services/              # Business logic + system commands
├── Policies/
├── Events/ & Listeners/
├── Providers/
│   └── {ModuleName}ServiceProvider.php (REQUIRED)
├── Routes/
│   ├── api.php
│   └── web.php
├── Database/
│   ├── Migrations/
│   ├── Factories/
│   └── Seeders/
├── Config/
└── Tests/
```

## Module Details (Trạng thái thực tế trong code)

### AppManager (hoàn thiện)
- **Controller**: AppManagerController (21 methods)
- **Service**: AppManagerService (20+ methods) — detect/install/uninstall, extension mgmt, config editor, log viewer
- **Jobs**: InstallAppVersionJob, UninstallAppVersionJob, ManagePhpExtensionJob
- **Model**: ManagedApp (slug, category, type single/multi_version, status, active_version)
- **Apps support**:
  - Single-version: Nginx, MySQL, Redis, Postfix, Dovecot, BIND, Fail2Ban, UFW, Composer, Horizon, Reverb, Terminal
  - Multi-version: PHP 7.4-8.4, Node.js 18/20/22, Python 3.10-3.12
- **Frontend**: AppManagerPage.vue, AppManagerDetailPage.vue

### Auth
- RBAC với 3 roles: admin, reseller, user (Spatie Permission)
- 2FA (TOTP), Sanctum API tokens, login throttling, password reset

### Backup (Updated 2026-04-01)
- **Flow**: mysqldump per DB → gzip -1 + nice/ionice (low CPU) → rclone upload → retention
- **Restore**: gunzip | mysql / tar -xf; download from remote nếu không có local
- **Service**: createBackup(), restore(), restoreDatabase(), restoreFiles(), browseSnapshot(), deleteSnapshot(), downloadFromRemote(), applyRetention(), cleanup()
- **Jobs**: BackupJob, RestoreJob (connection: redis-backups, retry_after=12000s)
- **Models**: Backup, BackupConfig, StorageRemote, RestoreOperation
- **Console**: `backup:run-scheduled` (everyMinute, checks next_run_at), `task:cleanup-stuck`

### Firewall
- UFW rules management
- Fail2Ban jails + banned IPs + whitelist
- WAF (WafController, WafService) — Web Application Firewall
- Malware Scan (MalwareScanController, MalwareScanService)

### Marketplace / Quick Deploy (đã code)
- **Controller**: AppInstallerController (4 methods)
- **Service**: AppInstallerService — checkRequirements, install, getInstallationStatus
- **Models**: AppTemplate (requirements json: php_version, extensions, min_disk_mb), AppInstallation (progress tracking)
- **Job**: InstallAppJob — download, dependencies, setup, DB create, config
- **Apps**: WordPress 6.7, Laravel 11.x, Joomla 5.x, Drupal 11.x, PrestaShop 8.x, Express 4.x
- **Frontend**: MarketplacePage.vue

### Migration (STATUS CHECK)
- **Controller**: MigrationController (7 methods)
- **Migrators** (6 files):
  - BaseMigrator.php (252 lines) — Abstract base + helpers
  - **PleskMigrator.php (553 lines) — FULL IMPLEMENTATION** (API + SSH, WordPress detection, psa.shadow auth)
  - **SshMigrator.php (312 lines) — FULL IMPLEMENTATION** (generic Unix, rsync, MySQL dump)
  - CpanelMigrator.php (39 lines) — **STUB** (delegate SshMigrator)
  - DirectAdminMigrator.php (39 lines) — **STUB** (delegate SshMigrator)
  - AaPanelMigrator.php (39 lines) — **STUB** (delegate SshMigrator)
- **Job**: RunMigrationJob
- **Model**: MigrationJob (encrypted credentials, status, progress, logs)
- **TODO**: Implement native API cho Cpanel/DirectAdmin/AaPanel

### Monitoring
- Metrics collection every minute (CollectMetricsJob)
- **7 Alert Evaluators**:
  - ResourceEvaluator (CPU/RAM/Disk thresholds)
  - SslExpiryEvaluator
  - ServiceDownEvaluator
  - SshBruteForceEvaluator
  - BackupFailureEvaluator
  - PanelIntrusionEvaluator
  - (AlertEvaluatorInterface base)
- Webhook alerts → OpenClaw/Alert Bot

### Reseller (đã code)
- **Controllers**: ResellerCustomerController (8 methods), ResellerBrandingController (3 methods), ResellerReportController (3 methods)
- **Services**: ResellerService, ResellerReportService
- **Features**:
  - Customer lifecycle: create, suspend, unsuspend, terminate, impersonate
  - Branding: company_name, logo, primary_color, custom_css, support contacts, nameservers
  - Resource limits per customer: domain/email/database/user limits, disk/bandwidth quotas
  - Reports: overview, growth trends, customer breakdown by status/plan
- **Model**: ResellerBranding + User extended (reseller_id, is_reseller)
- **Frontend**: ResellerPage.vue

### Security (đã code)
- **Controller**: SecurityController (30+ methods)
- **Services**: SecurityScoreService (5-min cache), AuditLogService
- **Security Score (0-100, Grade A-F)** — 8 checks:
  - Firewall: 25 pts (enabled + rules + essential rules)
  - Fail2Ban: 15 pts (running + jails count)
  - WAF: 15 pts (enabled + blocking mode)
  - SSL/TLS: 15 pts (panel HTTPS + cert validity)
  - SSH: 10 pts (root login disabled + KeyAuth + non-default port)
  - Updates: 5 pts (updated recently)
  - Password Policy: 5 pts (2FA enabled)
  - Backups: 10 pts (config active + recent backup)
- **Audit Log**: module, action (CREATE/READ/UPDATE/DELETE/EXECUTE/LOGIN/LOGIN_FAILED/SUSPICIOUS), resource, changes, IP
- **Fail2Ban Full Integration**: jails (create/enable/disable/delete), banned IPs, whitelist, custom jails
- **Frontend**: SecurityPage.vue (73.6KB — comprehensive dashboard)

### Settings (CHƯA HOÀN THIỆN)
- **Controller**: SettingsController (10 methods)
- **Service**: SettingsService (key-value store with dot-notation)
- **Model**: SystemSetting (group, key, value, type boolean/json/string)
- **Implemented**:
  - General settings (timezone, app config)
  - Time sync via NTP (timedatectl)
  - Notifications test (email/telegram/slack/discord/openclaw)
  - **Gmail OAuth2 full flow** (via OAuth Proxy service)
- **TODO**:
  - UI minimal (SettingsPage.vue chỉ 3.6KB)
  - Advanced notification config
  - User management settings
  - Security policies settings
  - Payment/License settings
- **Frontend tabs**: SettingsGeneralTab, SettingsNotificationsTab, SettingsApiTokensTab, SettingsSslTab

### Task (đã code)
- **Controller**: TaskController (13 methods) — list/filter/search, active, recent, stats, cancel, retry, bulkDelete, cleanup, output streaming
- **Service**: TaskService — create, start, updateProgress, appendOutput, complete, fail, cancel, getStatistics, cleanupOldTasks
- **Model**: Task (user_id, type, name, status, progress 0-100, output, related_type/id polymorphic, input_data, started_at, completed_at)
- **Status**: pending / running / completed / failed / cancelled
- **16 Task Types**: backup.create, backup.restore, service.{start,stop,restart,install,uninstall}, ssl.{issue,renew}, dns.sync, system.update, database.{import,export}, file.{upload,extract}, custom
- **Live output**: offset-based polling qua `/output?offset=X` (chưa có broadcasting)
- **Console**: CleanupStuckTasks command
- **Frontend**: TaskPage.vue (28KB)

## Coding Conventions
- PHP: PSR-12, declare(strict_types=1), type hints mọi nơi
- Vue: Composition API + `<script setup>`, Tailwind utility classes
- API Response format chuẩn:
  ```json
  {"success": true, "data": {}, "message": ""}
  {"success": false, "error": {"code": "ERROR_CODE", "message": ""}}
  ```
- Database: snake_case, UUID primary keys, soft deletes, timestamps
- Tên file test: `{Feature}Test.php`, dùng Pest syntax

## Design System (CloudStack-Inspired)
- **Colors:** Primary #1A5276, Secondary #2ECC71, Danger #E74C3C, BG Light #F8F9FA, BG Dark #1A1A2E
- **Layout:** Fixed sidebar (260px collapsible) + Top navbar (56px) + Content area
- **Typography:** Inter font, 14px base
- **Components:** Cards, DataTable, Modal, Drawer, Toast, Breadcrumb, StatusBadge
- **Dark Mode:** CSS variables toggle, localStorage preference
- **i18n:** Vue I18n, support vi/en (default vi), language switcher trong user dropdown, localStorage preference
- **UI Rule**: KHÔNG dùng unicode emoji — dùng SVG icons (heroicons) hoặc plain text

## Quy Tắc Quan Trọng
1. Mỗi module PHẢI có ServiceProvider đăng ký trong config/app.php
2. System commands (nginx, mysql, etc.) PHẢI chạy qua Service layer, KHÔNG trực tiếp trong Controller
3. Mọi config change PHẢI backup trước khi apply
4. Mọi action PHẢI ghi audit log (AuditLogService::log)
5. API endpoints PHẢI có FormRequest validation
6. Vue components PHẢI support dark mode
7. Vue components PHẢI support multilingual (i18n) — KHÔNG hardcode text
8. KHÔNG hardcode paths, dùng config()
9. **Trước khi code method mới, đọc CLAUDE.md này + check code hiện có (grep) xem đã có method tương tự chưa**

## Git & GitHub
- **Repository:** `vsisnet/vsispanel` (private) trên GitHub
- **KHÔNG commit các file sau lên GitHub** (đã thêm vào `.gitignore`):
  - `CLAUDE.md` — Tài liệu nội bộ cho Claude Code
  - `CONTEXT.md` — Context và lịch sử thay đổi
  - `/docs/` — Thư mục tài liệu workflows
  - `.claude/settings.local.json` — Cấu hình local Claude Code

## Workflow Files
Chi tiết từng phase trong thư mục `docs/workflows/`:
- `Phase1_Foundation.md` — Nền tảng (Tuần 1-6)
- `Phase2_WebHosting.md` — Web Hosting Core (Tuần 7-12)
- `Phase3_EmailDNS.md` — Email & DNS (Tuần 13-18)
- `Phase4_Security.md` — Security & Backup (Tuần 19-24)
- `Phase5_Advanced.md` — Advanced Features (Tuần 25-30)
- `Phase6_Launch.md` — Polish & Launch (Tuần 31-36)

## Recent Changes

### 2026-04-17 — Module audit
- Scan toàn bộ 23 modules, update CLAUDE.md với thông tin chính xác từ code
- Xác nhận AppManager/Task/Reseller/Security/Marketplace đã code hoàn thiện
- Settings chưa hoàn thiện (UI minimal, nhiều features TODO)
- Migration: chỉ Plesk + SSH có code đầy đủ; Cpanel/DirectAdmin/AaPanel là stub delegate SshMigrator

### 2026-04-16 — Backup queue fix
- Thêm redis-backups queue connection với retry_after=12000s (> timeout 10800)
- Fix scheduled backup bị MaxAttemptsExceededException khi job chạy > 90s
- Thêm browseSnapshot() method trong BackupService

### 2026-04-15 — Backup simple flow optimization
- gzip dùng `nice -n 19 ionice -c 3 gzip -1` — giảm CPU khi backup
- Thêm deleteSnapshot() method (fix batch delete crash)
- Xóa 200 dòng Restic code cũ trong RestoreJob
- RestoreJob: dùng simple flow output (files_restored, bytes_restored)
- BackupJob: chỉ set completed_at (status đã được set bởi createBackup)

### 2026-04-01 — Migrate Restic → Simple Backup
- Không còn dùng Restic/Borg — chuyển sang simple flow:
  - Database: mysqldump per DB → .sql.gz
  - Files: tar -cf với nice/ionice (low CPU)
  - Upload: rclone to remote storage
  - Restore: gunzip | mysql / tar -xf
- BackupJob: tries=1, timeout=10800 (3h), queue=backups

### 2026-02-27 — PleskMigrator rewrite
- Full Plesk-specific migration (không delegate SshMigrator nữa)
- WordPress auto-detection via wp-config.php
- Plesk admin auth: MYSQL_PWD=$(cat /etc/psa/.psa.shadow)
- Auto-update wp-config.php với DB credentials mới sau migration
