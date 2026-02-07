# Phase 2: Web Hosting Core (Tuần 7-12)
> **Mục tiêu:** Quản lý domain, Nginx virtual hosts, PHP-FPM multi-version, MySQL databases, SSL, File Manager cơ bản.
> **Tiền đề:** Phase 1 hoàn thành — login, RBAC, dashboard, API base hoạt động.

## Progress Checklist

- [x] **Task 2.1: Domain Management Module** - Hoàn thành
  - [x] Domain model với relationships
  - [x] Subdomain model
  - [x] DomainService với create/update/delete/suspend/unsuspend
  - [x] DomainController với CRUD API
  - [x] SubdomainController
  - [x] CreateDomainRequest, UpdateDomainRequest validation
  - [x] DomainResource, SubdomainResource
  - [x] DomainPolicy authorization
  - [x] Feature tests cho Domain module (13 tests pass)
  - [x] Vue pages: WebsitesPage.vue, CreateDomainModal.vue, EditDomainModal.vue
  - [x] Domain store (Pinia)
  - [x] i18n translations (vi/en)

- [x] **Task 2.2: Nginx Virtual Host Service** - Hoàn thành
  - [x] NginxService với createVhost, deleteVhost, updateVhost, enableSsl, disableSsl, testConfig, reload
  - [x] getAccessLog, getErrorLog methods
  - [x] Blade templates: vhost.blade.php, vhost-ssl.blade.php, vhost-redirect.blade.php
  - [x] config/webserver.php
  - [x] WebServerServiceProvider
  - [x] Tích hợp với DomainService (auto create/delete vhost)
  - [x] Migration add username to users table
  - [x] Feature tests (13 tests pass)

- [x] **Task 2.3: PHP-FPM Multi-Version Management** - Hoàn thành
  - [x] PhpFpmService với getInstalledVersions, createPool, deletePool, updatePool, switchVersion
  - [x] getPhpInfo, updatePhpIni, getUserPhpSettings methods
  - [x] Blade template: pool.blade.php cho PHP-FPM pool config
  - [x] PhpController với API endpoints
  - [x] UpdatePhpVersionRequest, UpdatePhpSettingsRequest validation
  - [x] Routes cho PHP management
  - [x] Vue component: PhpSettingsModal.vue
  - [x] i18n translations (vi/en)
  - [x] Feature tests (9 PhpFpmService + 10 PhpApi = 19 tests pass)
  - [x] Sửa base Controller thêm AuthorizesRequests trait

- [ ] **Task 2.4: MySQL Database Management Module**
- [ ] **Task 2.5: Hosting Plans & Subscriptions**
- [ ] **Task 2.6: SSL Certificate Module**
- [ ] **Task 2.7: File Manager v1**
- [ ] **Task 2.8: Websites Page UI Complete**

---

## Task 2.1: Domain Management Module
**Thời gian:** ~1 giờ
**Kiểm tra:** Tạo domain → Nginx vhost được tạo → Website accessible

```
>>> PROMPT cho Claude Code:
Tạo module Domain hoàn chỉnh cho VSISPanel:

1. Model: Domain (app/Modules/Domain/Models/Domain.php)
   - Relationships: belongsTo User, hasMany Subdomain, hasOne SslCertificate, hasOne DnsZone
   - Scopes: active(), forUser($user), withSsl()
   - Accessors: documentRootPath, accessLogPath, errorLogPath
   - Events: DomainCreated, DomainDeleted

2. Model: Subdomain
   - Relationships: belongsTo Domain
   - fullName accessor: "{name}.{domain.name}"

3. Migration bổ sung nếu chưa có đủ fields:
   - domains: name, document_root, php_version, status, ssl_enabled, is_main, web_server_type (nginx/apache), access_log, error_log

4. Service: DomainService (app/Modules/Domain/Services/DomainService.php)
   - create(User $user, array $data): Domain
     * Validate domain format, check unique
     * Tạo document root: /home/{username}/domains/{domain}/public_html
     * Tạo thư mục logs: /home/{username}/domains/{domain}/logs
     * Set ownership: chown -R {username}:{username}
     * Tạo index.html mặc định "Welcome to {domain}"
     * Gọi NginxService::createVhost()
     * Gọi PhpFpmService::createPool() (nếu chưa có)
     * Fire DomainCreated event
   - delete(Domain $domain): void
     * Remove Nginx vhost
     * Remove PHP-FPM pool (nếu không còn domain nào dùng)
     * Archive document root (không xóa ngay, move to trash)
     * Fire DomainDeleted event
   - suspend/unsuspend
   - changePHPVersion(Domain $domain, string $version)

5. Controller: DomainController
   - GET /api/v1/domains - list (paginated, filterable by status)
   - POST /api/v1/domains - create
   - GET /api/v1/domains/{domain} - show
   - PUT /api/v1/domains/{domain} - update
   - DELETE /api/v1/domains/{domain} - delete
   - POST /api/v1/domains/{domain}/suspend - suspend
   - POST /api/v1/domains/{domain}/unsuspend - unsuspend

6. SubdomainController:
   - CRUD endpoints nested under domain: /api/v1/domains/{domain}/subdomains

7. FormRequests: CreateDomainRequest, UpdateDomainRequest, CreateSubdomainRequest
   - Validate: domain format (regex), unique, user quota check

8. Resources: DomainResource, DomainCollection, SubdomainResource

9. Policy: DomainPolicy
   - Admin: tất cả
   - Reseller: domains của customers
   - User: chỉ domains của mình

10. Vue pages:
    - WebsitesPage.vue: DataTable list domains (name, status, PHP, SSL badge, disk used, actions)
    - CreateDomainModal.vue: form tạo domain mới
    - DomainDetailPage.vue: tabs (Overview, Files, Databases, Email, SSL, DNS, Logs)

11. Tests Pest:
    - Test CRUD operations
    - Test quota enforcement (domains_limit)
    - Test permission (user A không thấy domain user B)
    - Test Nginx vhost generation

Lưu ý: NginxService sẽ được tạo ở Task 2.2, tạm dùng mock/placeholder.
```

---

## Task 2.2: Nginx Virtual Host Service
**Thời gian:** ~45 phút
**Kiểm tra:** Nginx config valid sau khi tạo domain

```
>>> PROMPT cho Claude Code:
Tạo NginxService quản lý virtual hosts cho VSISPanel:

1. Service: NginxService (app/Modules/WebServer/Services/NginxService.php)

   Methods:
   - createVhost(Domain $domain): void
     * Generate config từ Blade template
     * Save to /etc/nginx/sites-available/{domain.name}.conf
     * Symlink to sites-enabled
     * Test config: nginx -t
     * Reload nginx nếu test pass
     * Rollback nếu test fail

   - deleteVhost(Domain $domain): void
     * Remove symlink sites-enabled
     * Archive config (không xóa, move to backup dir)
     * Reload nginx

   - updateVhost(Domain $domain): void
     * Backup current config
     * Generate new config
     * Test → apply hoặc rollback

   - enableSsl(Domain $domain, string $certPath, string $keyPath): void
     * Update vhost config thêm SSL block
     * Add HTTP→HTTPS redirect

   - disableSsl(Domain $domain): void

   - testConfig(): bool (nginx -t)
   - reload(): CommandResult
   - getAccessLog(Domain $domain, int $lines = 100): string
   - getErrorLog(Domain $domain, int $lines = 100): string

2. Blade Templates (resources/views/templates/nginx/):

   a) vhost.blade.php - Standard HTTP vhost:
      ```nginx
      server {
          listen 80;
          server_name {{ $domain->name }} www.{{ $domain->name }};
          root {{ $domain->documentRootPath }};
          index index.php index.html;
          access_log {{ $domain->accessLogPath }};
          error_log {{ $domain->errorLogPath }};

          # Security headers
          add_header X-Frame-Options "SAMEORIGIN";
          add_header X-Content-Type-Options "nosniff";

          location / { try_files $uri $uri/ /index.php?$query_string; }

          location ~ \.php$ {
              fastcgi_pass unix:/run/php/php{{ $domain->php_version }}-fpm-{{ $domain->user->username }}.sock;
              fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
              include fastcgi_params;
          }

          location ~ /\.(?!well-known) { deny all; }
      }
      ```

   b) vhost-ssl.blade.php - HTTPS vhost:
      * Thêm listen 443 ssl http2
      * ssl_certificate, ssl_certificate_key paths
      * SSL security settings (TLS 1.2+, strong ciphers)
      * HSTS header

   c) vhost-redirect.blade.php - HTTP→HTTPS redirect

3. Config: config/webserver.php
   - nginx_sites_available: /etc/nginx/sites-available
   - nginx_sites_enabled: /etc/nginx/sites-enabled
   - config_backup_dir: /var/vsispanel/backups/nginx
   - default_php_version: '8.3'
   - template_path: resources/views/templates/nginx

4. Tests:
   - Test vhost config generation (string matching)
   - Test SSL config includes correct paths
   - Test config backup on update
   - Test rollback on failed nginx -t

Sử dụng SystemCommandExecutor từ Phase 1 cho tất cả system commands.
```

---

## Task 2.3: PHP-FPM Multi-Version Management
**Thời gian:** ~40 phút
**Kiểm tra:** Mỗi domain có thể chọn PHP version riêng

```
>>> PROMPT cho Claude Code:
Tạo PhpFpmService quản lý PHP-FPM multi-version cho VSISPanel:

1. Service: PhpFpmService (app/Modules/WebServer/Services/PhpFpmService.php)

   Methods:
   - getInstalledVersions(): array
     * Scan /etc/php/ directories
     * Return: ['7.4', '8.0', '8.1', '8.2', '8.3'] với status mỗi version

   - createPool(User $user, string $phpVersion): void
     * Generate pool config từ template
     * Save: /etc/php/{version}/fpm/pool.d/{username}.conf
     * Restart php{version}-fpm

   - deletePool(User $user, string $phpVersion): void

   - updatePool(User $user, string $phpVersion, array $settings): void
     * Update pool config
     * Validate settings
     * Restart FPM

   - switchVersion(Domain $domain, string $fromVersion, string $toVersion): void
     * Update domain PHP version
     * Update Nginx vhost (fastcgi_pass to new socket)
     * Ensure pool exists for new version
     * Restart services

   - getPhpInfo(string $version): array
     * Extensions loaded, php.ini settings

   - updatePhpIni(User $user, string $version, array $settings): void
     * Whitelist allowed settings: memory_limit, upload_max_filesize, post_max_size, max_execution_time, display_errors, etc.
     * Custom php.ini per user: /etc/php/{version}/fpm/conf.d/vsispanel-{username}.ini

2. Blade Template: resources/views/templates/php-fpm/pool.blade.php
   ```ini
   [{{ $user->username }}]
   user = {{ $user->username }}
   group = {{ $user->username }}
   listen = /run/php/php{{ $phpVersion }}-fpm-{{ $user->username }}.sock
   listen.owner = www-data
   listen.group = www-data
   pm = dynamic
   pm.max_children = {{ $settings['max_children'] ?? 5 }}
   pm.start_servers = {{ $settings['start_servers'] ?? 2 }}
   pm.min_spare_servers = {{ $settings['min_spare'] ?? 1 }}
   pm.max_spare_servers = {{ $settings['max_spare'] ?? 3 }}
   pm.max_requests = 500
   php_admin_value[open_basedir] = /home/{{ $user->username }}:/tmp:/var/lib/php
   php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen
   ```

3. Controller: PhpController (API endpoints)
   - GET /api/v1/php/versions - installed versions
   - GET /api/v1/php/{version}/info - PHP info
   - PUT /api/v1/domains/{domain}/php-version - switch version
   - PUT /api/v1/domains/{domain}/php-settings - update php.ini settings
   - GET /api/v1/domains/{domain}/php-settings - current settings

4. Vue: PhpSettingsDrawer.vue
   - Select PHP version dropdown
   - PHP settings form (memory_limit, upload_max_filesize, etc.)
   - Slider inputs cho numeric values
   - Save → restart confirmation

5. Tests:
   - Test pool config generation
   - Test PHP version switch updates Nginx config
   - Test php.ini whitelist (reject dangerous settings)
   - Test open_basedir isolation
```

---

## Task 2.4: MySQL Database Management Module
**Thời gian:** ~45 phút
**Kiểm tra:** Tạo database + user qua panel, truy cập phpMyAdmin SSO

```
>>> PROMPT cho Claude Code:
Tạo module Database cho VSISPanel:

1. Models:
   - ManagedDatabase: name, user_id, size_bytes, charset, collation
   - DatabaseUser: username, user_id, host, permissions (JSON), password_hash
   - Pivot: database_database_user (many-to-many)

2. Service: DatabaseService (app/Modules/Database/Services/DatabaseService.php)
   - createDatabase(User $user, string $name): ManagedDatabase
     * Prefix database name: {username}_{name}
     * Execute: CREATE DATABASE `{prefixed_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
     * Record in panel database
     * Check user quota (databases_limit)

   - deleteDatabase(ManagedDatabase $db): void
     * DROP DATABASE (với confirmation)
     * Remove records

   - createDatabaseUser(User $user, string $username, string $password): DatabaseUser
     * Prefix: {username}_{dbuser}
     * CREATE USER '{prefixed}'@'localhost' IDENTIFIED BY '{password}'
     * Record in panel database

   - deleteDatabaseUser(DatabaseUser $dbUser): void

   - grantAccess(DatabaseUser $dbUser, ManagedDatabase $db, array $privileges): void
     * GRANT {privileges} ON `{db}`.* TO '{user}'@'{host}'
     * Default privileges: SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX

   - revokeAccess(DatabaseUser $dbUser, ManagedDatabase $db): void

   - getDatabaseSize(ManagedDatabase $db): int (bytes)

   - backupDatabase(ManagedDatabase $db): string (path to dump file)
     * mysqldump --single-transaction

   - restoreDatabase(ManagedDatabase $db, string $dumpPath): void

   - importSql(ManagedDatabase $db, string $filePath): void

3. Controller: DatabaseController
   - CRUD /api/v1/databases
   - GET /api/v1/databases/{db}/size
   - POST /api/v1/databases/{db}/backup
   - POST /api/v1/databases/{db}/restore (upload dump)
   - POST /api/v1/databases/{db}/import

4. Controller: DatabaseUserController
   - CRUD /api/v1/database-users
   - POST /api/v1/database-users/{user}/grant
   - POST /api/v1/database-users/{user}/revoke

5. phpMyAdmin SSO:
   - Service: PhpMyAdminService
   - GET /api/v1/databases/{db}/phpmyadmin-url
   - Generate single-use SSO token
   - Redirect qua phpmyadmin/index.php?token={sso_token}
   - Token expires in 30 seconds

6. Vue pages:
   - DatabasesPage.vue: DataTable (name, size, users, charset, actions: phpMyAdmin, backup, delete)
   - CreateDatabaseModal.vue: name input, charset select, create user checkbox
   - DatabaseUsersTab.vue: user list, privilege matrix checkbox grid
   - ImportDatabaseModal.vue: file upload (.sql, .sql.gz)

7. Tests:
   - Test database creation with prefix
   - Test quota enforcement
   - Test privilege grant/revoke
   - Test phpMyAdmin SSO token generation
   - Test user isolation
```

---

## Task 2.5: Hosting Plans & Subscriptions
**Thời gian:** ~35 phút
**Kiểm tra:** Tạo plan, assign cho user, quota enforcement hoạt động

```
>>> PROMPT cho Claude Code:
Tạo module Hosting (Plans & Subscriptions) cho VSISPanel:

1. Models: Plan, Subscription (đã có migration từ Phase 1, bổ sung nếu cần)

2. Service: PlanService
   - create(array $data): Plan
   - update(Plan $plan, array $data): Plan
   - delete(Plan $plan): void (check không có active subscriptions)
   - getAvailablePlans(User $reseller = null): Collection

3. Service: SubscriptionService
   - subscribe(User $user, Plan $plan): Subscription
   - changePlan(Subscription $sub, Plan $newPlan): Subscription
     * Check new plan có đủ quota cho current usage
     * Migrate resource limits
   - suspend(Subscription $sub, string $reason): void
   - unsuspend(Subscription $sub): void
   - cancel(Subscription $sub): void
   - getUsage(Subscription $sub): ResourceUsage DTO
     * disk_used, bandwidth_used, domains_count, databases_count, email_count, ftp_count

4. Service: QuotaEnforcer
   - canCreateDomain(User $user): bool
   - canCreateDatabase(User $user): bool
   - canCreateEmailAccount(User $user): bool
   - canCreateFtpAccount(User $user): bool
   - checkDiskQuota(User $user, int $additionalBytes): bool
   - enforceQuotas(User $user): QuotaReport
   - Throw QuotaExceededException khi vượt limit

5. Middleware: CheckQuota
   - Tự động check quota trước create operations
   - Return 402/429 khi vượt quota

6. Controllers: PlanController, SubscriptionController
   - Admin: CRUD plans, manage all subscriptions
   - Reseller: manage plans (created by self), manage customer subscriptions
   - User: view own subscription and usage

7. Vue pages:
   - PlansPage.vue (admin/reseller): plan list, create/edit plan form
   - CreatePlanForm.vue: sliders cho limits (disk, bandwidth, domains, etc.)
   - SubscriptionPage.vue (user): current plan info, usage bars, upgrade button
   - ResourceUsageWidget.vue: circular progress bars cho mỗi resource

8. Tests:
   - Test quota enforcement prevents over-limit creation
   - Test plan change with resource validation
   - Test reseller can only manage own plans
```

---

## Task 2.6: SSL Certificate Module
**Thời gian:** ~40 phút
**Kiểm tra:** Auto-issue Let's Encrypt SSL cho domain

```
>>> PROMPT cho Claude Code:
Tạo module SSL cho VSISPanel:

1. Model: SslCertificate
   - domain_id, type (lets_encrypt/custom), status (pending/active/expired/revoked)
   - certificate_path, private_key_path, ca_bundle_path
   - issued_at, expires_at, auto_renew (boolean)
   - issuer, serial_number

2. Service: SslService (app/Modules/SSL/Services/SslService.php)

   - issueLetsEncrypt(Domain $domain): SslCertificate
     * Chạy certbot certonly --nginx -d {domain} -d www.{domain} --non-interactive --agree-tos
     * Parse output lấy cert paths
     * Update NginxService::enableSsl()
     * Save record
     * Schedule auto-renew

   - uploadCustomCert(Domain $domain, string $cert, string $key, string $caBundle = null): SslCertificate
     * Validate cert matches key (openssl verify)
     * Validate cert matches domain (CN/SAN check)
     * Save files to /etc/vsispanel/ssl/{domain}/
     * Update Nginx

   - renewCertificate(SslCertificate $ssl): void
     * certbot renew --cert-name {domain}
     * Reload nginx

   - revokeCertificate(SslCertificate $ssl): void

   - checkExpiry(SslCertificate $ssl): int (days remaining)

   - getCertificateInfo(string $certPath): array
     * Parse: issuer, subject, validity dates, SANs, serial

3. Scheduled Job: RenewExpiringCertificates
   - Chạy daily
   - Tìm certificates expiring trong 30 ngày
   - Auto-renew nếu auto_renew = true
   - Send notification nếu renewal fails

4. Controller: SslController
   - GET /api/v1/ssl - list all certificates
   - POST /api/v1/domains/{domain}/ssl/lets-encrypt - issue Let's Encrypt
   - POST /api/v1/domains/{domain}/ssl/custom - upload custom cert
   - POST /api/v1/ssl/{ssl}/renew - manual renew
   - DELETE /api/v1/ssl/{ssl} - revoke and remove
   - GET /api/v1/ssl/{ssl}/info - certificate details

5. Vue pages:
   - SslPage.vue: certificate list (domain, type, status, expires, auto-renew toggle)
   - IssueSslModal.vue: chọn domain, Let's Encrypt hoặc upload custom
   - SslDetailDrawer.vue: certificate info, renewal history
   - Status badges: Active (green), Expiring Soon (orange), Expired (red)

6. Notifications:
   - SslExpiringNotification: send 30/14/7/1 ngày trước expiry
   - SslRenewalFailedNotification

7. Tests:
   - Test Let's Encrypt issue flow (mock certbot)
   - Test custom cert validation
   - Test expiry check
   - Test auto-renewal job
```

---

## Task 2.7: File Manager v1
**Thời gian:** ~45 phút
**Kiểm tra:** Browse, upload, edit files qua web interface

```
>>> PROMPT cho Claude Code:
Tạo module FileManager v1 cho VSISPanel:

1. Service: FileManagerService (app/Modules/FileManager/Services/FileManagerService.php)

   - listDirectory(User $user, string $path): array
     * Validate path trong user home directory (prevent directory traversal!)
     * Return: [{name, type (file/dir), size, permissions, modified_at, extension}]
     * Sort: directories first, then files

   - readFile(User $user, string $path): string
     * Check file size < 2MB (editable limit)
     * Validate text file (not binary)
     * Return content

   - writeFile(User $user, string $path, string $content): void
     * Backup original trước khi overwrite
     * Set proper ownership

   - createFile(User $user, string $path): void
   - createDirectory(User $user, string $path): void
   - delete(User $user, string $path): void (move to .trash, not permanent)
   - rename(User $user, string $oldPath, string $newPath): void
   - copy(User $user, string $from, string $to): void
   - move(User $user, string $from, string $to): void
   - changePermissions(User $user, string $path, string $permissions): void
   - getInfo(User $user, string $path): FileInfo DTO

   - upload(User $user, string $directory, UploadedFile $file): void
     * Check disk quota
     * Validate filename (no special chars)
     * Store with proper ownership

   - download(User $user, string $path): StreamedResponse

   - compress(User $user, array $paths, string $destination, string $type = 'zip'): void
   - extract(User $user, string $archivePath, string $destination): void

   SECURITY:
   - ALWAYS validate path starts with /home/{username}/
   - Prevent symlink attacks
   - Block access to hidden system files
   - Size checks before operations

2. Controller: FileManagerController
   - GET /api/v1/files?path= - list directory
   - GET /api/v1/files/read?path= - read file content
   - POST /api/v1/files/write - save file
   - POST /api/v1/files/create - create file/dir
   - DELETE /api/v1/files?path= - delete
   - POST /api/v1/files/rename - rename
   - POST /api/v1/files/copy - copy
   - POST /api/v1/files/move - move
   - POST /api/v1/files/permissions - chmod
   - POST /api/v1/files/upload - upload (multipart)
   - GET /api/v1/files/download?path= - download
   - POST /api/v1/files/compress - compress
   - POST /api/v1/files/extract - extract

3. Vue page: FileManagerPage.vue
   - Two-pane layout: directory tree (left, 250px) + file list (right)
   - Toolbar: Upload, New File, New Folder, Delete, Rename, Compress, Extract, Permissions
   - Drag-and-drop upload zone
   - File list: icon (per extension), name, size, modified, permissions
   - Double-click file: open editor (CodeMirror) hoặc download
   - Right-click context menu: Open, Edit, Rename, Copy, Move, Delete, Permissions, Download
   - Breadcrumb navigation cho current path
   - Multi-select với Shift/Ctrl click
   - Upload progress bar

4. Cài thêm: npm install codemirror @codemirror/lang-javascript @codemirror/lang-php @codemirror/lang-html @codemirror/lang-css @codemirror/theme-one-dark

5. Tests:
   - Test directory traversal prevention (../../../etc/passwd PHẢI bị block)
   - Test file CRUD operations
   - Test disk quota check on upload
   - Test permission isolation between users
```

---

## Task 2.8: Websites Page UI Hoàn Chỉnh
**Thời gian:** ~30 phút
**Kiểm tra:** Trang Websites hiện đầy đủ thông tin domains với actions

```
>>> PROMPT cho Claude Code:
Hoàn thiện trang Websites (WebsitesPage.vue) cho VSISPanel:

1. WebsitesPage.vue - redesign hoàn chỉnh:
   - Header: "Websites" title + "Add Website" button (primary)
   - Search bar + filters: Status (All/Active/Suspended), PHP version
   - DataTable columns:
     * Domain name (link to detail) + favicon
     * Status badge (Active=green, Suspended=yellow, Disabled=gray)
     * PHP version badge (e.g., "PHP 8.3" blue badge)
     * SSL status: lock icon green (active) / orange (expiring) / red (expired) / gray (none)
     * Disk usage: mini progress bar + "XX MB / YY GB"
     * Created date
     * Actions dropdown: Visit Site, File Manager, Databases, Email, DNS, SSL, PHP Settings, Logs, Suspend, Delete
   - Bulk actions: Suspend selected, Delete selected
   - Empty state khi chưa có domain: illustration + "No websites yet" + Add Website button
   - Pagination: 15 per page

2. DomainDetailPage.vue (/websites/{domain}):
   - Header: domain name + Visit Site button + status badge
   - Tab navigation:
     * Overview: document root, PHP version, SSL status, created date, resource usage cards
     * Files: embedded FileManager (filtered to domain's document root)
     * Databases: list databases linked to this domain
     * Email: email accounts cho domain này
     * SSL: certificate info, renew button, auto-renew toggle
     * DNS: DNS zone editor cho domain
     * Logs: Access log + Error log viewer với live tail
     * Settings: PHP version select, PHP settings, Nginx config view

3. AddWebsiteWizard.vue (multi-step modal):
   - Step 1: Enter domain name, validate format + unique
   - Step 2: Select PHP version
   - Step 3: Auto-create options (SSL, DNS zone, email, database) checkboxes
   - Step 4: Summary + Create button
   - Loading state during creation
   - Success: redirect to domain detail page

4. Pinia store: stores/domains.js
   - State: domains list, currentDomain, loading states
   - Actions: fetchDomains, createDomain, deleteDomain, suspendDomain
   - Getters: activeDomains, domainsCount

Đảm bảo responsive trên mobile: table chuyển thành card list.
```

---

## Checklist Phase 2 Hoàn Thành

```
>>> PROMPT cho Claude Code:
Verify Phase 2 completion:

1. Tạo domain "test.local" qua API → kiểm tra Nginx vhost config được tạo
2. Tạo subdomain "app.test.local" → Nginx config updated
3. Switch PHP version từ 8.3 → 8.1 → kiểm tra Nginx vhost cập nhật fastcgi_pass
4. Tạo database "testdb" → MySQL database exists
5. Tạo database user → GRANT permissions hoạt động
6. phpMyAdmin SSO link hoạt động
7. Issue SSL (mock certbot) → Nginx HTTPS config
8. File Manager: upload, edit, delete files hoạt động
9. Quota enforcement: vượt limit bị block
10. Websites page UI hiển thị đúng, responsive

Chạy toàn bộ tests: php artisan test
Liệt kê PASS/FAIL và fix items FAIL.
```

---

## Commit Convention Phase 2
```
feat(domain): CRUD with Nginx vhost auto-generation
feat(nginx): virtual host service with SSL support
feat(php): multi-version PHP-FPM management
feat(database): MySQL database and user management
feat(hosting): plans, subscriptions, quota enforcement
feat(ssl): Let's Encrypt auto-issue and renewal
feat(files): web-based file manager v1
feat(ui): complete Websites page with detail view
```
