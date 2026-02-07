# Phase 1: Foundation (Tuần 1-6)
> **Mục tiêu:** Xây dựng nền tảng cốt lõi — project structure, authentication, RBAC, dashboard skeleton, API base.

---

## Cách sử dụng file này
Mở Claude Code trong VSCode, copy từng block `>>> PROMPT` bên dưới và paste vào Claude Code theo thứ tự.
Sau mỗi task, kiểm tra code, chạy test, commit trước khi sang task tiếp theo.

---

## Task 1.1: Khởi tạo Laravel Project
**Thời gian:** ~30 phút
**Kiểm tra hoàn thành:** `php artisan serve` chạy được, truy cập http://localhost:8000

```
>>> PROMPT cho Claude Code:
Khởi tạo project Laravel 11 tên "vsispanel" với cấu hình sau:

1. Chạy `composer create-project laravel/laravel vsispanel`
2. Cài dependencies:
   - composer require laravel/sanctum laravel/horizon predis/predis spatie/laravel-permission spatie/laravel-activitylog
   - composer require --dev pestphp/pest pestphp/pest-plugin-laravel
   - npm install vue@3 @vitejs/plugin-vue pinia vue-router@4 axios tailwindcss @tailwindcss/forms @headlessui/vue @heroicons/vue
3. Tạo cấu trúc module trong app/Modules/ với các thư mục rỗng:
   Auth, Server, WebServer, Domain, Hosting, Database, Mail, DNS, SSL, FTP, FileManager, Backup, Firewall, Monitoring, Cron, Marketplace
4. Mỗi module có cấu trúc: Models/, Http/Controllers/, Http/Requests/, Http/Resources/, Services/, Policies/, Events/, Listeners/, Providers/, Routes/, Database/Migrations/, Database/Factories/, Database/Seeders/, Config/, Tests/Unit/, Tests/Feature/
5. Tạo file .env.example với MySQL, Redis config
6. Setup vite.config.js cho Vue 3 + Tailwind
7. Setup tailwind.config.js với custom colors theo CLAUDE.md Design System
8. Tạo base Pest config

Đảm bảo `php artisan serve` và `npm run dev` chạy được.
```

---

## Task 1.2: Module Autoloader
**Thời gian:** ~20 phút
**Kiểm tra hoàn thành:** Tất cả module ServiceProviders được load tự động

```
>>> PROMPT cho Claude Code:
Tạo hệ thống auto-load modules cho VSISPanel:

1. Tạo file app/Modules/ModuleServiceProvider.php:
   - Scan thư mục app/Modules/*/Providers/
   - Tự động register tất cả ServiceProvider tìm thấy
   - Load routes từ mỗi module (Routes/api.php, Routes/web.php)
   - Load migrations từ mỗi module (Database/Migrations/)
   - Load config từ mỗi module (Config/)
   - Merge module configs vào app config

2. Đăng ký ModuleServiceProvider trong bootstrap/providers.php

3. Tạo template ServiceProvider cho mỗi module:
   app/Modules/Auth/Providers/AuthServiceProvider.php (mẫu đầy đủ)

4. Tạo Artisan command: `php artisan module:make {name}` 
   - Tự động tạo toàn bộ folder structure + ServiceProvider cho module mới

5. Tạo Artisan command: `php artisan module:list`
   - Liệt kê tất cả modules và trạng thái (enabled/disabled)

Test: chạy `php artisan module:list` hiển thị tất cả modules.
```

---

## Task 1.3: Database Schema Core
**Thời gian:** ~40 phút
**Kiểm tra hoàn thành:** `php artisan migrate` thành công, tất cả bảng được tạo

```
>>> PROMPT cho Claude Code:
Tạo database migrations cho các bảng core của VSISPanel. Dùng UUID cho primary key, có soft deletes và timestamps.

1. Module Auth - users table (extend Laravel default):
   - uuid, name, email, password, role (enum: admin/reseller/user)
   - parent_id (nullable, FK users - cho reseller hierarchy)
   - status (enum: active/suspended/terminated)
   - two_factor_secret, two_factor_confirmed_at
   - last_login_at, last_login_ip
   - locale (default 'vi'), timezone (default 'Asia/Ho_Chi_Minh')
   - Indexes: email (unique), role, status, parent_id

2. Module Hosting - plans table:
   - uuid, name, description, slug (unique)
   - disk_limit (MB), bandwidth_limit (MB/month)
   - domains_limit, subdomains_limit, databases_limit
   - email_accounts_limit, ftp_accounts_limit
   - php_version_default, is_active (boolean)
   - created_by (FK users)

3. Module Hosting - subscriptions table:
   - uuid, user_id (FK), plan_id (FK)
   - status (enum: active/suspended/expired/cancelled)
   - disk_used, bandwidth_used
   - starts_at, expires_at

4. Module Domain - domains table:
   - uuid, user_id (FK), subscription_id (FK)
   - name (unique), document_root, php_version
   - status (enum: active/suspended/disabled)
   - ssl_enabled (boolean), is_main (boolean)

5. Module Domain - subdomains table:
   - uuid, domain_id (FK), name, document_root, php_version

6. Activity log: sử dụng spatie/laravel-activitylog (đã cài)

7. Tạo Models cho tất cả tables trên:
   - Relationships đầy đủ (belongsTo, hasMany, belongsToMany)
   - Casts cho enums, dates, booleans
   - Scopes: active(), suspended(), forUser()
   - Fillable, hidden properties

8. Tạo Factories và Seeders:
   - AdminSeeder: tạo admin@vsispanel.local / password
   - PlanSeeder: 3 plans mẫu (Starter, Business, Enterprise)

Chạy: php artisan migrate --seed
```

---

## Task 1.4: Authentication System
**Thời gian:** ~45 phút
**Kiểm tra hoàn thành:** Login/logout API hoạt động, 2FA flow OK

```
>>> PROMPT cho Claude Code:
Tạo hệ thống Authentication đầy đủ cho VSISPanel trong module Auth:

1. Auth Controllers (app/Modules/Auth/Http/Controllers/):

   a) LoginController:
      - POST /api/auth/login - Email + password login, trả về Sanctum token
      - POST /api/auth/login/2fa - Verify TOTP code sau login
      - POST /api/auth/logout - Revoke current token
      - Ghi activity log mọi login attempt (thành công/thất bại)
      - Rate limiting: 5 attempts/minute

   b) ProfileController:
      - GET /api/auth/me - Current user info
      - PUT /api/auth/profile - Update name, email, locale, timezone
      - PUT /api/auth/password - Change password

   c) TwoFactorController:
      - POST /api/auth/2fa/enable - Generate secret + QR code URL
      - POST /api/auth/2fa/confirm - Verify và activate 2FA
      - POST /api/auth/2fa/disable - Tắt 2FA (yêu cầu password)
      - Sử dụng package: pragmarx/google2fa-laravel

2. FormRequest validations cho mỗi endpoint

3. API Resources: UserResource, LoginResource

4. Middleware:
   - EnsureTwoFactorVerified: check 2FA nếu user đã enable
   - EnsureAccountActive: check status != suspended/terminated
   - TrackLastLogin: update last_login_at, last_login_ip

5. Routes trong app/Modules/Auth/Routes/api.php:
   - Prefix: /api/auth
   - Public routes: login, login/2fa
   - Protected routes (sanctum): me, profile, password, 2fa/*, logout

6. Cài thêm: composer require pragmarx/google2fa-laravel bacon/bacon-qr-code

7. Tests (Pest):
   - Test login success/failure
   - Test 2FA flow
   - Test rate limiting
   - Test profile update
   - Test middleware blocks suspended users

Chạy: php artisan test --filter=Auth
```

---

## Task 1.5: RBAC - Phân Quyền
**Thời gian:** ~30 phút
**Kiểm tra hoàn thành:** Admin/Reseller/User có permissions khác nhau

```
>>> PROMPT cho Claude Code:
Tạo hệ thống Role-Based Access Control cho VSISPanel sử dụng spatie/laravel-permission:

1. Seeder: RolesAndPermissionsSeeder - tạo roles và permissions:

   Roles: admin, reseller, user

   Permissions (nhóm theo module):
   - server: server.view, server.manage, server.services.restart
   - domains: domains.view, domains.create, domains.edit, domains.delete, domains.manage-all
   - hosting: plans.view, plans.create, plans.edit, plans.delete, subscriptions.manage
   - databases: databases.view, databases.create, databases.delete, databases.manage-all
   - mail: mail.view, mail.create, mail.delete, mail.manage-all
   - dns: dns.view, dns.edit, dns.manage-all
   - ssl: ssl.view, ssl.manage, ssl.manage-all
   - files: files.view, files.edit, files.upload
   - ftp: ftp.view, ftp.create, ftp.delete
   - backup: backup.view, backup.create, backup.restore, backup.manage-all
   - firewall: firewall.view, firewall.manage
   - monitoring: monitoring.view, monitoring.manage
   - cron: cron.view, cron.create, cron.edit, cron.delete
   - users: users.view, users.create, users.edit, users.delete, users.impersonate
   - reseller: reseller.manage-customers, reseller.manage-plans, reseller.view-reports

   Role assignments:
   - admin: TẤT CẢ permissions
   - reseller: domains.*, databases.*, mail.*, dns.*, ssl.*, files.*, ftp.*, backup.view/create/restore, cron.*, reseller.*, users.view (chỉ customers của mình)
   - user: domains.view/create/edit, databases.view/create, mail.view/create, dns.view/edit, ssl.view/manage, files.*, ftp.view/create, backup.view/create, cron.view/create/edit

2. Tạo middleware CheckModulePermission:
   - Kiểm tra permission dựa trên route prefix
   - Return 403 JSON nếu không có quyền

3. Tạo trait HasRoleHelpers cho User model:
   - isAdmin(), isReseller(), isUser()
   - canManage(User $target) - admin quản lý tất cả, reseller chỉ quản lý users của mình
   - scopeAccessibleBy(User $manager) - query scope lọc users theo quyền

4. Policy base class: ModulePolicy
   - Tự động check ownership (user_id) cho reseller/user
   - Admin bypass tất cả checks

5. Tests:
   - Admin có tất cả quyền
   - Reseller chỉ thấy customers của mình
   - User chỉ thấy resources của mình
   - Suspended user bị block

Chạy: php artisan db:seed --class=RolesAndPermissionsSeeder
```

---

## Task 1.6: Vue.js Frontend Scaffold
**Thời gian:** ~45 phút
**Kiểm tra hoàn thành:** Truy cập http://localhost:8000 thấy layout CloudStack với sidebar

```
>>> PROMPT cho Claude Code:
Tạo frontend Vue 3 scaffold cho VSISPanel với CloudStack-inspired design:

1. Setup Vue Router (resources/js/router/index.js):
   - Routes: /login, /dashboard, /websites, /email, /databases, /files, /dns, /ssl, /backup, /security, /monitoring, /cron, /settings, /users (admin), /reseller (reseller)
   - Navigation guards: check auth, check permission
   - Lazy loading cho mỗi route

2. Setup Pinia stores (resources/js/stores/):
   - auth.js: user state, login/logout actions, 2FA state
   - app.js: sidebar collapsed, dark mode, notifications
   - Persist dark mode preference trong localStorage

3. Setup Axios (resources/js/utils/api.js):
   - Base URL: /api
   - Request interceptor: attach Sanctum token
   - Response interceptor: handle 401 (redirect login), 403 (show forbidden), 422 (validation errors), 500 (toast error)
   - CSRF cookie handling

4. Layout Components (resources/js/layouts/):

   a) AppLayout.vue - Layout chính sau login:
      - Fixed Sidebar trái (260px, collapsible to 64px icons-only)
      - Top Navbar (56px): hamburger toggle, global search input, notification bell, user avatar dropdown
      - Content area với breadcrumb + padding
      - Transition animations khi collapse sidebar

   b) Sidebar.vue:
      - Logo "VSISPanel" ở top
      - Navigation groups với Heroicons:
        * MAIN: Dashboard (HomeIcon)
        * WEBSITES: Websites (GlobeAltIcon), Databases (CircleStackIcon), File Manager (FolderIcon)
        * EMAIL & DNS: Email (EnvelopeIcon), DNS (ServerStackIcon)
        * SECURITY: SSL (LockClosedIcon), Firewall (ShieldCheckIcon), Backup (CloudArrowUpIcon)
        * SERVER: Monitoring (ChartBarIcon), Cron Jobs (ClockIcon), Terminal (CommandLineIcon)
        * ADMIN (chỉ hiện cho admin/reseller): Users (UsersIcon), Plans (CubeIcon)
      - Active route highlighting
      - Collapsed mode: chỉ hiện icons, hover tooltip tên
      - Smooth transition animation

   c) TopNavbar.vue:
      - Hamburger menu (toggle sidebar)
      - Search input (Ctrl+K shortcut)
      - Notification bell với badge count
      - Dark/Light mode toggle (SunIcon/MoonIcon)
      - User dropdown: Profile, Settings, Logout

   d) AuthLayout.vue - Layout cho login page:
      - Center card design
      - VSISPanel logo + branding

5. Base Components (resources/js/components/ui/):
   - VCard.vue: card container với header, body, footer slots
   - VDataTable.vue: sortable columns, pagination, search, bulk actions, loading skeleton
   - VModal.vue: dialog modal với Headless UI, sizes (sm/md/lg/xl)
   - VDrawer.vue: slide-in drawer từ phải
   - VButton.vue: variants (primary/secondary/danger/ghost), sizes, loading state
   - VInput.vue: text input với label, error, helper text
   - VSelect.vue: dropdown select
   - VBadge.vue: status badges (success/warning/danger/info)
   - VToast.vue: toast notification system (success/error/warning/info)
   - VBreadcrumb.vue: auto-generate từ route
   - VEmptyState.vue: empty state với icon + message + action
   - VLoadingSkeleton.vue: shimmer loading placeholder
   - VConfirmDialog.vue: confirm trước delete actions

6. Dark Mode:
   - CSS variables trong tailwind.config.js
   - Toggle class 'dark' trên <html>
   - Tất cả components support dark: variants
   - Persist preference

7. Pages skeleton:
   - LoginPage.vue: email + password form, 2FA step, remember me
   - DashboardPage.vue: placeholder cards "Coming in Phase 2"

8. File resources/js/app.js: mount Vue app với router, pinia, global components

Đảm bảo npm run dev chạy và layout hiển thị đúng.
```

---

## Task 1.7: Dashboard Skeleton
**Thời gian:** ~30 phút
**Kiểm tra hoàn thành:** Dashboard hiện stat cards và placeholder charts

```
>>> PROMPT cho Claude Code:
Tạo Dashboard page cho VSISPanel với layout CloudStack-inspired:

1. DashboardPage.vue (resources/js/pages/DashboardPage.vue):

   Row 1 - Stat Cards (4 columns, responsive 2 cols trên tablet, 1 col mobile):
   - Websites: icon GlobeAltIcon, count, "Active" subtitle, màu blue
   - Databases: icon CircleStackIcon, count, "Running" subtitle, màu green
   - Email Accounts: icon EnvelopeIcon, count, "Active" subtitle, màu purple
   - Disk Usage: icon ServerIcon, percentage bar, "XX GB / YY GB", màu orange

   Row 2 - Server Metrics (2 columns):
   - CPU Usage: area chart placeholder (ApexCharts hoặc Chart.js)
   - Memory Usage: area chart placeholder
   - Hiện dummy data 24 data points

   Row 3 - 2 columns:
   - Left: Recent Activity feed (list 10 items: icon + message + timestamp)
   - Right: Quick Actions card:
     * "Add Website" button
     * "Create Database" button
     * "Add Email Account" button
     * "Create Backup" button

   Row 4:
   - System Information card: OS, Hostname, Uptime, PHP Version, MySQL Version, Nginx Version
   - Giá trị placeholder cho bây giờ

2. Tạo Pinia store: stores/dashboard.js
   - State: stats, metrics, recentActivity, systemInfo
   - Action: fetchDashboardData() - gọi API (placeholder)
   - Loading states cho mỗi section

3. API endpoint placeholder:
   - GET /api/dashboard/stats
   - GET /api/dashboard/metrics?period=24h
   - GET /api/dashboard/activity?limit=10
   - GET /api/dashboard/system-info
   - Controller trả về dummy data cho bây giờ

4. Responsive: cards stack trên mobile, charts full width trên tablet

Cài thêm nếu cần: npm install apexcharts vue3-apexcharts
```

---

## Task 1.8: System Command Executor
**Thời gian:** ~30 phút
**Kiểm tra hoàn thành:** Service class có thể chạy system commands an toàn

```
>>> PROMPT cho Claude Code:
Tạo System Command Executor service - lớp trung gian an toàn để chạy Linux commands:

1. app/Services/SystemCommandExecutor.php:
   - execute(string $command, array $args = []): CommandResult
   - executeAsRoot(string $command, array $args = []): CommandResult (sudo)
   - Whitelist allowed commands: nginx, php-fpm, mysql, systemctl, certbot, ufw, useradd, userdel, chown, chmod, mkdir, rm, cp, mv, tar, restic
   - REJECT mọi command không trong whitelist
   - Escape tất cả arguments với escapeshellarg()
   - Timeout: 30 seconds default, configurable
   - Log mọi command vào activity log: command, args, exit_code, user_id, timestamp
   - CommandResult DTO: success, exitCode, stdout, stderr, executionTime

2. app/Services/CommandResult.php (DTO):
   - Properties: success (bool), exitCode (int), stdout (string), stderr (string), executionTime (float)
   - Method: toArray(), isSuccess(), getOutput(), getError()

3. app/Services/ServiceManager.php:
   - Quản lý Linux services qua systemctl
   - start(string $service): CommandResult
   - stop(string $service): CommandResult
   - restart(string $service): CommandResult
   - reload(string $service): CommandResult
   - status(string $service): ServiceStatus
   - isRunning(string $service): bool
   - Whitelist services: nginx, apache2, php*-fpm, mysql, mariadb, postfix, dovecot, named, pdns, redis-server, fail2ban, proftpd
   - ServiceStatus DTO: name, isRunning, uptime, pid, memoryUsage

4. app/Services/ServerInfoCollector.php:
   - getOsInfo(): array (distro, version, kernel)
   - getCpuInfo(): array (model, cores, usage%)
   - getMemoryInfo(): array (total, used, free, percentage)
   - getDiskInfo(): array (partitions with size, used, free)
   - getNetworkInfo(): array (interfaces, IPs)
   - getUptime(): string
   - getLoadAverage(): array
   - Sử dụng /proc/cpuinfo, /proc/meminfo, df, free, uptime commands

5. Config file: config/vsispanel.php
   - command_timeout: 30
   - allowed_commands: [...]
   - allowed_services: [...]
   - sudo_password_required: false
   - log_commands: true

6. Tests:
   - Test whitelist blocking unauthorized commands
   - Test argument escaping
   - Test ServiceManager methods (mock exec)
   - Test ServerInfoCollector parsing

Chạy: php artisan test --filter=System
```

---

## Task 1.9: API Base Structure
**Thời gian:** ~25 phút
**Kiểm tra hoàn thành:** API trả đúng format, Swagger docs accessible

```
>>> PROMPT cho Claude Code:
Setup API base structure chuẩn cho VSISPanel:

1. app/Http/Traits/ApiResponseTrait.php:
   ```php
   successResponse($data, $message = '', $code = 200)
   errorResponse($message, $code, $errorCode = null, $errors = [])
   paginatedResponse($paginator, $resource)
   noContentResponse()
   ```
   Format:
   - Success: {"success": true, "data": {...}, "message": "...", "meta": {}}
   - Error: {"success": false, "error": {"code": "VALIDATION_ERROR", "message": "...", "errors": {}}}
   - Paginated: thêm "meta": {"current_page", "last_page", "per_page", "total"}

2. app/Http/Middleware/ForceJsonResponse.php:
   - Set Accept: application/json cho mọi /api/* request

3. app/Http/Middleware/ApiRateLimiting.php:
   - Default: 60 requests/minute
   - Auth routes: 10 requests/minute
   - Config trong config/vsispanel.php

4. app/Exceptions/Handler.php customization:
   - 404: {"success": false, "error": {"code": "NOT_FOUND", "message": "Resource not found"}}
   - 422: format validation errors
   - 403: permission denied
   - 500: internal server error (hide details in production)
   - Log tất cả 500 errors

5. Base Controller: app/Modules/Base/Http/Controllers/ApiController.php
   - use ApiResponseTrait
   - authorize() helper
   - Tất cả module controllers extend từ đây

6. API versioning setup:
   - routes/api.php prefix: /api/v1
   - Header: Accept: application/vnd.vsispanel.v1+json (optional)

7. Health check endpoint:
   - GET /api/health - trả về status, version, uptime
   - Không cần auth

8. Cài swagger: composer require darkaonline/l5-swagger
   - Config base swagger docs
   - Annotate LoginController làm mẫu
   - Accessible: /api/documentation

Chạy: curl http://localhost:8000/api/health
```

---

## Task 1.10: CI/CD & Docker Dev Environment
**Thời gian:** ~30 phút
**Kiểm tra hoàn thành:** `docker compose up` khởi động toàn bộ stack, GitHub Actions chạy tests

```
>>> PROMPT cho Claude Code:
Setup Docker development environment và CI/CD cho VSISPanel:

1. docker-compose.yml:
   Services:
   - app: PHP 8.3-FPM + Laravel (mount source code)
   - nginx: reverse proxy cho app (port 8000)
   - mysql: MySQL 8.0 (port 3306, persistent volume)
   - redis: Redis 7 (port 6379)
   - mailhog: email testing (port 1025/8025)
   - node: Node.js 20 cho frontend build (npm run dev)

2. docker/Dockerfile (PHP app):
   - FROM php:8.3-fpm
   - Install extensions: pdo_mysql, redis, gd, zip, intl, bcmath, pcntl
   - Install Composer
   - Working dir: /var/www/html
   - Non-root user: vsispanel

3. docker/nginx/default.conf:
   - Laravel Nginx config chuẩn

4. docker/.env.docker:
   - DB_HOST=mysql, REDIS_HOST=redis, MAIL_HOST=mailhog

5. Makefile cho common commands:
   - make up: docker compose up -d
   - make down: docker compose down
   - make shell: exec vào app container
   - make test: chạy tests trong container
   - make migrate: chạy migrations
   - make seed: chạy seeders
   - make fresh: migrate:fresh --seed
   - make logs: docker compose logs -f
   - make build-frontend: npm run build

6. .github/workflows/ci.yml:
   - Trigger: push to main, pull_request
   - Jobs:
     a) tests: PHP 8.3, MySQL 8.0, Redis - chạy PHPUnit/Pest
     b) frontend: Node 20 - npm run build, npm run lint
     c) code-style: PHP-CS-Fixer check
   - Cache composer và npm dependencies

7. .github/workflows/deploy.yml (placeholder):
   - Manual trigger
   - SSH deploy script placeholder

8. Makefile README section giải thích cách dùng

Chạy: docker compose up -d && make migrate && make seed
```

---

## Checklist Phase 1 Hoàn Thành

Sau khi hoàn thành tất cả tasks, verify:

```
>>> PROMPT cho Claude Code:
Verify Phase 1 completion - chạy các checks sau và báo cáo kết quả:

1. php artisan module:list - hiện tất cả 16 modules
2. php artisan migrate:status - tất cả migrations đã chạy
3. php artisan test - tất cả tests pass
4. curl -X POST http://localhost:8000/api/v1/auth/login -d '{"email":"admin@vsispanel.local","password":"password"}' - trả về token
5. Truy cập http://localhost:8000 - hiện login page
6. Login thành công - hiện dashboard với sidebar CloudStack
7. Dark mode toggle hoạt động
8. Sidebar collapse/expand hoạt động
9. API /api/health trả về đúng format
10. /api/documentation hiện Swagger docs

Liệt kê items nào PASS/FAIL và fix items FAIL.
```

---

## Commit Convention Phase 1
```
feat(auth): implement login with 2FA support
feat(rbac): setup roles and permissions with spatie
feat(ui): create CloudStack-inspired sidebar layout
feat(dashboard): add stat cards and metric placeholders
feat(core): system command executor with whitelist
feat(api): base API structure with standard responses
feat(docker): development environment setup
```
