# Phase 6: Polish & Launch (Tuần 31-36)
> **Mục tiêu:** Testing toàn diện, optimization, i18n, installer, documentation, launch v1.0.
> **Tiền đề:** Phase 5 hoàn thành — tất cả features đã implement.

---

## Task 6.1: Comprehensive Testing
**Thời gian:** ~2 giờ (ongoing)
**Kiểm tra:** Test coverage > 80%, tất cả tests pass

```
>>> PROMPT cho Claude Code:
Tạo comprehensive test suite cho VSISPanel. Mỗi module cần Unit tests và Feature tests.
Target: >80% code coverage.

1. Unit Tests (test business logic, mock system commands):

   tests/Unit/Modules/Auth/:
   - LoginServiceTest: test login success, failure, rate limiting, 2FA flow
   - TwoFactorServiceTest: test enable, verify, disable
   - RbacTest: test role permissions, canManage scope

   tests/Unit/Modules/Domain/:
   - DomainServiceTest: test create, delete, suspend flow
   - NginxServiceTest: test vhost config generation, SSL config, rollback
   - PhpFpmServiceTest: test pool config, version switch

   tests/Unit/Modules/Database/:
   - DatabaseServiceTest: test create with prefix, grant/revoke
   - PhpMyAdminSsoTest: test token generation and expiry

   tests/Unit/Modules/Mail/:
   - PostfixServiceTest: test config generation
   - DovecotServiceTest: test mailbox operations
   - MailSecurityServiceTest: test SPF/DKIM/DMARC generation

   tests/Unit/Modules/DNS/:
   - PowerDnsServiceTest: test zone creation, record CRUD
   - DnsValidatorTest: test record validation per type

   tests/Unit/Modules/SSL/:
   - SslServiceTest: test certbot flow, cert validation, expiry check

   tests/Unit/Modules/Backup/:
   - BackupServiceTest: test create, restore, retention
   - BackupDestinationTest: test each destination type

   tests/Unit/Modules/Firewall/:
   - FirewallServiceTest: test rule building, essential rules protection
   - Fail2BanServiceTest: test ban/unban, jail config

   tests/Unit/Modules/Monitoring/:
   - MetricsCollectorTest: test /proc parsing
   - AlertEvaluatorTest: test threshold evaluation, cooldown

   tests/Unit/Services/:
   - SystemCommandExecutorTest: test whitelist, arg escaping, timeout
   - QuotaEnforcerTest: test each quota type

2. Feature Tests (HTTP tests, test full API flows):

   tests/Feature/Api/Auth/:
   - LoginTest: POST /api/v1/auth/login with valid/invalid credentials
   - TwoFactorTest: full 2FA enable → login → verify flow
   - ProfileTest: update profile, change password

   tests/Feature/Api/Domains/:
   - DomainCrudTest: full CRUD lifecycle
   - DomainQuotaTest: create beyond limit → 429
   - DomainPermissionTest: user A cannot access user B domains
   - SubdomainTest: CRUD nested under domain

   tests/Feature/Api/Databases/:
   - DatabaseCrudTest: create, grant, delete
   - DatabasePermissionTest: isolation between users

   tests/Feature/Api/Mail/:
   - MailAccountTest: full CRUD
   - ForwardingTest: add/remove forwarding
   - AutoResponderTest: enable/disable

   tests/Feature/Api/DNS/:
   - DnsZoneTest: zone CRUD
   - DnsRecordTest: record CRUD with validation

   tests/Feature/Api/SSL/:
   - SslIssueTest: Let's Encrypt flow
   - SslCustomTest: upload custom cert

   tests/Feature/Api/Backup/:
   - BackupCrudTest: create, restore, delete
   - BackupScheduleTest: scheduled backup config

   tests/Feature/Api/Firewall/:
   - FirewallRuleTest: CRUD rules
   - Fail2BanTest: ban/unban

   tests/Feature/Api/Monitoring/:
   - MetricsTest: current and historical
   - AlertTest: CRUD alert rules

   tests/Feature/Api/Reseller/:
   - ResellerCustomerTest: create/manage customers
   - ResellerImpersonateTest: impersonate and return

3. Chạy coverage report:
   php artisan test --coverage --min=80

4. Fix tất cả failing tests.

5. Tạo test helper traits:
   - WithAuthenticatedUser: login as admin/reseller/user
   - WithMockedSystemCommands: mock all system command execution
   - WithTestDomain: create test domain setup
```

---

## Task 6.2: Performance Optimization
**Thời gian:** ~1 giờ
**Kiểm tra:** Page load < 2s, API response < 200ms

```
>>> PROMPT cho Claude Code:
Tối ưu performance cho VSISPanel:

1. Backend Optimization:

   a) Database Queries:
   - Review N+1 queries: add eager loading (with()) cho tất cả relationships
   - Add database indexes cho commonly queried columns
   - Use chunk() cho large data processing
   - Cache expensive queries:
     * Dashboard stats: cache 60 seconds
     * Plan list: cache 5 minutes
     * Server info: cache 30 seconds
   - Implement query caching middleware cho GET API requests

   b) Redis Caching Strategy:
   - Config cache: php artisan config:cache
   - Route cache: php artisan route:cache
   - View cache: php artisan view:cache
   - Custom cache keys per module:
     * vsispanel:stats:{user_id} → dashboard stats
     * vsispanel:domains:{user_id} → domain list
     * vsispanel:metrics:current → current server metrics
   - Cache tags cho easy invalidation per module
   - Cache warming job after deploy

   c) Queue Optimization:
   - Separate queues: default, backups, monitoring, notifications
   - Horizon config: tuned worker counts per queue
   - Failed job handling và retry logic

2. Frontend Optimization:

   a) Vue.js:
   - Lazy load all route components: () => import('./pages/XXX.vue')
   - Virtual scrolling cho large lists (vue-virtual-scroller)
   - Debounce search inputs (300ms)
   - Memoize expensive computed properties
   - v-once cho static content

   b) Assets:
   - Vite code splitting per route
   - Dynamic imports cho heavy libraries (ApexCharts, CodeMirror, xterm.js)
   - Image lazy loading
   - Preload critical CSS
   - Font preload: Inter font

   c) API Calls:
   - Implement request deduplication (same URL within 100ms)
   - Stale-while-revalidate pattern cho dashboard data
   - Pagination: default 15, max 100
   - Sparse fieldsets: ?fields=id,name,status cho list endpoints

3. Server Optimization:
   - Nginx: gzip compression, browser caching (30 days for assets)
   - PHP-FPM: OPcache enabled, preloading
   - MySQL: query cache, buffer pool size
   - Tạo config template optimized

4. Monitoring:
   - Add Laravel Telescope cho development (disabled in production)
   - Add query time logging (slow query > 100ms)
   - Add API response time header: X-Response-Time

5. Benchmark:
   - Tạo Artisan command: php artisan vsispanel:benchmark
   - Measure: dashboard load, domain list, file manager operations
   - Target: API < 200ms, Page load < 2s

6. Tests:
   - Test cache invalidation khi data changes
   - Test queue processing
   - Benchmark key endpoints
```

---

## Task 6.3: Multi-Language Support (i18n)
**Thời gian:** ~45 phút
**Kiểm tra:** Panel hiển thị đúng bằng Tiếng Việt và English

```
>>> PROMPT cho Claude Code:
Tạo hệ thống đa ngôn ngữ cho VSISPanel:

1. Backend (Laravel):
   - lang/vi/ folder: auth.php, validation.php, pagination.php, passwords.php, vsispanel.php
   - lang/en/ folder: same files
   - vsispanel.php: tất cả custom strings cho panel
     * dashboard.*, domains.*, databases.*, mail.*, dns.*, ssl.*, files.*, backup.*, firewall.*, monitoring.*, cron.*, settings.*, users.*
   - API responses sử dụng __() cho messages
   - Validation messages localized

2. Frontend (Vue i18n):
   - npm install vue-i18n@9
   - resources/js/i18n/index.js: setup Vue I18n
   - resources/js/i18n/locales/vi.json: Vietnamese translations
   - resources/js/i18n/locales/en.json: English translations
   - Structure:
     ```json
     {
       "common": { "save": "Lưu", "cancel": "Hủy", "delete": "Xóa", "create": "Tạo mới", ... },
       "sidebar": { "dashboard": "Tổng quan", "websites": "Websites", "email": "Email", ... },
       "dashboard": { "title": "Tổng quan", "websites": "Websites", ... },
       "domains": { "title": "Quản lý Websites", "add": "Thêm Website", ... },
       ... tất cả modules
     }
     ```
   - Replace tất cả hardcoded strings trong Vue components bằng {{ $t('key') }}

3. Language Switcher:
   - Component: LanguageSwitcher.vue (dropdown trong TopNavbar)
   - Persist trong user profile (locale field)
   - API: PUT /api/v1/auth/profile {locale: 'vi'}
   - Browser detection cho default language

4. Date/Time Localization:
   - Sử dụng dayjs với locale plugins
   - Format dates theo locale
   - Relative time: "2 giờ trước" / "2 hours ago"

5. RTL support placeholder (cho tương lai: Arabic, Hebrew)

6. Tests:
   - Test API responses in both languages
   - Test all keys exist in both locale files (no missing translations)
   - Tạo Artisan command: php artisan vsispanel:check-translations
```

---

## Task 6.4: Installation Wizard
**Thời gian:** ~45 phút
**Kiểm tra:** Fresh server → chạy installer → panel hoạt động

```
>>> PROMPT cho Claude Code:
Tạo Installation Wizard cho VSISPanel:

1. Installer Script: install.sh (bash)
   - One-command install: curl -sSL https://get.vsispanel.com | bash
   - Supported OS: Ubuntu 22.04/24.04, AlmaLinux 9
   - Auto-detect OS version
   - Steps:
     a) Check system requirements (RAM, disk, OS)
     b) Install dependencies: PHP 8.3, Composer, Node.js 20, MySQL 8, Redis, Nginx
     c) Install optional services: Postfix, Dovecot, PowerDNS, ProFTPD, ClamAV, Rspamd
     d) Clone VSISPanel repository
     e) Composer install, npm install, npm run build
     f) Generate .env, create database, run migrations + seeders
     g) Setup Supervisor cho Horizon
     h) Setup systemd service cho Reverb
     i) Configure Nginx reverse proxy cho panel (port 8443)
     j) Generate self-signed SSL cho panel (replaced by Let's Encrypt later)
     k) Setup cron: * * * * * php /opt/vsispanel/artisan schedule:run
     l) Print: admin URL, default credentials, next steps
   - Flags: --skip-mail, --skip-dns, --non-interactive
   - Log: /var/log/vsispanel/install.log
   - Colorized output, progress indicators

2. Web-based Setup Wizard (post-install, first access):
   - Route: /setup (only accessible when setup_completed=false)
   - SetupController: multi-step wizard

   Step 1 - System Check:
   - Check all requirements: PHP version, extensions, MySQL, Redis, Nginx
   - Green checkmarks / Red X for each
   - "Continue" only if all pass

   Step 2 - Database Configuration:
   - MySQL host, port, database name, username, password
   - Test connection button
   - Create database if not exists

   Step 3 - Admin Account:
   - Admin name, email, password
   - Company name
   - Timezone select
   - Language select

   Step 4 - Server Configuration:
   - Server hostname
   - Server IP
   - Panel port (default 8443)
   - Enable/disable: Mail, DNS, FTP
   - Nameservers (ns1, ns2)

   Step 5 - SSL for Panel:
   - Option 1: Let's Encrypt (enter domain pointing to server)
   - Option 2: Self-signed (default)
   - Option 3: Custom certificate upload

   Step 6 - Complete:
   - "Installation Complete!" message
   - Admin login URL
   - Quick start guide links
   - "Go to Dashboard" button
   - Mark setup_completed=true in config

3. Artisan Commands:
   - php artisan vsispanel:install (CLI version of installer)
   - php artisan vsispanel:uninstall (remove all configs, keep data optional)
   - php artisan vsispanel:update (git pull, composer, npm, migrate)

4. Update mechanism:
   - Service: UpdateService
   - Check for updates: compare current version với GitHub releases
   - One-click update from panel (admin only)
   - Backup before update
   - Rollback if update fails

5. Tests:
   - Test system requirements checker
   - Test database connection test
   - Test setup wizard flow
```

---

## Task 6.5: User Documentation
**Thời gian:** ~45 phút
**Kiểm tra:** Documentation site accessible, searchable

```
>>> PROMPT cho Claude Code:
Tạo documentation cho VSISPanel sử dụng VitePress:

1. Setup: npx create-vitepress docs/

2. Documentation structure (docs/):
   
   getting-started/
   - installation.md: system requirements, install script, setup wizard
   - quick-start.md: first domain, first email, first database
   - updating.md: how to update panel

   admin-guide/
   - dashboard.md: overview of dashboard
   - server-management.md: services, PHP versions, server config
   - user-management.md: create users, roles, permissions
   - reseller-guide.md: setup resellers, branding
   - firewall.md: firewall rules, Fail2Ban, WAF
   - backup.md: backup configuration, restore
   - monitoring.md: metrics, alerts, logs
   - settings.md: panel settings, SSL, updates

   user-guide/
   - websites.md: add domain, manage websites
   - email.md: create email, webmail, forwarding
   - databases.md: create database, phpMyAdmin
   - dns.md: DNS zone editor, records
   - ssl.md: SSL certificates, auto-renewal
   - file-manager.md: upload, edit, manage files
   - ftp.md: FTP accounts, connection
   - cron.md: scheduled tasks
   - backup.md: personal backups

   developer/
   - api.md: API overview, authentication
   - api-reference.md: link to Swagger docs
   - plugins.md: plugin development guide
   - contributing.md: how to contribute
   - architecture.md: system architecture overview
   - module-development.md: creating new modules

   faq.md: common questions
   troubleshooting.md: common issues and solutions
   changelog.md: version history

3. Config: docs/.vitepress/config.js
   - Title: "VSISPanel Documentation"
   - Sidebar: auto-generated from folder structure
   - Search: built-in local search
   - Dark mode support
   - Vietnamese và English (i18n)

4. In-panel help:
   - HelpButton.vue component: "?" icon on each page
   - Links to relevant documentation page
   - Contextual help tooltips

5. README.md cho GitHub repository:
   - Project description, screenshot
   - Quick install command
   - Features list
   - Tech stack
   - Contributing guide link
   - License (GPL v3 recommended for hosting panel)
```

---

## Task 6.6: Migration Tool & Plugin Marketplace
**Thời gian:** ~40 phút
**Kiểm tra:** Import from cPanel backup, plugin install

```
>>> PROMPT cho Claude Code:
Tạo Migration Tool và Plugin Marketplace foundation cho VSISPanel:

1. Migration Tool:
   Service: MigrationService

   a) cPanel Migration:
   - importCpanelBackup(string $backupPath): MigrationResult
     * Parse cPanel backup format (tar.gz)
     * Extract: domains, databases, email accounts, DNS zones, SSL certs
     * Map to VSISPanel models
     * Create domains, import databases, setup email, import DNS
     * Import SSL certificates
     * Report: imported/skipped/failed items

   b) Plesk Migration:
   - importPleskBackup(string $backupPath): MigrationResult
     * Similar to cPanel but Plesk format

   c) DirectAdmin Migration:
   - importDirectAdminBackup(string $backupPath): MigrationResult

   Controller: MigrationController
   - POST /api/v1/admin/migrate/upload - upload backup file
   - POST /api/v1/admin/migrate/analyze - analyze backup contents
   - POST /api/v1/admin/migrate/import - run import
   - GET /api/v1/admin/migrate/status - import progress

   Vue: MigrationWizard.vue
   - Step 1: Upload backup file
   - Step 2: Review detected items (domains, databases, emails)
   - Step 3: Select what to import
   - Step 4: Import progress + results

2. Plugin Marketplace Foundation:
   
   Model: Plugin
   - name, slug, description, version, author
   - type (enum: module/theme/integration)
   - status (enum: installed/active/disabled)
   - config (JSON), path

   Service: PluginService
   - install(string $packagePath): Plugin
     * Validate plugin structure (has plugin.json manifest)
     * Extract to /opt/vsispanel/plugins/{slug}/
     * Register ServiceProvider
     * Run plugin migrations
   - uninstall(Plugin $plugin): void
   - enable/disable
   - getMarketplaceList(): array (fetch from remote marketplace API - placeholder)

   Plugin Manifest (plugin.json):
   ```json
   {
     "name": "WordPress Manager",
     "slug": "wordpress-manager",
     "version": "1.0.0",
     "author": "VSISPanel",
     "description": "Advanced WordPress management",
     "type": "module",
     "laravel": { "providers": ["WordPressManagerServiceProvider"] },
     "vue": { "routes": "routes.js", "sidebar": { "icon": "...", "label": "WordPress", "group": "TOOLS" } }
   }
   ```

   Controller: PluginController (admin only)
   - GET /api/v1/admin/plugins - installed plugins
   - POST /api/v1/admin/plugins/install - install from upload
   - PUT /api/v1/admin/plugins/{plugin}/toggle - enable/disable
   - DELETE /api/v1/admin/plugins/{plugin} - uninstall

   Vue: PluginMarketplace.vue
   - Installed plugins list
   - Upload plugin (.zip)
   - Enable/Disable toggle

3. WHMCS Integration Module (as a plugin):
   - Plugin cho billing integration
   - Provisioning: create/suspend/terminate accounts
   - API endpoint cho WHMCS callbacks
   - Documentation cho WHMCS module setup
   - Placeholder implementation

4. Tests:
   - Test cPanel backup parsing (sample backup fixture)
   - Test plugin install/uninstall lifecycle
   - Test plugin manifest validation
```

---

## Task 6.7: Final Polish & Launch Preparation
**Thời gian:** ~1 giờ
**Kiểm tra:** Everything works end-to-end

```
>>> PROMPT cho Claude Code:
Final polish cho VSISPanel v1.0 launch:

1. UI Polish:
   - Review tất cả pages: consistent spacing, alignment, responsiveness
   - Empty states cho tất cả list pages (illustration + message + action)
   - Loading skeletons cho tất cả data-loading sections
   - Error states: friendly error pages (404, 403, 500)
   - Toast notifications cho tất cả CRUD operations
   - Confirm dialogs cho tất cả destructive actions (delete, suspend)
   - Breadcrumbs on all pages
   - Keyboard shortcuts: Ctrl+K (search), Ctrl+N (new), Esc (close modal)
   - Focus management: auto-focus first input in modals
   - Form validation: inline errors, disable submit until valid

2. Security Final Check:
   - CSRF protection on all forms
   - XSS: htmlspecialchars() output, Content-Security-Policy header
   - SQL injection: all queries use Eloquent/bindings
   - File upload: validate MIME types, max size, no executable
   - Rate limiting: all endpoints
   - Session security: httponly, secure, samesite cookies
   - CORS configuration: restrictive
   - Remove Laravel debug mode in production
   - Remove Telescope in production
   - Audit sensitive config exposure

3. Production Configuration:
   - config/vsispanel.php: all production defaults
   - .env.production.example: sample production config
   - Nginx production config template (with caching, compression)
   - Supervisor config cho Horizon workers
   - Systemd service cho Laravel Reverb
   - Logrotate config cho panel logs

4. Version and Branding:
   - Set version: 1.0.0 in config/vsispanel.php
   - About page: version, license, credits, system info
   - Footer: "Powered by VSISPanel v1.0.0"
   - Favicon and logo assets
   - Login page branding

5. Changelog: CHANGELOG.md
   - v1.0.0 release notes: all features

6. License: LICENSE (GPL v3)

7. Final Test Run:
   - Fresh install on clean Ubuntu 24.04 VM
   - Run through entire setup wizard
   - Create admin, reseller, user accounts
   - Test every feature module
   - Test responsive on mobile
   - Test dark mode
   - Run full test suite
   - Performance benchmark

8. Release:
   - Git tag v1.0.0
   - Build production assets: npm run build
   - Create release archive
   - Update documentation site
   - Create demo server (optional)
```

---

## Checklist Phase 6 — FINAL LAUNCH

```
>>> PROMPT cho Claude Code:
FINAL VERIFICATION cho VSISPanel v1.0:

--- Core Features ---
[ ] Login/Logout/2FA works
[ ] RBAC: Admin/Reseller/User permissions correct
[ ] Dashboard shows real data

--- Website Management ---
[ ] Create domain → Nginx vhost → website accessible
[ ] Subdomain management works
[ ] PHP version switching works
[ ] PHP settings per domain works

--- Email ---
[ ] Create mail account works
[ ] SPF/DKIM/DMARC auto-configured
[ ] Forwarding/alias works
[ ] Webmail link works

--- Database ---
[ ] Create database/user works
[ ] phpMyAdmin SSO works
[ ] Backup/restore database works

--- DNS ---
[ ] Zone creation works
[ ] Record CRUD works
[ ] Template apply works

--- SSL ---
[ ] Let's Encrypt issue works (mock)
[ ] Custom cert upload works
[ ] Auto-renewal scheduled

--- File Manager ---
[ ] Browse, upload, edit, delete files
[ ] Code editor with syntax highlighting
[ ] Archive operations

--- Security ---
[ ] Firewall rules CRUD
[ ] Fail2Ban monitoring
[ ] Backup create/restore
[ ] Security score calculated

--- Monitoring ---
[ ] Real-time metrics display
[ ] Historical charts
[ ] Alert rules work

--- Other ---
[ ] Cron manager works
[ ] Web terminal works
[ ] Reseller features work
[ ] API documentation complete
[ ] i18n: Vietnamese and English
[ ] Installation wizard works
[ ] Documentation site built
[ ] All tests pass (>80% coverage)
[ ] Performance: API < 200ms, page load < 2s
[ ] Responsive: mobile, tablet, desktop
[ ] Dark mode works everywhere

Chạy: php artisan test --coverage
Kết quả:
```

---

## Commit Convention Phase 6
```
test: comprehensive test suite with 80%+ coverage
perf: caching, lazy loading, query optimization
feat(i18n): Vietnamese and English language support
feat(installer): one-command install script and setup wizard
docs: VitePress documentation site
feat(migration): cPanel/Plesk/DirectAdmin import tool
feat(plugins): plugin marketplace foundation
chore: production configuration and security hardening
release: VSISPanel v1.0.0
```
