# Context - VSISPanel Project

## Tổng quan dự án
- **Thư mục chính**: `/opt/vsispanel`
- **Thư mục bổ sung**: `/opt/vsispanel-workflows`, `/opt`

## Lịch sử thay đổi

### 2026-02-03: Thiết lập ban đầu & Cấu hình

#### 1. Di chuyển files từ vsispanel-workflows
- **Nguồn**: `vsispanel-workflows/` (trong `/opt/vsispanel`)
- **Đích**: `/opt/vsispanel/`
- **Files đã di chuyển**:
  - `CLAUDE.md` - Tài liệu hướng dẫn
  - `docs/` - Thư mục tài liệu

#### 2. Cấu hình Claude Code Permissions
- **File**: `.claude/settings.json`
- **Mục đích**: Cho phép Claude Code thực thi các lệnh mà không cần xác thực liên tục
- **Cấu hình**:
  ```json
  {
    "permissions": {
      "defaultMode": "bypassPermissions",
      "allow": [
        "Edit",
        "Write",
        "Bash",
        "Bash(*)",
        "Read",
        "Glob",
        "Grep"
      ]
    }
  }
  ```
- **Giải thích**:
  - `defaultMode: "bypassPermissions"` - Tự động cho phép tất cả các thao tác
  - `allow` - Danh sách các công cụ được phép sử dụng
  - Lưu ý: Có thể vẫn xuất hiện một số prompts do bug của hệ thống

#### 3. Cập nhật CLAUDE.md - Thêm yêu cầu i18n
- **Thay đổi**:
  - Thêm **Vue I18n** vào Tech Stack
  - Thêm quy tắc: "Vue components PHẢI support multilingual (i18n) - KHÔNG hardcode text"
  - Thêm i18n config vào Design System: support vi/en (default vi), language switcher trong user dropdown
- **Mục đích**: Đảm bảo toàn bộ UI hỗ trợ đa ngôn ngữ từ đầu, tránh phải refactor sau

## Cấu trúc thư mục hiện tại

```
/opt/vsispanel/
├── .claude/
│   └── settings.json          # Cấu hình permissions
├── CLAUDE.md                   # Tài liệu Claude
├── CONTEXT.md                  # File này - ghi chú context
├── docs/                       # Thư mục tài liệu
├── vsispanel-workflows/        # Thư mục cũ (đã trống)
└── VSISPanel_Workflows.zip     # File zip gốc
```

## Ghi chú quan trọng

### Permissions
- Đã cấu hình `.claude/settings.json` để bypass permission prompts
- Nếu vẫn bị hỏi permissions, thử restart VSCode hoặc reload window

### Tài liệu
- Xem `CLAUDE.md` để biết thêm chi tiết về workflows
- Xem `docs/` để xem tài liệu chi tiết

## Phase 1: Foundation Progress

### Task 1.1: Khởi tạo Laravel Project ✅
- Cài đặt PHP 8.3, Composer, Node.js 20
- Tạo Laravel 11 project
- Cài đặt packages: sanctum, horizon, spatie permission/activitylog, pest
- Tạo 16 module structures
- Configure Vite, Tailwind với custom colors

### Task 1.2: Module Autoloader ✅
- Tạo ModuleServiceProvider auto-load modules
- Commands: `module:make`, `module:list`
- Đăng ký trong bootstrap/providers.php

### Task 1.3: Database Schema Core ✅
- Cài đặt MySQL 8.0 và Redis
- Tạo migrations: users, plans, subscriptions, domains, subdomains
- User model với UUID support
- AdminSeeder (admin@vsispanel.local / Quanghuy@@3112)
- PlanSeeder (Starter, Business, Enterprise)
- Fixed: activity_log UUID support (nullableUuidMorphs)

### Task 1.4: Authentication System ✅
- **Controllers:**
  - LoginController: login, verifyTwoFactor, logout (với rate limiting)
  - ProfileController: me, updateProfile, updatePassword
  - TwoFactorController: enable, confirm, disable (với QR code)
- **FormRequests:** LoginRequest, TwoFactorLoginRequest, UpdateProfileRequest, UpdatePasswordRequest
- **Resources:** UserResource, LoginResource
- **Middlewares:** EnsureAccountActive, TrackLastLogin
- **API Routes:** /api/auth/* (login, logout, me, profile, password, 2fa/*)
- **Packages:** google2fa-laravel, bacon-qr-code
- **Fixed:** personal_access_tokens UUID support (uuidMorphs)
- **Tested:** All endpoints working

### Task 1.5: RBAC - Phân Quyền ✅
- **Published spatie/laravel-permission migrations** (với UUID support)
- **RolesAndPermissionsSeeder:**
  - 3 Roles: admin, reseller, user
  - 53 Permissions (server, domains, hosting, databases, mail, dns, ssl, files, ftp, backup, firewall, monitoring, cron, users, reseller)
  - Admin: tất cả permissions
  - Reseller: 31 permissions (quản lý customers)
  - User: 21 permissions (cơ bản)
- **HasRoleHelpers Trait:**
  - isAdmin(), isReseller(), isUser()
  - canManage(User $target), canView(User $target)
  - scopeAccessibleBy(), scopeCustomersOf()
- **CheckModulePermission Middleware:**
  - Auto-detect permission từ route và HTTP method
  - Admin bypass all checks
  - Return 403 JSON nếu không có quyền
- **ModulePolicy Base Class:**
  - Auto check ownership (user_id)
  - Admin bypass via before()
  - manageAll() permission check
- **Sample Policies:** DomainPolicy, DatabasePolicy, BackupPolicy
- **Tests:** 11 tests passed (admin/reseller/user permissions, ownership, scopes)

### Task 1.6: Vue.js Frontend Scaffold ✅
- **Vue Router:** Lazy-loading routes với navigation guards
  - Routes: /login, /dashboard, /websites, /databases, /files, /email, /dns, /ssl, /firewall, /backup, /monitoring, /cron, /users, /settings, /profile
  - Permission check via route meta
- **Pinia Stores:**
  - auth.js: login, logout, verify2FA, fetchUser, hasPermission
  - app.js: sidebarCollapsed, darkMode, locale, toasts, notifications
- **Axios API:** Request/response interceptors, CSRF handling, error handling
- **i18n:** Vue I18n với vi/en locales
- **Layouts:**
  - AppLayout: Sidebar + TopNavbar + Content
  - AuthLayout: Center card design
  - Sidebar: CloudStack-inspired với Heroicons, collapsible
  - TopNavbar: Search, language switcher, dark mode toggle, notifications, user menu
- **UI Components:**
  - VButton, VInput, VCard, VBadge, VModal, VBreadcrumb, VToastContainer, VLoadingSkeleton, VEmptyState, VConfirmDialog
- **Pages:**
  - LoginPage: Email/password form + 2FA step
  - DashboardPage: Stat cards + Quick actions
  - Placeholder pages cho tất cả routes khác
- **Build:** npm run build thành công

### Task 1.7: Dashboard Skeleton ✅
- **Server Module:**
  - SystemInfoService: CPU, Memory, Disk metrics, System info, Services status
  - DashboardController: stats, metrics, activity, system-info, realtime endpoints
  - ServerServiceProvider: API routes với api prefix
- **Dashboard API Endpoints:**
  - `GET /api/dashboard/stats` - Thống kê websites, databases, emails, disk usage
  - `GET /api/dashboard/metrics` - CPU, Memory, Disk metrics với history
  - `GET /api/dashboard/activity` - Activity log gần đây
  - `GET /api/dashboard/system-info` - System info (admin only)
  - `GET /api/dashboard/realtime` - Real-time metrics
- **Frontend:**
  - dashboard.js store: fetchStats, fetchMetrics, fetchActivity, fetchSystemInfo, fetchRealtime
  - DashboardPage.vue: ApexCharts cho CPU/Memory, stat cards, activity list, system info, services status
  - VCard component: thêm headerRight slot
  - i18n: thêm các keys mới cho dashboard
- **ApexCharts:** npm install apexcharts vue3-apexcharts
- **Tests:** 12 tests passed (authentication, stats, metrics, activity, system-info, realtime)

### Task 1.8: System Command Executor ✅
- **CommandResult.php** (DTO): success, exitCode, stdout, stderr, executionTime
- **ServiceStatus.php** (DTO): name, isRunning, uptime, pid, memoryUsage, activeState
- **SystemCommandExecutor.php:**
  - execute(command, args): Thực thi command với whitelist validation
  - executeAsRoot(command, args): Thực thi với sudo
  - Whitelist allowed commands từ config
  - Escape tất cả arguments với escapeshellarg()
  - Timeout support (default 30s)
  - Log commands vào activity log
- **ServiceManager.php:**
  - start/stop/restart/reload(service): Quản lý services via systemctl
  - status(service): Lấy ServiceStatus
  - isRunning(service)/isEnabled(service): Check trạng thái
  - Whitelist allowed services từ config
- **ServerInfoCollector.php:**
  - getOsInfo(): distro, version, kernel, arch
  - getCpuInfo(): model, cores, usage, load
  - getMemoryInfo(): total, used, free, percentage
  - getDiskInfo(): partitions với size, used, free
  - getNetworkInfo(): interfaces, IPs
  - getUptime(), getLoadAverage()
- **Config:** config/vsispanel.php với allowed_commands, allowed_services, paths, rates
- **Logging:** commands channel trong config/logging.php
- **Tests:** 37 tests passed (command whitelist, argument escaping, service management, server info parsing)

### Task 1.9-1.10: Pending

---

## Phase 2: Web Hosting Core Progress

### Task 2.1: Domain Management Module ✅
- **Domain Model:** UUID, SoftDeletes, relationships với User, Subscription, SSL, DNS
- **DomainService:** CRUD operations, validateDomainName, ensureUniqueForUser
- **DomainController:** Full CRUD với policy authorization
- **FormRequests:** CreateDomainRequest, UpdateDomainRequest (domain validation, PHP version)
- **DomainResource:** API response formatting
- **DomainPolicy:** Ownership-based authorization
- **Routes:** /api/v1/domains/*
- **Events:** DomainCreated, DomainDeleted
- **Tests:** 13 tests passed (CRUD, authorization, validation)

### Task 2.2: Nginx Virtual Host Service ✅
- **config/webserver.php:** Nginx paths, PHP-FPM config, SSL settings
- **NginxService:**
  - createVhost(), deleteVhost(), updateVhost()
  - enableSsl(), disableSsl()
  - testConfig(), reload(), restart()
  - getAccessLog(), getErrorLog(), getStatus()
  - Backup/rollback on config test failure
- **Blade Templates:**
  - vhost.blade.php: HTTP vhost với PHP-FPM socket
  - vhost-ssl.blade.php: HTTPS với TLS 1.2/1.3, HSTS
  - vhost-redirect.blade.php: HTTP→HTTPS redirect
- **WebServerServiceProvider:** Đăng ký NginxService, PhpFpmService
- **DomainService Integration:** Auto create/delete vhost khi domain created/deleted
- **Migration:** Thêm username column vào users table
- **Tests:** 13 tests passed (vhost generation, SSL, backup/rollback)

### Task 2.3: PHP-FPM Multi-Version Management ✅
- **PhpFpmService:**
  - createPool(), deletePool()
  - switchVersion(): Change PHP version cho domain
  - updatePhpIni(): Update PHP settings
  - getInstalledVersions(): List installed versions
  - Backup/restore pool config
- **Blade Template:** pool.blade.php với security settings (open_basedir)
- **PhpController:** API endpoints cho PHP management
- **FormRequests:**
  - UpdatePhpVersionRequest: Validate PHP version
  - UpdatePhpSettingsRequest: Validate PHP ini settings
- **Routes:**
  - `GET /api/v1/php/versions`
  - `GET /api/v1/domains/{domain}/php-settings`
  - `PUT /api/v1/domains/{domain}/php-version`
  - `PUT /api/v1/domains/{domain}/php-settings`
- **Vue Component:** PhpSettingsModal.vue với sliders cho memory_limit, upload_max_filesize, etc.
- **i18n:** php.* translations (vi/en)
- **Tests:** 19 tests passed (9 PhpFpmService + 10 PhpApi)

### Task 2.4: MySQL Database Management Module ✅
- **Models:** ManagedDatabase, DatabaseUser (with UUID, SoftDeletes)
- **DatabaseService:**
  - createDatabase(), deleteDatabase()
  - createDatabaseUser(), deleteDatabaseUser()
  - grantAccess(), revokeAccess()
  - changeDatabaseUserPassword()
  - backupDatabase(), restoreDatabase(), importSql()
  - getDatabaseSize(), getDatabaseTables()
  - User prefix: username_dbname, username_dbuser
- **Controllers:** DatabaseController, DatabaseUserController
- **FormRequests:** CreateDatabaseRequest, CreateDatabaseUserRequest, GrantAccessRequest, ImportDatabaseRequest
- **Resources:** ManagedDatabaseResource, DatabaseUserResource (with Collections)
- **Policies:** ManagedDatabasePolicy, DatabaseUserPolicy (ownership-based)
- **Routes:**
  - `GET/POST /api/v1/databases` - List/Create
  - `GET/DELETE /api/v1/databases/{id}` - Show/Delete
  - `GET /api/v1/databases/{id}/size` - Get size
  - `GET /api/v1/databases/{id}/tables` - List tables
  - `POST /api/v1/databases/{id}/backup` - Backup
  - `POST /api/v1/databases/{id}/import` - Import SQL
  - `GET/POST /api/v1/database-users` - List/Create
  - `GET/DELETE /api/v1/database-users/{id}` - Show/Delete
  - `PUT /api/v1/database-users/{id}/password` - Change password
  - `POST /api/v1/database-users/{id}/grant` - Grant access
  - `POST /api/v1/database-users/{id}/revoke` - Revoke access
  - `GET /api/v1/database-users/privileges` - List privileges
- **Factories:** ManagedDatabaseFactory, DatabaseUserFactory
- **Tests:** 28 tests passed (13 Database API + 15 DatabaseUser API)
- **Permissions Added:** databases.edit, database_users.*

### Task 2.5: Hosting Plans & Subscriptions ✅
- **Models:** Plan, Subscription (already existed from Phase 1)
- **Services:**
  - QuotaService: canCreateDomain/Database/Email/FtpAccount, hasExceeded*, getQuotaUsage, enforceQuota
  - PlanService: CRUD operations, activate/deactivate, clonePlan, getAvailablePlans
  - SubscriptionService: createSubscription, changePlan, suspend/unsuspend, cancel, renew, getStatistics
- **Controllers:** PlanController, SubscriptionController
- **FormRequests:** CreatePlanRequest, UpdatePlanRequest, CreateSubscriptionRequest
- **Resources:** PlanResource, PlanCollection, SubscriptionResource, SubscriptionCollection
- **Policies:** PlanPolicy (admin-only), SubscriptionPolicy (ownership-based with admin viewAny)
- **Routes:**
  - Plans: GET/POST/PUT/DELETE /api/v1/plans, POST activate/deactivate/clone
  - Available plans: GET /api/v1/plans/available (any authenticated user)
  - Subscriptions: GET/POST /api/v1/subscriptions, statistics
  - User routes: GET current, quota
  - Admin actions: change-plan, suspend, unsuspend, cancel, renew
- **Permissions Added:** subscriptions.view/create/edit/delete/suspend/renew/manage-all
- **Tests:** 44 tests passed (18 Plan API + 26 Subscription API)

### Task 2.6: SSL Certificate Module ✅
- **Models:** SslCertificate (UUID, SoftDeletes)
- **SslService:**
  - issueLetsEncrypt(): Issue Let's Encrypt certificate via certbot
  - uploadCustomCert(): Upload and validate custom SSL certificates
  - renewCertificate(): Renew Let's Encrypt certificates
  - revokeCertificate(): Revoke and remove certificates
  - getCertificateInfo(): Parse certificate info via openssl
  - processAutoRenewals(): Batch renewal for expiring certs
- **SslController:** Full CRUD + renew, toggle-auto-renew, check-expiry
- **FormRequests:** UploadCustomCertRequest (PEM format validation)
- **Resources:** SslCertificateResource, SslCertificateCollection
- **Policies:** SslCertificatePolicy (ownership via domain relationship)
- **Routes:**
  - `GET /api/v1/ssl` - List certificates
  - `GET /api/v1/ssl/check-expiry` - Get expiring certificates
  - `POST /api/v1/ssl/domains/{domain}/letsencrypt` - Issue Let's Encrypt
  - `POST /api/v1/ssl/domains/{domain}/custom` - Upload custom certificate
  - `GET /api/v1/ssl/{ssl}` - Show certificate
  - `GET /api/v1/ssl/{ssl}/info` - Get certificate details
  - `POST /api/v1/ssl/{ssl}/renew` - Renew certificate
  - `POST /api/v1/ssl/{ssl}/toggle-auto-renew` - Toggle auto-renew
  - `DELETE /api/v1/ssl/{ssl}` - Revoke and delete certificate
- **Jobs:** RenewExpiringCertificates (scheduled job for auto-renewal)
- **Console Commands:** ssl:renew (--days, --sync options)
- **Config:** config/ssl.php (storage paths, Let's Encrypt config, Nginx SSL settings)
- **Factories:** SslCertificateFactory (with states: letsEncrypt, custom, active, pending, failed, expired, expiringSoon, revoked)
- **Permissions Added:** ssl.create, ssl.edit, ssl.delete, ssl.renew, ssl.manage-all
- **Tests:** 26 tests passed (Index, Issue, Upload, Show, Info, Renew, Toggle, Delete, CheckExpiry)
- **Key Fixes:**
  - HasRoleHelpers trait: Changed isAdmin()/isReseller()/isUser() to use hasRole() instead of checking role column
  - SslCertificatePolicy: Compare user_id as strings to handle UUID type differences

### Task 2.7: File Manager v1 ✅
- **FileManagerService:**
  - listDirectory(): List files and directories with metadata
  - getFileContent()/saveFileContent(): Read/write file content
  - createFile()/createDirectory(): Create new files/directories
  - uploadFiles(): Handle file uploads with validation
  - downloadFile(): Download files
  - rename()/copy()/move(): File operations
  - delete()/deleteMultiple(): Delete files/directories
  - compress()/extract(): ZIP archive operations
  - getPermissions()/setPermissions(): Manage file permissions
  - search(): Search files by name
  - getDiskUsage(): Get domain disk usage
- **FileManagerController:** Full file management API
- **FormRequests:**
  - SaveFileRequest, CreateFileRequest, UploadFilesRequest
  - RenameRequest, CopyMoveRequest, DeleteFilesRequest
  - CompressFilesRequest, ExtractArchiveRequest, SetPermissionsRequest
- **Config:** filemanager.php (base path, allowed/blocked extensions, max sizes)
- **Security Features:**
  - Path sanitization to prevent directory traversal
  - Extension whitelist/blacklist
  - File size limits
  - Protected paths that cannot be deleted
  - Domain isolation (users can only access their own domains)
- **Routes:**
  - `GET /api/v1/domains/{domain}/files` - List directory
  - `GET /api/v1/domains/{domain}/files/content` - Get file content
  - `PUT /api/v1/domains/{domain}/files/content` - Save file content
  - `POST /api/v1/domains/{domain}/files/file` - Create file
  - `POST /api/v1/domains/{domain}/files/directory` - Create directory
  - `POST /api/v1/domains/{domain}/files/upload` - Upload files
  - `GET /api/v1/domains/{domain}/files/download` - Download file
  - `POST /api/v1/domains/{domain}/files/rename` - Rename
  - `POST /api/v1/domains/{domain}/files/copy` - Copy
  - `POST /api/v1/domains/{domain}/files/move` - Move
  - `DELETE /api/v1/domains/{domain}/files` - Delete
  - `POST /api/v1/domains/{domain}/files/compress` - Compress to ZIP
  - `POST /api/v1/domains/{domain}/files/extract` - Extract ZIP
  - `GET/POST /api/v1/domains/{domain}/files/permissions` - Get/Set permissions
  - `GET /api/v1/domains/{domain}/files/search` - Search files
  - `GET /api/v1/domains/{domain}/files/disk-usage` - Get disk usage
- **Tests:** 30 tests passed (List, Content, Create, Upload, Rename, Copy, Move, Delete, Compress, Extract, Permissions, Search, DiskUsage, Security)

### Task 2.8: Websites Page UI Complete ✅
- **DomainDetailPage.vue:** Detail page with 8 tabs (Overview, Files, Databases, Email, SSL, DNS, Logs, Settings)
- **Tab Components:**
  - DomainOverviewTab: Quick stats, domain info, subdomains list, quick actions
  - DomainFilesTab: Embedded file browser with toolbar
  - DomainDatabasesTab: List databases, create database modal
  - DomainEmailTab: Placeholder for Phase 3
  - DomainSslTab: SSL status, issue Let's Encrypt, upload custom, manage certificates
  - DomainDnsTab: Placeholder for Phase 3
  - DomainLogsTab: Access/Error log viewer with syntax highlighting
  - DomainSettingsTab: PHP version/settings, Nginx config view, danger zone
- **AddWebsiteWizard.vue:** Multi-step wizard (Domain → PHP → Options → Summary)
- **CreateSubdomainModal.vue:** Create subdomain form
- **Router Update:** Added `/websites/:id` route for domain-detail
- **i18n Translations:**
  - domainDetail.*: All domain detail page translations
  - fileManager.*: File manager translations
  - databases.*: Database management translations
  - email.*: Email translations (placeholder)
  - ssl.*: SSL management translations
  - dns.*: DNS translations (placeholder)
  - logs.*: Log viewer translations
  - websites.wizard.*: Add website wizard translations
- **WebsitesPage.vue:** Updated to use AddWebsiteWizard instead of simple modal

## Key Files

### Authentication
- [LoginController.php](app/Modules/Auth/Http/Controllers/LoginController.php)
- [ProfileController.php](app/Modules/Auth/Http/Controllers/ProfileController.php)
- [TwoFactorController.php](app/Modules/Auth/Http/Controllers/TwoFactorController.php)
- [Auth Routes](app/Modules/Auth/Routes/api.php)

### Database
- [User Model](app/Modules/Auth/Models/User.php)
- [AdminSeeder](database/seeders/AdminSeeder.php)
- [PlanSeeder](database/seeders/PlanSeeder.php)

### Config
- [bootstrap/providers.php](bootstrap/providers.php) - Module registration
- [bootstrap/app.php](bootstrap/app.php) - Middleware aliases

### RBAC
- [RolesAndPermissionsSeeder](database/seeders/RolesAndPermissionsSeeder.php)
- [HasRoleHelpers Trait](app/Modules/Auth/Traits/HasRoleHelpers.php)
- [CheckModulePermission Middleware](app/Modules/Auth/Http/Middleware/CheckModulePermission.php)
- [ModulePolicy Base](app/Modules/Base/Policies/ModulePolicy.php)
- [RBAC Tests](app/Modules/Auth/Tests/Feature/RbacTest.php)

### Frontend
- [Vue App](resources/js/App.vue)
- [Vue Router](resources/js/router/index.js)
- [Auth Store](resources/js/stores/auth.js)
- [App Store](resources/js/stores/app.js)
- [Dashboard Store](resources/js/stores/dashboard.js)
- [i18n Config](resources/js/i18n/index.js)
- [Layouts](resources/js/layouts/)
- [UI Components](resources/js/components/ui/)
- [Pages](resources/js/pages/)

### Dashboard/Server Module
- [DashboardController](app/Modules/Server/Http/Controllers/DashboardController.php)
- [SystemInfoService](app/Modules/Server/Services/SystemInfoService.php)
- [ServerServiceProvider](app/Modules/Server/Providers/ServerServiceProvider.php)
- [Dashboard Routes](app/Modules/Server/Routes/api.php)
- [Dashboard Tests](app/Modules/Server/Tests/Feature/DashboardTest.php)
- [DashboardPage.vue](resources/js/pages/DashboardPage.vue)

### System Command Executor
- [SystemCommandExecutor](app/Services/SystemCommandExecutor.php)
- [ServiceManager](app/Services/ServiceManager.php)
- [ServerInfoCollector](app/Services/ServerInfoCollector.php)
- [CommandResult DTO](app/Services/CommandResult.php)
- [ServiceStatus DTO](app/Services/ServiceStatus.php)
- [VSISPanel Config](config/vsispanel.php)
- [Command Executor Tests](app/Modules/Server/Tests/Unit/SystemCommandExecutorTest.php)
- [Service Manager Tests](app/Modules/Server/Tests/Unit/ServiceManagerTest.php)
- [Server Info Collector Tests](app/Modules/Server/Tests/Unit/ServerInfoCollectorTest.php)

## Server Info
- **IP:** 161.248.184.106:8000
- **Admin:** admin@vsispanel.local / Quanghuy@@3112
- **Database:** MySQL 8.0

### Phase 2 Key Files

#### Domain Module
- [Domain Model](app/Modules/Domain/Models/Domain.php)
- [DomainService](app/Modules/Domain/Services/DomainService.php)
- [DomainController](app/Modules/Domain/Http/Controllers/DomainController.php)
- [Domain Routes](app/Modules/Domain/Routes/api.php)
- [Domain Tests](tests/Feature/Domain/DomainTest.php)

#### WebServer Module
- [NginxService](app/Modules/WebServer/Services/NginxService.php)
- [PhpFpmService](app/Modules/WebServer/Services/PhpFpmService.php)
- [PhpController](app/Modules/WebServer/Http/Controllers/PhpController.php)
- [WebServer Config](config/webserver.php)
- [Nginx Templates](resources/views/templates/nginx/)
- [PHP-FPM Templates](resources/views/templates/php-fpm/)
- [WebServer Routes](app/Modules/WebServer/Routes/api.php)
- [NginxService Tests](tests/Feature/WebServer/NginxServiceTest.php)
- [PhpFpmService Tests](tests/Feature/WebServer/PhpFpmServiceTest.php)

#### Database Module
- [ManagedDatabase Model](app/Modules/Database/Models/ManagedDatabase.php)
- [DatabaseUser Model](app/Modules/Database/Models/DatabaseUser.php)
- [DatabaseService](app/Modules/Database/Services/DatabaseService.php)
- [DatabaseController](app/Modules/Database/Http/Controllers/DatabaseController.php)
- [DatabaseUserController](app/Modules/Database/Http/Controllers/DatabaseUserController.php)
- [Database Routes](app/Modules/Database/Routes/api.php)
- [ManagedDatabaseFactory](app/Modules/Database/Database/Factories/ManagedDatabaseFactory.php)
- [DatabaseUserFactory](app/Modules/Database/Database/Factories/DatabaseUserFactory.php)
- [DatabaseApiTest](tests/Feature/Database/DatabaseApiTest.php)
- [DatabaseUserApiTest](tests/Feature/Database/DatabaseUserApiTest.php)

#### Hosting Module
- [Plan Model](app/Modules/Hosting/Models/Plan.php)
- [Subscription Model](app/Modules/Hosting/Models/Subscription.php)
- [QuotaService](app/Modules/Hosting/Services/QuotaService.php)
- [PlanService](app/Modules/Hosting/Services/PlanService.php)
- [SubscriptionService](app/Modules/Hosting/Services/SubscriptionService.php)
- [PlanController](app/Modules/Hosting/Http/Controllers/PlanController.php)
- [SubscriptionController](app/Modules/Hosting/Http/Controllers/SubscriptionController.php)
- [Hosting Routes](app/Modules/Hosting/Routes/api.php)
- [PlanApiTest](tests/Feature/Hosting/PlanApiTest.php)
- [SubscriptionApiTest](tests/Feature/Hosting/SubscriptionApiTest.php)

#### SSL Module
- [SslCertificate Model](app/Modules/SSL/Models/SslCertificate.php)
- [SslService](app/Modules/SSL/Services/SslService.php)
- [SslController](app/Modules/SSL/Http/Controllers/SslController.php)
- [SslCertificatePolicy](app/Modules/SSL/Policies/SslCertificatePolicy.php)
- [SSL Routes](app/Modules/SSL/Routes/api.php)
- [SSL Config](app/Modules/SSL/Config/ssl.php)
- [SSLServiceProvider](app/Modules/SSL/Providers/SSLServiceProvider.php)
- [RenewExpiringCertificates Job](app/Modules/SSL/Jobs/RenewExpiringCertificates.php)
- [SslCertificateFactory](app/Modules/SSL/Database/Factories/SslCertificateFactory.php)
- [SslApiTest](tests/Feature/SSL/SslApiTest.php)

#### FileManager Module
- [FileManagerService](app/Modules/FileManager/Services/FileManagerService.php)
- [FileManagerController](app/Modules/FileManager/Http/Controllers/FileManagerController.php)
- [FileManager Config](app/Modules/FileManager/Config/filemanager.php)
- [FileManager Routes](app/Modules/FileManager/Routes/api.php)
- [FileManagerServiceProvider](app/Modules/FileManager/Providers/FileManagerServiceProvider.php)
- [FileManagerApiTest](tests/Feature/FileManager/FileManagerApiTest.php)

#### Vue Components
- [PhpSettingsModal](resources/js/components/php/PhpSettingsModal.vue)
- [WebsitesPage](resources/js/pages/WebsitesPage.vue)
- [DomainDetailPage](resources/js/pages/DomainDetailPage.vue)
- [AddWebsiteWizard](resources/js/components/domain/AddWebsiteWizard.vue)
- [CreateSubdomainModal](resources/js/components/domain/CreateSubdomainModal.vue)
- [DomainOverviewTab](resources/js/components/domain/tabs/DomainOverviewTab.vue)
- [DomainFilesTab](resources/js/components/domain/tabs/DomainFilesTab.vue)
- [DomainDatabasesTab](resources/js/components/domain/tabs/DomainDatabasesTab.vue)
- [DomainEmailTab](resources/js/components/domain/tabs/DomainEmailTab.vue)
- [DomainSslTab](resources/js/components/domain/tabs/DomainSslTab.vue)
- [DomainDnsTab](resources/js/components/domain/tabs/DomainDnsTab.vue)
- [DomainLogsTab](resources/js/components/domain/tabs/DomainLogsTab.vue)
- [DomainSettingsTab](resources/js/components/domain/tabs/DomainSettingsTab.vue)

### Phase 3 Key Files

#### Mail Module
- [MailDomain Model](app/Modules/Mail/Models/MailDomain.php)
- [MailAccount Model](app/Modules/Mail/Models/MailAccount.php)
- [MailAlias Model](app/Modules/Mail/Models/MailAlias.php)
- [MailService](app/Modules/Mail/Services/MailService.php)
- [RspamdService](app/Modules/Mail/Services/RspamdService.php)
- [WebmailService](app/Modules/Mail/Services/WebmailService.php)
- [MailDomainController](app/Modules/Mail/Http/Controllers/MailDomainController.php)
- [MailAccountController](app/Modules/Mail/Http/Controllers/MailAccountController.php)
- [SpamController](app/Modules/Mail/Http/Controllers/SpamController.php)
- [WebmailController](app/Modules/Mail/Http/Controllers/WebmailController.php)
- [Mail Routes](app/Modules/Mail/Routes/api.php)
- [Mail Config](config/mail_server.php)
- [Webmail Config](config/webmail.php)

#### DNS Module
- [DnsZone Model](app/Modules/DNS/Models/DnsZone.php)
- [DnsRecord Model](app/Modules/DNS/Models/DnsRecord.php)
- [PowerDnsService](app/Modules/DNS/Services/PowerDnsService.php)
- [DnsZoneController](app/Modules/DNS/Http/Controllers/DnsZoneController.php)
- [DnsRecordController](app/Modules/DNS/Http/Controllers/DnsRecordController.php)
- [DNS Routes](app/Modules/DNS/Routes/api.php)
- [DNS Templates](resources/views/templates/dns/) - default, google-workspace, office365, email-only

#### FTP Module
- [FtpAccount Model](app/Modules/FTP/Models/FtpAccount.php)
- [FtpService](app/Modules/FTP/Services/FtpService.php)
- [FtpController](app/Modules/FTP/Http/Controllers/FtpController.php)
- [FTP Routes](app/Modules/FTP/Routes/api.php)

#### Vue Pages (Phase 3)
- [EmailPage](resources/js/pages/EmailPage.vue)
- [DnsPage](resources/js/pages/DnsPage.vue)
- [FtpPage](resources/js/pages/FtpPage.vue)
- [SpamSettingsTab](resources/js/components/email/SpamSettingsTab.vue)

## Phase 3: Email & DNS Progress

### Task 3.1: Postfix/Dovecot Email Service ✅
- **Models:** MailDomain, MailAccount, MailAlias, MailForwarder (with UUID, SoftDeletes)
- **MailService:**
  - createMailDomain(), deleteMailDomain()
  - createMailAccount(), updateMailAccount(), deleteMailAccount()
  - changePassword(), getQuotaUsage()
  - createAlias(), deleteAlias()
  - createForwarder(), deleteForwarder()
  - suspendAccount(), unsuspendAccount()
  - Virtual users configuration for Postfix/Dovecot
- **Controllers:** MailDomainController, MailAccountController, MailAliasController
- **FormRequests:** CreateMailDomainRequest, CreateMailAccountRequest, CreateMailAliasRequest
- **Resources:** MailDomainResource, MailAccountResource, MailAliasResource
- **Policies:** MailDomainPolicy, MailAccountPolicy
- **Routes:** /api/mail/* (domains, accounts, aliases, forwarders)
- **Config:** config/mail_server.php (Postfix/Dovecot paths, quotas)
- **Tests:** Mail module tests passed

### Task 3.2: PowerDNS Zone Management ✅
- **Models:** DnsZone, DnsRecord (with UUID, SoftDeletes)
- **DnsService:**
  - createZone(), deleteZone()
  - createRecord(), updateRecord(), deleteRecord()
  - getZoneRecords(), exportZone()
  - applyTemplate() - Basic, Mailserver templates
  - validateRecord() - Type-specific validation
  - Auto-increment SOA serial
- **Controllers:** DnsZoneController, DnsRecordController
- **FormRequests:** CreateDnsZoneRequest, CreateDnsRecordRequest, UpdateDnsRecordRequest
- **Resources:** DnsZoneResource, DnsRecordResource
- **Policies:** DnsZonePolicy, DnsRecordPolicy
- **Routes:** /api/dns/* (zones, records, templates, export)
- **Record Types:** A, AAAA, CNAME, MX, TXT, NS, SRV, CAA, PTR, SOA
- **Tests:** DNS module tests passed

### Task 3.3: Email Page UI ✅
- **EmailPage.vue:** Complete email management interface
  - Domain selection dropdown
  - Email accounts table with actions (change password, suspend, delete)
  - Aliases table with create/delete
  - Forwarders management
  - Create account modal with quota settings
  - Spam settings tab
- **SpamSettingsTab.vue:** Spam filter configuration (SpamAssassin integration)
- **i18n:** email.* translations (vi/en)

### Task 3.4: DNS Page UI ✅
- **DnsPage.vue:** Complete DNS zone management interface
  - Zone selection dropdown
  - Records table with type-specific formatting
  - Create/edit record modal with type-specific fields
  - Delete zone/record confirmation
  - Template application modal
  - Zone export functionality
  - Record enable/disable toggle
- **i18n:** dns.* translations (vi/en)

### Task 3.5: FTP Account Management ✅
- **Models:** FtpAccount (with UUID, SoftDeletes)
- **FtpService:**
  - createFtpAccount(), updateFtpAccount(), deleteFtpAccount()
  - changePassword()
  - updateQuota()
  - suspendAccount(), unsuspendAccount()
  - ProFTPD/Pure-FTPd configuration
- **Controllers:** FtpController
- **FormRequests:** CreateFtpAccountRequest, UpdateFtpAccountRequest
- **Resources:** FtpAccountResource
- **Policies:** FtpAccountPolicy
- **Routes:** /api/ftp/* (CRUD, password, suspend/unsuspend)
- **FtpPage.vue:** Complete FTP management interface
  - FTP accounts table with status badges
  - Create/edit account modal
  - Change password modal
  - Delete confirmation dialog
  - Suspend/unsuspend actions
- **i18n:** ftp.* translations (vi/en)
- **Tests:** FTP module tests passed

### Task 3.6: Webmail Integration (Roundcube) ✅
- **WebmailService:** (app/Modules/Mail/Services/WebmailService.php)
  - generateSsoToken() - Generate SSO token for webmail access
  - validateSsoToken() - Validate and return account
  - getWebmailUrl() - Get webmail URL with SSO token
  - getMailClientConfig() - Get IMAP/POP3/SMTP settings for email clients
  - getWebmailConfig() - Get global webmail configuration
- **Config:** config/webmail.php
  - Roundcube settings (path, database, IMAP/SMTP)
  - SSO configuration (token TTL, IP validation, single use)
  - Mail server settings for client configuration
  - Auto-configuration endpoints
- **WebmailController:** (app/Modules/Mail/Http/Controllers/WebmailController.php)
  - POST /api/mail/webmail/validate-sso - Validate SSO token (for Roundcube plugin)
  - GET /api/mail/webmail/config - Get webmail configuration
  - POST /api/mail/webmail/auto-login - Generate auto-login URL
- **MailAccountController Updates:**
  - GET /api/mail/accounts/{id}/webmail-url - Get webmail SSO URL
  - GET /api/mail/accounts/{id}/client-config - Get mail client config
- **Roundcube SSO Plugin:** resources/roundcube/plugins/vsispanel_sso/
  - vsispanel_sso.php - Main plugin for SSO integration
  - README.md - Installation and configuration guide
- **EmailPage.vue Updates:**
  - Added "Open Webmail" button in account actions (GlobeAltIcon)
  - Enhanced mail configuration card with copy-to-clipboard
  - Added "Client Settings" modal with detailed IMAP/POP3/SMTP configuration
  - Webmail link in config card
- **i18n:** Added webmail translations (en.json, vi.json)
  - openWebmail, webmailError, clientSettings, clientConfigTitle
  - imapSettings, pop3Settings, smtpSettings
  - server, port, security, authentication
  - usernameNote, usernameNoteDesc

### Task 3.7: DNS Templates & Bulk Operations ✅
- **DNS Templates:** (resources/views/templates/dns/)
  - default.json - Basic website hosting template (A, AAAA, MX, TXT SPF/DMARC, NS)
  - google-workspace.json - Google Workspace email template
  - office365.json - Microsoft 365 email template
  - email-only.json - Email-focused template with SPF/DKIM/DMARC
- **PowerDnsService Updates:** (app/Modules/DNS/Services/PowerDnsService.php)
  - getTemplatePreview() - Preview template with variable substitution
  - bulkAddRecords() - Add multiple records at once
  - bulkDeleteRecords() - Delete multiple records by IDs
  - bulkUpdateRecords() - Update multiple records
  - importZone() - Import from BIND zone file format
  - parseBindZoneFile() - Parse BIND format with $TTL, $ORIGIN support
  - cloneZone() - Clone records from one zone to another
  - resetZone() - Reset zone to default records
- **DnsZoneController Updates:** (app/Modules/DNS/Http/Controllers/DnsZoneController.php)
  - POST /api/dns/templates/preview - Preview DNS template
  - POST /api/dns/zones/{zone}/bulk-add - Bulk add records
  - POST /api/dns/zones/{zone}/bulk-delete - Bulk delete records
  - POST /api/dns/zones/{zone}/bulk-update - Bulk update records
  - POST /api/dns/zones/{zone}/import - Import BIND zone file
  - POST /api/dns/zones/{zone}/clone - Clone to another zone
  - POST /api/dns/zones/{zone}/reset - Reset to default
- **DnsPage.vue Updates:** (resources/js/pages/DnsPage.vue)
  - Added Import Zone modal with BIND format textarea
  - Added Clone Zone modal with target zone selection
  - Added Reset Zone confirmation dialog
  - Added bulk selection checkboxes in records table
  - Added Bulk Actions bar with bulk delete
  - Added Import, Clone, Reset buttons in zone actions
- **i18n:** Added DNS bulk operations translations (en.json, vi.json)
  - import, importZone, zoneFileContent, zoneFilePlaceholder, zoneFileHint
  - clone, cloneZone, targetZone, selectTargetZone, cloneHint
  - reset, resetZone, resetConfirm
  - bulkDelete, selectedRecords, bulkDeleteConfirm
  - Success/error messages for all operations

### Task 3.8: Email Anti-Spam (Rspamd) ✅
- **RspamdService:** (app/Modules/Mail/Services/RspamdService.php)
  - getStatus() - Get Rspamd service status and version
  - getStatistics() - Get spam/ham statistics (scanned, learned, spam_count, ham_count)
  - getSpamScore() / setSpamScore() - Manage score thresholds (reject, add_header, greylist)
  - getWhitelist() / addToWhitelist() / removeFromWhitelist() - Whitelist management
  - getBlacklist() / addToBlacklist() / removeFromBlacklist() - Blacklist management
  - trainHam() / trainSpam() - Machine learning training
  - scanMessage() - Scan message and get spam score
  - getHistory() - Get recent scan history
  - reload() / restart() - Service management
  - Manages /etc/rspamd/local.d/ configuration files
- **SpamController:** (app/Modules/Mail/Http/Controllers/SpamController.php)
  - GET /api/mail/spam/settings - Get settings and statistics
  - PUT /api/mail/spam/settings - Update score thresholds
  - GET/POST/DELETE /api/mail/spam/whitelist - Whitelist CRUD
  - GET/POST/DELETE /api/mail/spam/blacklist - Blacklist CRUD
  - GET /api/mail/spam/history - Get scan history
  - POST /api/mail/spam/train/ham - Train as ham
  - POST /api/mail/spam/train/spam - Train as spam
- **SpamSettingsTab.vue:** (resources/js/components/mail/SpamSettingsTab.vue)
  - Status card with running/stopped indicator
  - Statistics display (scanned, spam, ham, learned)
  - Score thresholds configuration (reject, add_header, greylist)
  - Whitelist management with add/remove
  - Blacklist management with add/remove
- **Config:** rspamd settings in config/vsispanel.php
  - rspamd_api_url, rspamd_api_password, rspamd_config_path
- **MailServiceProvider:** Added RspamdService registration
- **i18n:** Complete spam translations (en.json, vi.json)
  - status, running, stopped, statistics
  - scoreThresholds, rejectScore, addHeaderScore, greylistScore
  - whitelist, blacklist, addEntry, noEntries
  - settingsSaved, settingsError

### Bug Fixes & Improvements (2026-02-05)

#### Task Manager Module ✅
- **New Module:** Task Manager to track long-running operations
- **Files:**
  - [Task Model](app/Modules/TaskManager/Models/Task.php) - UUID, status tracking, progress percentage
  - [TaskService](app/Modules/TaskManager/Services/TaskService.php) - Create, update, cancel tasks
  - [TaskController](app/Modules/TaskManager/Http/Controllers/TaskController.php) - API endpoints
  - [TaskPage.vue](resources/js/pages/TaskPage.vue) - Task management UI
- **Features:**
  - Track backup/restore operations, service install/uninstall
  - Real-time progress updates via polling
  - Task filtering by status, type
  - Cancel, retry, delete tasks
  - Auto-refresh active tasks every 5 seconds
- **Routes:** /api/tasks/* (list, stats, active, cancel, retry, delete, bulk-delete)
- **i18n:** task.* translations (vi/en)
- **Sidebar:** Added Task Manager link with BackgroundBeaker icon

#### MySQL InnoDB Recovery ✅
- **Issue:** MySQL InnoDB undo tablespace corruption after disk full event
- **Error:** `unable to read page [page id: space=4294967279] (undo tablespace has been truncated)`
- **Solution:**
  1. Started MySQL in recovery mode 6 (`innodb_force_recovery=6`)
  2. Dumped all databases: `mysqldump --all-databases > backup.sql`
  3. Stopped MySQL, deleted data directory
  4. Reinitialized MySQL: `mysqld --initialize-insecure`
  5. Restored databases from dump
- **Files Modified:** `/etc/mysql/mysql.conf.d/mysqld.cnf` (temporary recovery mode)

#### phpMyAdmin SSO Fix ✅
- **Issue:** Auto-login to phpMyAdmin broken after MySQL recovery
- **Error:** `Authentication Error - Invalid request: missing authentication token`
- **Root Cause:** Configuration file not incrementing server index
- **Fix:** Added `$i++` to create server 2 for SSO auth
- **File:** [/etc/phpmyadmin/conf.d/vsispanel-signon.php](/etc/phpmyadmin/conf.d/vsispanel-signon.php)
  ```php
  $i++;  // Server 2 for SSO
  $cfg['Servers'][$i]['auth_type'] = 'signon';
  $cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
  $cfg['ServerDefault'] = $i;
  ```
- **File:** [/usr/share/phpmyadmin/signon.php](/usr/share/phpmyadmin/signon.php) - Token validation handler

#### Backup Module Improvements ✅
- **Custom Backup Type:** Added support for custom backup configurations
  - Added `TYPE_CUSTOM = 'custom'` to BackupConfig and Backup models
  - Migration: [2026_02_05_160200_add_custom_type_to_backup_configs.php](app/Modules/Backup/Database/Migrations/2026_02_05_160200_add_custom_type_to_backup_configs.php)
  - Added `getPathsFromBackupItems()` method in BackupService to map backup_items to paths

- **Database Restore Fix (Empty Password):**
  - **Issue:** `Access denied for user 'root'@'localhost' (using password: YES)` when MySQL root has no password
  - **Root Cause:** Code always passing `-p` flag even when password is empty
  - **Fix:** Conditionally add `-p` flag only when password is not empty
  - **File:** [BackupService.php](app/Modules/Backup/Services/BackupService.php)
    ```php
    // Line ~781 - restoreFromSqlDumps()
    $mysqlCommand = ['mysql', '-u', $mysqlCredentials['user']];
    if (!empty($mysqlCredentials['password'])) {
        $mysqlCommand[] = '-p' . $mysqlCredentials['password'];
    }

    // Line ~1420 - createDatabaseDumps()
    $dumpCommand = ['mysqldump', '-u', $mysqlCredentials['user']];
    if (!empty($mysqlCredentials['password'])) {
        $dumpCommand[] = '-p' . $mysqlCredentials['password'];
    }

    // Line ~1480 - listDatabases()
    $mysqlCommand = ['mysql', '-u', $credentials['user']];
    if (!empty($credentials['password'])) {
        $mysqlCommand[] = '-p' . $credentials['password'];
    }
    ```
  - **Important:** After code changes, must clear caches and restart services:
    ```bash
    php artisan config:clear && php artisan cache:clear && php artisan config:cache
    systemctl restart php8.3-fpm
    php artisan horizon:terminate && php artisan horizon
    ```

### Bug Fixes & Improvements (2026-02-04)

#### Database Import Feature Fixes
- **Issue 1:** "Backup file not found or not readable" error
  - **Cause:** Shell `test -r` command failed due to whitelist restrictions
  - **Fix:** Changed to PHP native `file_exists()` and `is_readable()` in [DatabaseService.php](app/Modules/Database/Services/DatabaseService.php)

- **Issue 2:** File saved with .txt extension instead of original extension
  - **Cause:** Using `$file->store()` which generates random filename
  - **Fix:** Changed to `$file->storeAs()` with explicit filename preserving original extension in [DatabaseController.php](app/Modules/Database/Http/Controllers/DatabaseController.php)

- **Issue 3:** "Import file not found" with correct extension
  - **Cause:** Laravel local disk root is `storage/app/private/` not `storage/app/`
  - **Fix:** Use `Storage::disk('local')->path()` instead of manual path construction

- **Issue 4:** "Command 'bash' is not allowed" error
  - **Cause:** `bash`, `cat`, `zcat` commands not in whitelist
  - **Fix:** Added shell commands to `allowed_commands` in [config/vsispanel.php](config/vsispanel.php):
    ```php
    'bash', 'cat', 'zcat', 'test'
    ```

- **New Feature:** ZIP file extraction support
  - Added `extractSqlFromZip()` method in DatabaseService
  - Added `deleteDirectory()` helper method for cleanup
  - Database import now supports `.sql`, `.gz`, and `.zip` files

#### PHP Settings UI Improvements
- **File:** [DomainSettingsTab.vue](resources/js/components/domain/tabs/DomainSettingsTab.vue)
- **Changes:**
  - Changed PHP settings from dropdowns to text inputs for custom values
  - Users can now enter values like `1G`, `2G` for memory limits
  - Fixed `fetchPhpSettings()` to correctly read from `response.data.data.settings` instead of spreading entire response
  - Settings now persist and display correctly after save/reload

- **File:** [UpdatePhpSettingsRequest.php](app/Modules/WebServer/Http/Requests/UpdatePhpSettingsRequest.php)
- **Validation Limits Increased:**
  - `max_execution_time`: 0-86400 seconds (24 hours, 0 = unlimited)
  - `max_input_time`: -1 to 86400 seconds (-1 = use max_execution_time)
  - `max_input_vars`: 100-100000 (for complex apps like WooCommerce)

- **i18n Additions:** (vi.json, en.json)
  ```json
  "memoryLimitHint": "Ví dụ: 256M, 512M, 1G, 2G",
  "uploadMaxFilesizeHint": "Ví dụ: 128M, 256M, 512M, 1G",
  "postMaxSizeHint": "Ví dụ: 128M, 256M, 512M, 1G (nên >= upload_max_filesize)",
  "maxExecutionTimeHint": "Giây (0 = không giới hạn, ví dụ: 300, 600, 3600)"
  ```

#### API Route Prefix Consistency Fix
- **Issue:** Frontend calling `/api/v1/*` but backend routes registered as `/api/*`
- **Cause:** Some modules used `prefix('api/v1')` while others used `prefix('api')`
- **Files Fixed (Backend - changed to `prefix('api')`):**
  - [Domain Routes](app/Modules/Domain/Routes/api.php)
  - [WebServer Routes](app/Modules/WebServer/Routes/api.php)
  - [SSL Routes](app/Modules/SSL/Routes/api.php)
  - [Hosting Routes](app/Modules/Hosting/Routes/api.php)
- **Files Fixed (Frontend - removed `/v1/` prefix):**
  - [domains.js](resources/js/stores/domains.js) - All 11 API endpoints
  - [EmailPage.vue](resources/js/pages/EmailPage.vue)
  - [DnsPage.vue](resources/js/pages/DnsPage.vue)
  - [SpamSettingsTab.vue](resources/js/components/email/SpamSettingsTab.vue)
  - [PhpSettingsModal.vue](resources/js/components/php/PhpSettingsModal.vue)
  - [DomainDetailPage.vue](resources/js/pages/DomainDetailPage.vue)

#### Database Transaction Fix
- **Issue:** "There is no active transaction" error when creating databases
- **Cause:** MySQL DDL statements (CREATE DATABASE, CREATE USER) cause implicit commit and cannot run inside DB::transaction()
- **Fix:** Changed [DatabaseService.php](app/Modules/Database/Services/DatabaseService.php) to use try/catch with manual cleanup instead of DB::transaction()

#### Database Creation Form Enhancement
- **File:** [DomainDatabasesTab.vue](resources/js/components/domain/tabs/DomainDatabasesTab.vue)
- **Added:** Checkbox to create database user with username/password fields
- **Added:** Password generation functionality
- **i18n Added:** `common.generate`, `databases.createUser`, `databases.username`, `databases.usernamePlaceholder`, `databases.password`, `databases.passwordPlaceholder`, `databases.userCreateWarning`

#### File Manager Modals
- **File:** [DomainFilesTab.vue](resources/js/components/domain/tabs/DomainFilesTab.vue)
- **Added:** New Folder modal
- **Added:** New File modal
- **Added:** Upload files modal
- **Config:** Created [filemanager.php](config/filemanager.php) with allowed/blocked extensions, editable extensions

## TODO

### Phase 1 (Completed)
- [x] Task 1.1-1.8: Foundation ✅
- [ ] Task 1.9: API Base Structure
- [ ] Task 1.10: CI/CD & Docker Dev Environment

### Phase 2 (Completed)
- [x] Task 2.1: Domain Management Module ✅
- [x] Task 2.2: Nginx Virtual Host Service ✅
- [x] Task 2.3: PHP-FPM Multi-Version Management ✅
- [x] Task 2.4: MySQL Database Management Module ✅
- [x] Task 2.5: Hosting Plans & Subscriptions ✅
- [x] Task 2.6: SSL Certificate Module ✅
- [x] Task 2.7: File Manager v1 ✅
- [x] Task 2.8: Websites Page UI Complete ✅

### Phase 3 (Completed)
- [x] Task 3.1: Postfix/Dovecot Email Service ✅
- [x] Task 3.2: PowerDNS Zone Management ✅
- [x] Task 3.3: Email Page UI ✅
- [x] Task 3.4: DNS Page UI ✅
- [x] Task 3.5: FTP Account Management ✅
- [x] Task 3.6: Webmail Integration (Roundcube) ✅
- [x] Task 3.7: DNS Templates & Bulk Operations ✅
- [x] Task 3.8: Email Anti-Spam (Rspamd) ✅

### Phase 4 (Completed)
- [x] Task Manager Module ✅
- [x] Backup Module - Custom backup type ✅
- [x] Backup Module - Database restore fix ✅
- [x] Integrate Task Manager with backup/restore operations ✅

### Phase 5 (Completed)
- [x] App Manager Module ✅
- [x] Task Module (refactored from TaskManager) ✅
- [x] Monitoring Dashboard ✅
- [x] Alerts System ✅
- [x] Cron Job Manager ✅
- [x] Reseller Management ✅
- [x] Horizon Installs Queue ✅
- [x] Critical Service Protection ✅
- [x] Task Progress Streaming Fix ✅
- [x] Node.js Version Protection ✅
- [x] Settings Module (General + Notifications + Gmail OAuth2) ✅
- [x] Redis Cache Fallback for MySQL Down Alerts ✅

### Phase 4 Key Files

#### Task Manager Module
- [Task Model](app/Modules/TaskManager/Models/Task.php)
- [TaskService](app/Modules/TaskManager/Services/TaskService.php)
- [TaskController](app/Modules/TaskManager/Http/Controllers/TaskController.php)
- [TaskPage.vue](resources/js/pages/TaskPage.vue)
- [Task Routes](app/Modules/TaskManager/Routes/api.php)

#### Backup Module (Updated)
- [BackupConfig Model](app/Modules/Backup/Models/BackupConfig.php) - Added TYPE_CUSTOM
- [Backup Model](app/Modules/Backup/Models/Backup.php) - Added TYPE_CUSTOM
- [BackupService](app/Modules/Backup/Services/BackupService.php) - Database restore fixes
- [Migration](app/Modules/Backup/Database/Migrations/2026_02_05_160200_add_custom_type_to_backup_configs.php)

## Phase 5: Advanced Features Progress

### App Manager Module ✅
- **Mô tả:** Quản lý toàn bộ ứng dụng/dịch vụ trên server (install, uninstall, start, stop, restart, cấu hình)
- **Models:** [ManagedApp](app/Modules/AppManager/Models/ManagedApp.php) - UUID, SoftDeletes, version tracking, critical flag
- **Services:** [AppManagerService](app/Modules/AppManager/Services/AppManagerService.php) - Detect, refresh status, version management, config management
- **Controllers:** [AppManagerController](app/Modules/AppManager/Http/Controllers/AppManagerController.php) - Full REST API (15 endpoints)
- **Jobs:**
  - [InstallAppVersionJob](app/Modules/AppManager/Jobs/InstallAppVersionJob.php) - Async install với progress tracking (PHP, Node.js, Python, Composer)
  - [UninstallAppVersionJob](app/Modules/AppManager/Jobs/UninstallAppVersionJob.php) - Async uninstall
- **Config:** [appmanager.php](app/Modules/AppManager/Config/appmanager.php) - 18 managed apps, 8 categories
- **Migration:** [create_managed_apps_table](app/Modules/AppManager/Database/Migrations/2026_02_07_000003_create_managed_apps_table.php)
- **Frontend:**
  - [AppManagerPage.vue](resources/js/pages/AppManagerPage.vue) - List view với category filter, search, critical badges
  - [AppManagerDetailPage.vue](resources/js/pages/AppManagerDetailPage.vue) - Detail view, version management, config editor, logs, extensions, task progress modal
- **App Types:**
  - **Single-version:** nginx, mysql, redis, postfix, dovecot, named, fail2ban, ufw, composer, vsispanel-web/horizon/terminal
  - **Multi-version:** PHP (7.4-8.4), Node.js (18, 20, 22), Python (3.10-3.12)
- **Features:**
  - Live service status detection (running/enabled)
  - Configuration file management với backup trước khi save
  - Service log viewing
  - Per-version PHP extensions listing
  - Task progress tracking (real-time polling modal)
- **Routes:**
  - `GET /api/app-manager` - List all apps
  - `GET /api/app-manager/{slug}` - App detail
  - `POST /api/app-manager/scan` - Scan/detect apps on system
  - `POST /api/app-manager/{slug}/start|stop|restart|enable|disable` - Service control
  - `POST /api/app-manager/{slug}/install|uninstall` - Install/uninstall (async via Task)
  - `POST /api/app-manager/{slug}/set-default` - Set default version
  - `GET /api/app-manager/{slug}/extensions` - List PHP extensions
  - `GET /api/app-manager/{slug}/config` - Get config files
  - `PUT /api/app-manager/{slug}/config` - Save config
  - `GET /api/app-manager/{slug}/logs` - Service logs

### Task Module (Refactored) ✅
- **Mô tả:** Module Task mới (refactored từ TaskManager), track long-running operations với streaming output
- **Models:** [Task](app/Modules/Task/Models/Task.php) - UUID, SoftDeletes, 16 task types, 5 statuses, duration calculation
- **Services:** [TaskService](app/Modules/Task/Services/TaskService.php) - Create, update progress, complete, fail, cancel, statistics
- **Controllers:** [TaskController](app/Modules/Task/Http/Controllers/TaskController.php) - CRUD + output streaming endpoint
- **Resources:** [TaskResource](app/Modules/Task/Http/Resources/TaskResource.php) - JSON formatting
- **Routes:**
  - `GET /api/tasks` - List tasks (filterable by status, type)
  - `GET /api/tasks/stats` - Task statistics
  - `GET /api/tasks/active` - Active tasks only
  - `GET /api/tasks/{id}` - Task detail
  - `GET /api/tasks/{id}/output` - Streaming output (offset-based polling)
  - `POST /api/tasks/{id}/cancel` - Cancel task
  - `POST /api/tasks/{id}/retry` - Retry failed task
  - `DELETE /api/tasks/{id}` - Delete task
  - `POST /api/tasks/bulk-delete` - Bulk delete
- **Task Types:** backup.create, backup.restore, service.start/stop/restart/install/uninstall, ssl.issue/renew, dns.sync, system.update, database.import/export, file.upload/extract, custom
- **Frontend:** [TaskPage.vue](resources/js/pages/TaskPage.vue) - Task list với auto-refresh, filtering, streaming progress

### Monitoring Dashboard ✅
- **Frontend:** [MonitoringPage.vue](resources/js/pages/MonitoringPage.vue)
- **Features:**
  - **Dashboard Tab:** CPU/Memory gauge charts, disk usage, network I/O, load average, uptime, process count
  - **Processes Tab:** Top processes by CPU/Memory, kill process functionality
  - ApexCharts integration (area/line charts)
  - Chart period selector: 1h, 6h, 24h, 7d, 30d
  - Auto-refresh every 30 seconds
  - Dark mode support
- **API:**
  - `GET /api/monitoring/current` - Real-time metrics
  - `GET /api/monitoring/history` - Historical data
  - `GET /api/monitoring/processes` - Process list
  - `POST /api/monitoring/processes/{pid}/kill` - Kill process

### Alerts System ✅
- **Frontend:** [AlertsPage.vue](resources/js/pages/AlertsPage.vue)
- **Features:**
  - **Rules Tab:** Create/edit alert rules với metric conditions
  - **History Tab:** Alert history với severity filter, acknowledge/resolve
  - **Templates Tab:** Pre-built alert templates
  - Summary cards (total rules, active/critical, last 24h)
  - Metrics: CPU, memory, disk, service down, SSH brute force, SSL expiry, backup failed
  - Categories: resource, service, security, backup, ssl
  - Severity: info, warning, critical
  - Notification channels: email, telegram, slack, discord
  - Cooldown configuration (minutes)
- **API:**
  - `GET/POST /api/monitoring/alerts` - List/Create rules
  - `PUT/DELETE /api/monitoring/alerts/{id}` - Update/Delete rule
  - `POST /api/monitoring/alerts/{id}/toggle` - Toggle active
  - `GET /api/monitoring/alerts/summary` - Summary stats
  - `GET /api/monitoring/alerts/history` - Alert history
  - `GET /api/monitoring/alerts/templates` - Templates
  - `POST /api/monitoring/alerts/history/{id}/acknowledge|resolve` - Handle alerts
  - `POST /api/monitoring/alerts/from-template/{id}` - Activate template

### Cron Job Manager ✅
- **Backend:** [Cron Module](app/Modules/Cron/) - Model, Service, Controller, Routes
- **Frontend:** [CronPage.vue](resources/js/pages/CronPage.vue)
- **Features:**
  - Cron jobs table với status, schedule (human-readable), last/next run
  - Create/Edit modal với preset schedules
  - Schedule presets: every minute, 5/15/30min, hourly, daily, weekly, monthly
  - **SSL Renewal preset:** `0 3 * * *` với `certbot renew --quiet --deploy-hook "systemctl reload nginx"`
  - Schedule validation với next 5 runs preview
  - Output handling: discard, email, log file
  - Run now, view output, enable/disable toggle
- **API:**
  - `GET/POST /api/cron-jobs` - List/Create
  - `PUT/DELETE /api/cron-jobs/{id}` - Update/Delete
  - `POST /api/cron-jobs/{id}/toggle` - Enable/disable
  - `POST /api/cron-jobs/{id}/run-now` - Execute immediately
  - `GET /api/cron-jobs/{id}/output` - View output
  - `POST /api/cron-jobs/validate` - Validate cron expression

### Reseller Management ✅
- **Frontend:** [ResellerPage.vue](resources/js/pages/ResellerPage.vue)
- **Features:**
  - **Customers Tab:** Create customer, suspend/unsuspend/terminate, impersonate (token swap)
  - **Branding Tab:** Company name, primary color, support email/URL, nameservers
  - **Reports Tab:** Customer growth chart (bar chart, 3m/6m/12m), customer breakdown table
  - Status badges: active, suspended, terminated
- **API:**
  - `GET/POST /api/reseller/customers` - List/Create customers
  - `POST /api/reseller/customers/{id}/suspend|unsuspend|terminate|impersonate`
  - `GET/PUT /api/reseller/branding` - Load/Save branding
  - `GET /api/reseller/reports/overview|growth|customers` - Reports

### Router Updates ✅
- **File:** [router/index.js](resources/js/router/index.js)
- **New Routes:**
  - `/monitoring` → MonitoringPage (lazy-loaded)
  - `/cron` → CronPage
  - `/tasks` → TaskPage
  - `/app-manager` → AppManagerPage
  - `/app-manager/:slug` → AppManagerDetailPage (named: `app-manager-detail`)
  - `/alerts` → AlertsPage
  - `/reseller` → ResellerPage

### Bug Fixes & Improvements (2026-02-06)

#### Critical Service Protection ✅
- **Mô tả:** Bảo vệ các service quan trọng (nginx, mysql, redis) khỏi bị stop/disable/uninstall
- **Config:** Thêm `is_critical: true` vào nginx, mysql, redis trong [appmanager.php](app/Modules/AppManager/Config/appmanager.php)
- **Model:** Thêm `isCritical()`, `isActiveVersion()`, `$appends = ['is_critical']` vào [ManagedApp.php](app/Modules/AppManager/Models/ManagedApp.php)
- **Controller:** Thêm guards vào `stop()`, `disable()`, `uninstall()` trong [AppManagerController.php](app/Modules/AppManager/Http/Controllers/AppManagerController.php)
  - Critical services → 422 "Cannot stop/disable/uninstall — required by panel"
  - Active version → 422 "Switch the default version first"
- **Frontend:**
  - [AppManagerPage.vue](resources/js/pages/AppManagerPage.vue): "Critical" badge (amber), ẩn nút stop/uninstall cho critical
  - [AppManagerDetailPage.vue](resources/js/pages/AppManagerDetailPage.vue): Ẩn stop/disable/uninstall cho active version, disabled icon với tooltip

#### Task Progress Modal Streaming Fix ✅
- **Vấn đề:** Modal install trong AppManagerDetailPage stuck ở "Waiting for output..." và 0% progress dù Task page hiển thị progress tốt
- **Nguyên nhân:**
  1. `catch` block gọi `stopTaskPolling()` ngay lập tức khi có lỗi → polling dừng vĩnh viễn
  2. Backend không `refresh()` model → trả dữ liệu cũ
  3. `$request->get()` thay vì `$request->query()` cho query params
  4. Offset chỉ cập nhật khi output truthy (empty string "" là falsy)
- **Fix:**
  - Frontend: Thêm `taskPollErrors` counter (10 retries), delay 500ms trước poll đầu tiên, luôn cập nhật offset
  - Backend [TaskController](app/Modules/Task/Http/Controllers/TaskController.php): `$task->refresh()`, `$request->query()` với `(int)` cast

#### Horizon Installs Queue ✅
- **Vấn đề:** Install jobs chạy trên `default` queue (60s timeout) nhưng cần 15+ phút
- **Fix:** Thêm `supervisor-installs` vào [config/horizon.php](config/horizon.php):
  - Queue: `installs`
  - Timeout: 900s (15 phút)
  - Memory: 256MB
  - MaxProcesses: 1 (local), 2 (production)
- Install/Uninstall jobs đã chuyển sang queue `installs`

#### Node.js Version Protection ✅
- **Vấn đề:** InstallAppVersionJob ghi đè `/usr/local/bin/node` khi install Node 18, phá vỡ Vite 6 (cần Node 20+ cho `crypto.hash`)
- **Fix:** [InstallAppVersionJob.php](app/Modules/AppManager/Jobs/InstallAppVersionJob.php) kiểm tra `/usr/bin/node` tồn tại trước khi set default
  ```php
  $systemNode = trim(Process::timeout(5)->run('which /usr/bin/node 2>/dev/null')->output());
  if ($isFirst && empty($systemNode)) {
      $this->setNodeDefault($version, $binDir);
  }
  ```
- **Lưu ý:** Vite 6 yêu cầu Node.js 20+ (dùng `crypto.hash` API). Nếu bị lỗi `crypto.hash is not a function`, chạy: `ln -sf /usr/bin/node /usr/local/bin/node`

### Settings Module ✅
- **Backend:** [SettingsService](app/Modules/Settings/Services/SettingsService.php) - Get/set/batch update settings, timezone, mail config, notification test
- **Controller:** [SettingsController](app/Modules/Settings/Http/Controllers/SettingsController.php) - CRUD + Gmail OAuth2 flow + notification test
- **FormRequest:** [UpdateSettingsRequest](app/Modules/Settings/Http/Requests/UpdateSettingsRequest.php) - Validation với `prepareForValidation()` flat→nested conversion
- **Model:** [SystemSetting](app/Modules/Settings/Models/SystemSetting.php) - group/key/value/type storage
- **Frontend:** [SettingsPage.vue](resources/js/pages/SettingsPage.vue) - General + Notifications tabs
- **Features:**
  - General: Panel name, timezone (with server time sync via timedatectl)
  - Mail providers: SMTP, Gmail OAuth2, SES, Sendmail
  - Notification channels: Email, Telegram, Slack, Discord (with test button)
  - Gmail OAuth2 via OAuth Proxy (authorize/callback/revoke flow)
- **API:**
  - `GET /api/v1/settings` - Get all settings grouped
  - `PUT /api/v1/settings` - Update settings batch
  - `GET /api/v1/settings/timezones` - Available timezones
  - `POST /api/v1/settings/notifications/test` - Test notification channel
  - `POST /api/v1/settings/time/sync` - Sync server time via NTP
  - `GET/POST /api/v1/settings/mail/gmail/status|authorize|callback|revoke` - Gmail OAuth2

### Bug Fixes & Improvements (2026-02-07)

#### Settings Validation Fix ✅
- **Issue:** Frontend sends flat dot-notation keys `{"notifications.email.enabled": true}` but Laravel `validated()` returns empty `[]`
- **Root Cause:** Laravel validation expects nested structure `{"notifications": {"email": {"enabled": true}}}`
- **Fix:**
  - Added `prepareForValidation()` in [UpdateSettingsRequest](app/Modules/Settings/Http/Requests/UpdateSettingsRequest.php) to convert flat → nested
  - Added `Arr::dot($request->validated())` in [SettingsController](app/Modules/Settings/Http/Controllers/SettingsController.php) to flatten back for `updateBatch()`

#### SMTP Encryption Scheme Fix ✅
- **Issue:** `The "tls" scheme is not supported` error when sending SMTP emails
- **Root Cause:** Symfony Mailer only supports `smtp` (STARTTLS) or `smtps` (implicit SSL), not `tls`/`ssl`
- **Fix:** In [SettingsService.php](app/Modules/Settings/Services/SettingsService.php) `applyMailConfig()`:
  ```php
  $scheme = match ($encryption) {
      'ssl' => 'smtps',
      'tls', 'none' => null,
      default => null,
  };
  ```

#### Alert Rule Update Validation Fix ✅
- **Issue:** `Method validateSometimesIf does not exist` when updating alert rules
- **Root Cause:** `str_replace('required', 'sometimes', ...)` also changes `required_if` → `sometimes_if`
- **Fix:** In [AlertController.php](app/Modules/Monitoring/Http/Controllers/AlertController.php):
  ```php
  preg_replace('/\brequired\b(?!_)/', 'sometimes', $rule)
  ```

#### Redis Cache Fallback for MySQL Down ✅
- **Mô tả:** Alert rules và notification config được cache vào Redis để hệ thống alert vẫn hoạt động khi MySQL down
- **Strategy:** Write-through cache — mỗi lần đọc MySQL thành công, cache vào Redis. Khi MySQL fail, fallback đọc từ Redis.
- **Cache Keys (Redis DB 1):**
  - `vsispanel:alert_rules` — JSON array of active alert rules (raw DB attributes)
  - `vsispanel:notification_config` — JSON map config key → value (email, telegram, slack, discord)
  - `vsispanel:mail_config` — JSON map mail settings (provider, SMTP, SES, Gmail OAuth)
- **Files Modified:**
  - [AlertEvaluator.php](app/Modules/Monitoring/Services/AlertEvaluator.php):
    - Added `getActiveRules()` with MySQL-first, Redis fallback via `Cache::store('redis')`
    - Uses `$rule->getAttributes()` (raw DB attrs) instead of `toJson()` to avoid JSON cast hydration issues
    - Wrapped `AlertHistory::create()` in try-catch for QueryException
  - [AlertController.php](app/Modules/Monitoring/Http/Controllers/AlertController.php):
    - Added `refreshAlertRulesCache()` helper called after store/update/delete/toggle/createFromTemplate
  - [SettingsService.php](app/Modules/Settings/Services/SettingsService.php):
    - Refactored `applyNotificationConfigOverrides()` with Redis fallback
    - Refactored `applyMailConfigOverrides()` into MySQL/Redis/apply methods
    - All cache calls use `Cache::store('redis')` (default store is `database` which uses MySQL!)
  - [CollectMetricsJob.php](app/Modules/Monitoring/Jobs/CollectMetricsJob.php):
    - Split single try-catch into two: `collectAndSave()` and `evaluate()` run independently
    - So `evaluate()` still runs even if metrics saving fails due to MySQL down
- **Key Learnings:**
  - Default cache store is `database` → MUST use `Cache::store('redis')` explicitly
  - `$rules->toJson()` + `json_decode()` + `hydrate()` breaks JSON cast fields → use `getAttributes()` for raw values
- **Tested:** MySQL stopped → alert rules loaded from Redis → "MySQL Down" alert triggered → email + telegram sent successfully

### Phase 5 Key Files

#### App Manager Module
- [ManagedApp Model](app/Modules/AppManager/Models/ManagedApp.php)
- [AppManagerService](app/Modules/AppManager/Services/AppManagerService.php)
- [AppManagerController](app/Modules/AppManager/Http/Controllers/AppManagerController.php)
- [InstallAppVersionJob](app/Modules/AppManager/Jobs/InstallAppVersionJob.php)
- [UninstallAppVersionJob](app/Modules/AppManager/Jobs/UninstallAppVersionJob.php)
- [AppManager Config](app/Modules/AppManager/Config/appmanager.php)
- [AppManagerPage.vue](resources/js/pages/AppManagerPage.vue)
- [AppManagerDetailPage.vue](resources/js/pages/AppManagerDetailPage.vue)

#### Task Module
- [Task Model](app/Modules/Task/Models/Task.php)
- [TaskService](app/Modules/Task/Services/TaskService.php)
- [TaskController](app/Modules/Task/Http/Controllers/TaskController.php)
- [TaskResource](app/Modules/Task/Http/Resources/TaskResource.php)
- [Task Routes](app/Modules/Task/Routes/api.php)
- [TaskPage.vue](resources/js/pages/TaskPage.vue)

#### Monitoring & Alerts
- [MonitoringPage.vue](resources/js/pages/MonitoringPage.vue)
- [AlertsPage.vue](resources/js/pages/AlertsPage.vue)

#### Cron Module
- [CronPage.vue](resources/js/pages/CronPage.vue)
- [Cron Module](app/Modules/Cron/)

#### Reseller Module
- [ResellerPage.vue](resources/js/pages/ResellerPage.vue)

#### Settings Module
- [SettingsService](app/Modules/Settings/Services/SettingsService.php)
- [SettingsController](app/Modules/Settings/Http/Controllers/SettingsController.php)
- [UpdateSettingsRequest](app/Modules/Settings/Http/Requests/UpdateSettingsRequest.php)
- [SystemSetting Model](app/Modules/Settings/Models/SystemSetting.php)
- [SettingsPage.vue](resources/js/pages/SettingsPage.vue)
- [GmailOAuthTransport](app/Modules/Settings/Mail/GmailOAuthTransport.php)

#### Monitoring (Updated)
- [AlertEvaluator](app/Modules/Monitoring/Services/AlertEvaluator.php) - Redis cache fallback
- [AlertController](app/Modules/Monitoring/Http/Controllers/AlertController.php) - Cache refresh helper
- [CollectMetricsJob](app/Modules/Monitoring/Jobs/CollectMetricsJob.php) - Split try-catch

#### Queue Configuration
- [Horizon Config](config/horizon.php) - Added supervisor-installs

## Liên hệ & Tham khảo
- Claude Code Documentation: https://code.claude.com/docs
- GitHub Issues: https://github.com/anthropics/claude-code/issues
