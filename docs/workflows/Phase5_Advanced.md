# Phase 5: Advanced Features (Tuần 25-30)
> **Mục tiêu:** Monitoring real-time, SSH terminal, cron manager, reseller module, advanced file manager, one-click apps.
> **Tiền đề:** Phase 4 hoàn thành — Security, Firewall, Backup hoạt động.

---

## Task 5.1: Real-Time Server Monitoring
**Thời gian:** ~45 phút
**Kiểm tra:** Dashboard hiện live metrics, charts cập nhật real-time

```
>>> PROMPT cho Claude Code:
Tạo module Monitoring với real-time metrics cho VSISPanel:

1. Service: MetricsCollector (app/Modules/Monitoring/Services/MetricsCollector.php)
   Thu thập metrics từ /proc và system commands:

   - collectCpuUsage(): float (percentage 0-100)
     * Parse /proc/stat, tính delta giữa 2 readings
   - collectMemoryUsage(): MemoryMetric (total, used, free, cached, percentage)
     * Parse /proc/meminfo
   - collectDiskUsage(): array of DiskMetric (mount, total, used, free, percentage)
     * Parse `df -h` output
   - collectNetworkUsage(): NetworkMetric (bytes_in, bytes_out, per interface)
     * Parse /proc/net/dev
   - collectLoadAverage(): array [1min, 5min, 15min]
   - collectProcesses(): array [total, running, sleeping, zombie]
   - collectServiceStatuses(): array (mỗi managed service: running/stopped)
   - collectAll(): ServerMetrics DTO (tổng hợp tất cả trên)

2. Model: ServerMetric
   - timestamp, cpu_usage, memory_used, memory_total, disk_usage (JSON)
   - network_in, network_out, load_1m, load_5m, load_15m
   - Index: timestamp (for time-series queries)
   - Retention: auto-delete records older than 90 days

3. Jobs:
   - CollectMetricsJob: chạy mỗi 60 giây (schedule), save to database
   - CleanupOldMetrics: chạy daily, delete records > 90 ngày

4. Real-time via WebSocket (Laravel Reverb):
   - Channel: server-metrics (private, admin only)
   - Event: MetricsUpdated (broadcast mỗi 5 giây)
   - Payload: current CPU, RAM, Disk, Network, Load, Services status

5. Controller: MonitoringController
   - GET /api/v1/monitoring/current - current metrics snapshot
   - GET /api/v1/monitoring/history?period=24h|7d|30d - historical data
   - GET /api/v1/monitoring/services - all services status
   - POST /api/v1/monitoring/services/{service}/restart - restart service
   - GET /api/v1/monitoring/processes - top processes list

6. Per-Domain Metrics Service: DomainMetricsService
   - getBandwidthUsage(Domain $domain, string $period): array
     * Parse Nginx access logs
   - getDiskUsage(Domain $domain): int (du -sb)
   - getVisitorStats(Domain $domain, string $period): array
     * Parse access log: unique IPs, total requests, top pages, status codes

7. Vue pages:
   - MonitoringPage.vue: tabs (Dashboard, Services, Processes, Bandwidth, Alerts)
   
   Dashboard tab:
   - Real-time stat cards: CPU (gauge), RAM (gauge), Disk (bars), Network (speed)
   - Charts (ApexCharts):
     * CPU usage line chart (5-min intervals, 24h default)
     * Memory usage area chart
     * Network I/O dual-line chart (in/out)
     * Disk I/O chart
   - Period selector: 1h, 6h, 24h, 7d, 30d
   - Auto-refresh toggle (WebSocket connection indicator)

   Services tab:
   - Service cards grid:
     * Name, status indicator (green dot/red dot), uptime
     * Actions: Start, Stop, Restart, View Logs
   - Service log viewer: tail -f style with auto-scroll

   Processes tab:
   - Top processes table: PID, user, CPU%, MEM%, command
   - Sort by CPU/MEM
   - Kill process button (admin only)

8. WebSocket setup:
   - Install và configure Laravel Reverb
   - Vue composable: useServerMetrics() - auto-connect WebSocket, reactive data
   - Reconnect logic khi connection lost

9. Tests:
   - Test metrics collection parsing
   - Test time-series query (24h, 7d aggregation)
   - Test WebSocket event broadcasting (mock)
   - Test per-domain bandwidth calculation
```

---

## Task 5.2: Alert System
**Thời gian:** ~30 phút
**Kiểm tra:** Alerts trigger khi threshold exceeded, notifications sent

```
>>> PROMPT cho Claude Code:
Tạo Alert system cho VSISPanel monitoring:

1. Model: AlertRule
   - name, metric (enum: cpu, memory, disk, network, service_down, ssl_expiry, backup_failed)
   - condition (enum: above, below, equals)
   - threshold (float), duration_seconds (int, sustained threshold)
   - notification_channels (JSON: ['email', 'telegram', 'slack', 'discord'])
   - is_active (boolean), cooldown_minutes (int, prevent spam)
   - last_triggered_at

2. Model: AlertHistory
   - alert_rule_id, triggered_at, resolved_at, current_value
   - notification_sent (boolean), notification_channel

3. Service: AlertEvaluator (app/Modules/Monitoring/Services/AlertEvaluator.php)
   - evaluate(): void
     * Load active rules
     * Compare current metrics với thresholds
     * Check duration (sustained)
     * Check cooldown
     * Trigger alerts, send notifications
   - Chạy trong scheduled job mỗi phút

4. Notification Channels:
   - EmailAlertChannel: send email via Laravel Mail
   - TelegramAlertChannel: Telegram Bot API
   - SlackAlertChannel: Slack webhook
   - DiscordAlertChannel: Discord webhook
   - Interface: AlertChannel → send(AlertRule $rule, float $currentValue)

5. Default alert rules (seeder):
   - CPU > 90% for 5 minutes
   - Memory > 90% for 5 minutes
   - Disk > 85%
   - Any service down
   - SSL expiring in 7 days
   - Backup failed

6. Controller: AlertController
   - CRUD /api/v1/monitoring/alerts
   - GET /api/v1/monitoring/alerts/history
   - POST /api/v1/monitoring/alerts/test - send test notification

7. Vue components:
   - AlertsTab.vue: alert rules list, create/edit rule form
   - AlertRuleForm.vue: metric select, condition, threshold slider, channels checkboxes
   - AlertHistoryTable.vue: timeline of triggered alerts
   - NotificationChannelsSettings.vue: configure Telegram bot token, Slack webhook URL, etc.

8. Tests:
   - Test alert evaluation logic
   - Test cooldown prevents duplicate alerts
   - Test duration check (sustained threshold)
   - Test notification channel dispatch
```

---

## Task 5.3: Web SSH Terminal
**Thời gian:** ~35 phút
**Kiểm tra:** SSH terminal accessible qua browser

```
>>> PROMPT cho Claude Code:
Tạo Web SSH Terminal cho VSISPanel sử dụng xterm.js:

1. Backend: SSH WebSocket proxy
   - app/Modules/Server/Services/SshProxyService.php
   - Sử dụng Laravel Reverb WebSocket
   - Authenticate user via Sanctum token
   - Connect to local SSH (127.0.0.1) hoặc remote servers
   - Stream stdin/stdout qua WebSocket
   - Session timeout: 30 minutes idle
   - Audit log: record session start/end, commands (optional, configurable)

2. Controller: SshTerminalController
   - POST /api/v1/terminal/session - create new session, return session_id
   - DELETE /api/v1/terminal/session/{id} - close session
   - WebSocket channel: terminal.{session_id}

3. Alternative approach nếu WebSocket phức tạp:
   - Sử dụng gotty hoặc ttyd (Go-based web terminal)
   - Reverse proxy qua Nginx
   - SSO authentication from panel

4. Vue page: TerminalPage.vue
   - Full-screen terminal (xterm.js)
   - Multi-tab support: open multiple terminal sessions
   - Tab bar: new tab (+), close tab (x), rename tab
   - Terminal settings drawer:
     * Font size slider (10-20px)
     * Font family select (monospace fonts)
     * Color scheme: Dark (default), Light, Monokai, Solarized
     * Cursor style: block/underline/bar
     * Scroll buffer size
   - Toolbar: New Tab, Settings, Fullscreen toggle
   - Connection status indicator
   - Copy/paste support (Ctrl+Shift+C/V)
   - Search in terminal output (Ctrl+Shift+F)

5. Install: npm install xterm xterm-addon-fit xterm-addon-search xterm-addon-web-links

6. Security:
   - Only admin and users with terminal permission
   - Rate limit session creation
   - Session recording option (store commands for audit)
   - Disable for suspended users

7. Tests:
   - Test session creation with authentication
   - Test session timeout
   - Test permission check
```

---

## Task 5.4: Cron Job Manager
**Thời gian:** ~30 phút
**Kiểm tra:** CRUD cron jobs qua panel

```
>>> PROMPT cho Claude Code:
Tạo module Cron cho VSISPanel:

1. Model: CronJob
   - user_id, command, schedule (cron expression)
   - description, is_active (boolean)
   - run_as_user (string, default: the account user)
   - output_handling (enum: discard/email/log)
   - output_email (nullable), log_path (nullable)
   - last_run_at, last_run_status (enum: success/failed/running)
   - last_run_output (text, truncated to 10KB)
   - next_run_at (computed from schedule)

2. Service: CronService (app/Modules/Cron/Services/CronService.php)
   - create(User $user, array $data): CronJob
     * Validate cron expression
     * Write to user's crontab: crontab -u {username}
     * Format: {schedule} {command} >> {log_path} 2>&1
   - update(CronJob $job, array $data): CronJob
   - delete(CronJob $job): void
   - toggle(CronJob $job): void
   - run(CronJob $job): string (execute immediately, return output)
   - syncCrontab(User $user): void (rebuild user crontab from database)
   - parseCronExpression(string $expr): array (human readable next runs)
   - getNextRun(string $expr): Carbon
   - getUserCrontab(User $user): string (current crontab)

3. Controller: CronController
   - CRUD /api/v1/cron-jobs
   - POST /api/v1/cron-jobs/{job}/toggle
   - POST /api/v1/cron-jobs/{job}/run-now
   - GET /api/v1/cron-jobs/{job}/output - last run output

4. Vue page: CronPage.vue
   - DataTable: description, schedule (human readable), command (truncated), status, last run, next run, active toggle, actions
   - Create/Edit modal:
     * Command input
     * Schedule builder: preset buttons (Every Minute, Hourly, Daily, Weekly, Monthly) + custom cron expression input
     * Visual cron builder: minute, hour, day, month, weekday dropdowns
     * Preview: "Next 5 runs" list
     * Output handling: discard / email / log file
     * Description input
   - Run Now button với output modal
   - Last output viewer modal

5. Cài thêm: composer require dragonmantank/cron-expression

6. Tests:
   - Test cron expression validation
   - Test crontab sync
   - Test next run calculation
   - Test run-now execution
```

---

## Task 5.5: Reseller Module
**Thời gian:** ~45 phút
**Kiểm tra:** Reseller có thể tạo customers, plans, view reports

```
>>> PROMPT cho Claude Code:
Tạo Reseller module cho VSISPanel:

1. Reseller features (extend existing modules):

   a) Customer Management:
   - Service: ResellerService
     * createCustomer(User $reseller, array $data): User
       - Tạo user với parent_id = reseller.id
       - Auto-assign reseller's default plan nếu có
       - Create system user (useradd)
     * listCustomers(User $reseller): paginated collection
     * suspendCustomer / unsuspendCustomer / terminateCustomer
     * getResourceUsage(User $reseller): tổng resources sử dụng bởi tất cả customers
   
   b) Reseller Plans:
   - Reseller có resource limits (total disk, bandwidth, domains cho tất cả customers)
   - Reseller tạo plans cho customers trong giới hạn của mình
   - Service: ResellerPlanService
     * validatePlanWithinResellerLimits(User $reseller, array $planData): bool

   c) Custom Branding:
   - Model: ResellerBranding
     * reseller_id, company_name, logo_path, favicon_path
     * primary_color, custom_css
     * support_email, support_url
     * nameservers (JSON: ["ns1.example.com", "ns2.example.com"])
   - Middleware: ApplyResellerBranding
     * Detect reseller từ customer login
     * Apply branding to frontend

   d) Reseller Reports:
   - Service: ResellerReportService
     * getCustomerCount(): int
     * getTotalDiskUsage(): int
     * getTotalBandwidthUsage(): int
     * getResourceDistribution(): array (per customer breakdown)
     * getGrowthReport(string $period): array (new customers over time)

2. Controllers:
   - ResellerCustomerController: CRUD customers
   - ResellerPlanController: CRUD plans (reseller's own)
   - ResellerBrandingController: update branding settings
   - ResellerReportController: view reports

3. Vue pages:
   - ResellerDashboard.vue: customer count, resource usage overview, growth chart
   - ResellerCustomersPage.vue: customer list, create customer, impersonate
   - ResellerPlansPage.vue: manage plans
   - ResellerBrandingPage.vue: logo upload, color picker, nameserver config
   - ResellerReportsPage.vue: usage charts, customer breakdown table

4. User Impersonation:
   - POST /api/v1/admin/impersonate/{user} - admin/reseller logs in as user
   - POST /api/v1/admin/stop-impersonation - return to original user
   - Vue: impersonation banner at top "You are viewing as {username} [Return to admin]"
   - Activity log records impersonation

5. Tests:
   - Test reseller can only manage own customers
   - Test reseller resource limits enforcement
   - Test impersonation flow
   - Test branding application
```

---

## Task 5.6: Advanced File Manager v2
**Thời gian:** ~30 phút
**Kiểm tra:** Code editor, image preview, archive operations

```
>>> PROMPT cho Claude Code:
Nâng cấp File Manager lên v2 cho VSISPanel:

1. Code Editor Enhancement (resources/js/components/file-manager/CodeEditor.vue):
   - CodeMirror 6 integration với:
     * Syntax highlighting: PHP, HTML, CSS, JavaScript, JSON, YAML, XML, SQL, Markdown, Apache/Nginx config, .htaccess, .env
     * Line numbers, code folding
     * Search and replace (Ctrl+H)
     * Auto-indent, bracket matching
     * Dark/Light theme sync với panel theme
     * Minimap (optional)
     * Unsaved changes indicator
     * Save shortcut: Ctrl+S
   - Tab interface: open multiple files in tabs
   - File change detection (warn if file changed externally)

2. Image/Media Preview:
   - ImagePreview.vue: preview panel cho images (jpg, png, gif, svg, webp)
   - Thumbnail generation service (backend)
   - Image info: dimensions, size, type
   - PDF preview (inline iframe)
   - Video preview (HTML5 player) cho mp4, webm

3. Archive Operations Enhancement:
   - CompressModal.vue: chọn files → compress options (zip, tar.gz, tar.bz2) → name → compress
   - ExtractModal.vue: extract preview (list contents) → destination → extract
   - Progress bar cho large archive operations (WebSocket)

4. Additional Features:
   - Clipboard: Cut/Copy/Paste files (virtual clipboard)
   - Drag-and-drop move files between directories
   - Calculate folder size button
   - Batch rename: regex pattern rename
   - File comparison: diff 2 files side-by-side
   - Terminal integration: "Open Terminal Here" button

5. Service additions to FileManagerService:
   - getFilePreview(User $user, string $path): preview data
   - getThumbnail(User $user, string $imagePath, int $size = 100): StreamedResponse
   - compareFiles(User $user, string $path1, string $path2): DiffResult
   - searchFiles(User $user, string $directory, string $pattern): array (find command)
   - calculateSize(User $user, string $path): int (du -sb)

6. Tests:
   - Test syntax detection by extension
   - Test archive list contents
   - Test file search
   - Test thumbnail generation
```

---

## Task 5.7: One-Click App Installer & API Documentation
**Thời gian:** ~35 phút
**Kiểm tra:** Install WordPress qua panel, Swagger docs accessible

```
>>> PROMPT cho Claude Code:
Tạo One-Click App Installer và hoàn thiện API Documentation:

1. One-Click Installer:

   Model: AppTemplate
   - name, slug, description, version, icon_path
   - type (enum: php/nodejs/python/static)
   - requirements (JSON: php_version, extensions, min_disk)
   - install_script (text: bash script template)

   Service: AppInstallerService
   - getAvailableApps(): Collection
   - checkRequirements(Domain $domain, AppTemplate $app): RequirementCheck DTO
   - install(Domain $domain, AppTemplate $app, array $options): void
     Job-based installation:
     a) Check requirements
     b) Download source (git clone / wget)
     c) Configure (wp-config.php, .env, etc.)
     d) Create database if needed
     e) Run install script
     f) Set permissions
     g) Notify user on complete

   App Templates (seeder):
   - WordPress: download, create db, configure wp-config.php, run wp-cli install
   - Laravel: composer create-project, .env setup, key:generate, migrate
   - Joomla: download, create db, configure
   - Drupal: download, create db
   - PrestaShop: download, create db
   - Node.js (Express starter): git clone, npm install, PM2 setup

2. Controller: AppInstallerController
   - GET /api/v1/apps - available apps list
   - POST /api/v1/domains/{domain}/apps/install - install app
   - GET /api/v1/domains/{domain}/apps/install-status - installation progress

3. Vue components:
   - AppMarketplace.vue: app grid (icon, name, description, version, Install button)
   - InstallAppWizard.vue: select domain, options form, progress view

4. API Documentation Completion:
   - Update l5-swagger annotations cho ALL controllers
   - Group by module: Auth, Domains, Databases, Mail, DNS, SSL, Files, FTP, Backup, Firewall, Monitoring, Cron
   - Include request/response examples
   - Authentication section (Sanctum token)
   - Error response documentation
   - Rate limit documentation
   - Accessible at: /api/documentation
   - Export OpenAPI spec: /api/documentation/json

5. CLI Tool placeholder:
   - app/Console/Commands/VsispanelCommand.php
   - Subcommands: domain:list, domain:create, domain:delete, backup:create, user:create
   - Output format: table (default) hoặc JSON (--json flag)

6. Tests:
   - Test app requirements check
   - Test WordPress install flow (mock downloads)
   - Test API documentation generates valid OpenAPI spec
```

---

## Checklist Phase 5 Hoàn Thành

```
>>> PROMPT cho Claude Code:
Verify Phase 5 completion:

1. Monitoring dashboard hiện real-time metrics (mock data OK)
2. Historical charts load 24h/7d data
3. WebSocket connection indicator working
4. Alert rules CRUD, test notification sent
5. SSH terminal opens, connects (mock OK)
6. Multi-tab terminal working
7. Cron job CRUD, cron expression parser working
8. Reseller can create customers
9. Reseller resource limits enforced
10. User impersonation working
11. File Manager code editor with syntax highlighting
12. Image preview working
13. Archive compress/extract working
14. WordPress one-click install flow (mock)
15. Swagger API docs complete and accessible

Chạy: php artisan test
Liệt kê PASS/FAIL và fix.
```

---

## Commit Convention Phase 5
```
feat(monitoring): real-time server metrics with WebSocket
feat(alerts): alert rules with multi-channel notifications
feat(terminal): web SSH terminal with xterm.js
feat(cron): cron job manager with visual builder
feat(reseller): customer management and branding
feat(reseller): impersonation and reports
feat(files): advanced code editor and media preview
feat(apps): one-click app installer with templates
feat(api): complete Swagger/OpenAPI documentation
```
