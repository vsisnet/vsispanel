# Phase 4: Security & Backup (Tuần 19-24)
> **Mục tiêu:** Bảo mật nhiều lớp (Firewall, WAF, Fail2Ban), backup/restore toàn diện.
> **Tiền đề:** Phase 3 hoàn thành — Email, DNS, FTP hoạt động.

---

## Task 4.1: Firewall Management Module
**Thời gian:** ~40 phút
**Kiểm tra:** Thêm/xóa firewall rules qua panel, UFW updated

```
>>> PROMPT cho Claude Code:
Tạo module Firewall cho VSISPanel:

1. Model: FirewallRule
   - action (enum: allow/deny/limit), direction (enum: in/out)
   - protocol (enum: tcp/udp/any), port (string, e.g. "80", "8000:8100")
   - source_ip (nullable), destination_ip (nullable)
   - comment, is_active (boolean), priority (integer)

2. Service: FirewallService (app/Modules/Firewall/Services/FirewallService.php)
   Wrapper cho UFW:

   - enable(): CommandResult (ufw enable)
   - disable(): CommandResult
   - getStatus(): array (ufw status verbose → parse output)
   - addRule(array $data): FirewallRule
     * Build UFW command: ufw {allow|deny} {proto} from {source} to any port {port}
     * Execute command
     * Save to database
   - deleteRule(FirewallRule $rule): void
     * ufw delete {rule_number}
   - toggleRule(FirewallRule $rule): void
   - getDefaultPolicies(): array (input/output/forward)
   - setDefaultPolicy(string $direction, string $policy): void
   - getRulesList(): Collection (từ database + verify với ufw status)
   - resetToDefaults(): void
     * ufw reset
     * Re-apply essential rules (SSH, HTTP, HTTPS, panel port)

   Essential rules (auto-created on install):
   - Allow SSH (22)
   - Allow HTTP (80)
   - Allow HTTPS (443)
   - Allow Panel (8443 hoặc custom)
   - Allow FTP (21, 20, passive range)
   - Allow Mail (25, 587, 993, 995)
   - Allow DNS (53 tcp/udp)

3. Controller: FirewallController
   - GET /api/v1/firewall/status
   - GET /api/v1/firewall/rules
   - POST /api/v1/firewall/rules
   - DELETE /api/v1/firewall/rules/{rule}
   - PUT /api/v1/firewall/rules/{rule}/toggle
   - POST /api/v1/firewall/enable
   - POST /api/v1/firewall/disable
   - POST /api/v1/firewall/reset

4. Vue page: FirewallPage.vue
   - Header: Firewall status toggle (ON/OFF) với confirmation
   - Default policies display (INPUT: DENY, OUTPUT: ALLOW)
   - Rules DataTable: action badge, direction, protocol, port, source, comment, active toggle, delete
   - Add Rule modal: action select, protocol, port input, source IP (optional), comment
   - Quick add buttons: "Allow SSH", "Allow MySQL Remote", "Block IP"
   - Warning banner khi firewall disabled

5. Tests:
   - Test UFW command building
   - Test essential rules auto-creation
   - Test rule CRUD
   - Test cannot delete essential rules (SSH, panel port)
```

---

## Task 4.2: Fail2Ban Integration
**Thời gian:** ~35 phút
**Kiểm tra:** Fail2Ban jails configurable, banned IPs visible

```
>>> PROMPT cho Claude Code:
Tạo Fail2Ban integration cho VSISPanel:

1. Service: Fail2BanService (app/Modules/Firewall/Services/Fail2BanService.php)
   - getStatus(): array (fail2ban-client status)
   - getJails(): array
     * List all jails: sshd, vsispanel-auth, postfix, dovecot, proftpd, nginx-http-auth
     * Per jail: status, currently banned, total banned, ban time, find time, max retry
   - getJailStatus(string $jail): array
     * Banned IPs list, ban count, filter regex
   - getBannedIps(): array (all jails combined)
   - banIp(string $ip, string $jail = 'manual'): void
   - unbanIp(string $ip, string $jail = null): void
   - setJailConfig(string $jail, array $config): void
     * bantime, findtime, maxretry
   - createCustomJail(string $name, array $config): void
   - restart(): CommandResult

2. Custom Fail2Ban jails:
   - resources/views/templates/fail2ban/jail.d/vsispanel-auth.conf
     * Monitor panel login failures
     * Filter: /var/log/vsispanel/auth.log
     * maxretry: 5, bantime: 3600
   - vsispanel-api.conf: API brute force protection

3. IP Management Service: IpManagementService
   - addToWhitelist(string $ip): void (fail2ban ignoreip + firewall allow)
   - removeFromWhitelist(string $ip): void
   - addToBlacklist(string $ip): void (fail2ban ban + firewall deny)
   - removeFromBlacklist(string $ip): void
   - getWhitelist(): array
   - getBlacklist(): array
   - isBlacklisted(string $ip): bool
   - getIpInfo(string $ip): array (country, ISP via ip-api.com)

4. Controller: Fail2BanController
   - GET /api/v1/security/fail2ban/status
   - GET /api/v1/security/fail2ban/jails
   - GET /api/v1/security/fail2ban/jails/{jail}
   - PUT /api/v1/security/fail2ban/jails/{jail}/config
   - GET /api/v1/security/fail2ban/banned
   - POST /api/v1/security/fail2ban/ban
   - POST /api/v1/security/fail2ban/unban
   - GET /api/v1/security/ip-whitelist
   - POST /api/v1/security/ip-whitelist
   - DELETE /api/v1/security/ip-whitelist/{entry}
   - GET /api/v1/security/ip-blacklist
   - POST /api/v1/security/ip-blacklist

5. Vue components:
   - Fail2BanStatus.vue: jails grid cards (name, status, banned count, config)
   - BannedIpsTable.vue: IP, jail, ban time remaining, unban button
   - IpManagementTab.vue: whitelist/blacklist management, add IP form

6. Tests:
   - Test fail2ban command parsing
   - Test ban/unban operations
   - Test custom jail config generation
   - Test IP whitelist prevents banning
```

---

## Task 4.3: ModSecurity WAF & Malware Scanner
**Thời gian:** ~30 phút
**Kiểm tra:** WAF rules configurable, ClamAV scan runnable

```
>>> PROMPT cho Claude Code:
Tạo WAF và Malware scanning integration cho VSISPanel:

1. Service: WafService (app/Modules/Firewall/Services/WafService.php)
   ModSecurity v3 with OWASP CRS:

   - getStatus(): array (enabled/disabled, rules count)
   - enable(): void (add modsecurity on trong Nginx config)
   - disable(): void
   - getAuditLog(int $limit = 100): array (parse modsec_audit.log)
   - setMode(string $mode): void (DetectionOnly / On)
   - getRulesets(): array (OWASP CRS categories)
   - enableRuleset(string $ruleset): void
   - disableRuleset(string $ruleset): void
   - addWhitelistRule(string $ruleId, string $domain = null): void
   - getWhitelistedRules(): array

2. Service: MalwareScanService (app/Modules/Firewall/Services/MalwareScanService.php)
   ClamAV integration:

   - scanPath(string $path): ScanResult
     * clamscan -ri {path}
     * Parse output: infected files, details
   - scanDomain(Domain $domain): ScanResult
     * Scan domain document root
   - scanAll(): void (dispatch as background job)
   - quarantineFile(string $path): void (move to /var/vsispanel/quarantine/)
   - restoreFile(string $quarantinePath, string $originalPath): void
   - getQuarantinedFiles(): array
   - updateDefinitions(): CommandResult (freshclam)

3. Job: ScheduledMalwareScan
   - Chạy weekly
   - Scan all domains
   - Notify admin if infections found

4. Controllers: WafController, MalwareScanController
   - WAF: status, enable/disable, mode, audit log, whitelist
   - Malware: scan domain, scan results, quarantine management

5. Vue components:
   - WafSettingsCard.vue: toggle, mode select, rulesets checklist
   - WafAuditLog.vue: filterable log table
   - MalwareScanCard.vue: scan button, last scan date, results summary
   - ScanResultsModal.vue: infected files list, quarantine/ignore actions

6. Tests:
   - Test ModSecurity config generation
   - Test ClamAV output parsing
   - Test quarantine file operations
```

---

## Task 4.4: Backup & Restore Module
**Thời gian:** ~1 giờ
**Kiểm tra:** Full backup → restore hoạt động, scheduled backups run

```
>>> PROMPT cho Claude Code:
Tạo module Backup hoàn chỉnh cho VSISPanel sử dụng Restic:

1. Models:
   - BackupConfig: user_id (nullable = server-wide), schedule (cron expression), retention_daily, retention_weekly, retention_monthly, destination_type (enum: local/s3/ftp/b2), destination_config (JSON encrypted), is_active, last_run_at, next_run_at
   - Backup: user_id, backup_config_id, type (enum: full/files/databases/emails/config), status (enum: pending/running/completed/failed), size_bytes, snapshot_id, started_at, completed_at, error_message, metadata (JSON: included items)

2. Service: BackupService (app/Modules/Backup/Services/BackupService.php)

   - initRepository(BackupConfig $config): void
     * restic init --repo {destination}
     * Set encryption password from config

   - createBackup(User $user, string $type = 'full'): Backup
     * Lock to prevent concurrent backups
     * Type 'full': files + databases + emails + configs
     * Type 'files': user home directory only
     * Type 'databases': mysqldump all user databases
     * Type 'emails': maildir backup
     * Type 'config': Nginx, PHP, DNS configs

     Process:
     a) Create temporary staging directory
     b) Copy/dump relevant data
     c) restic backup --repo {dest} --tag {user_id} --tag {type} {staging_dir}
     d) Record snapshot_id
     e) Cleanup staging
     f) Update backup record with size, duration
     g) Apply retention policy

   - restore(Backup $backup, array $options): void
     * Options: restore_files (bool), restore_databases (bool), restore_emails (bool), target_path (override)
     * restic restore {snapshot_id} --repo {dest} --target {staging}
     * Selective restore based on options
     * For databases: mysql import from dump
     * Set proper file ownership

   - deleteBackup(Backup $backup): void
     * restic forget {snapshot_id}
     * restic prune

   - listSnapshots(User $user = null): array
   - getBackupSize(Backup $backup): int
   - verifyBackup(Backup $backup): bool (restic check)
   - downloadBackup(Backup $backup): StreamedResponse
     * restic dump {snapshot_id} / → tar stream

   - applyRetention(BackupConfig $config): void
     * restic forget --keep-daily {d} --keep-weekly {w} --keep-monthly {m}
     * restic prune

3. Backup Destinations:
   - LocalDestination: /var/vsispanel/backups/{user_id}/
   - S3Destination: s3:{bucket}/{prefix}
   - FtpDestination: sftp:{host}:{path}
   - B2Destination: b2:{bucket}:{path}
   - Interface: BackupDestination → getResticRepo(), getEnvVars()

4. Scheduled Jobs:
   - ProcessScheduledBackups: chạy mỗi giờ, check backup configs, dispatch BackupJob
   - BackupJob: queue job, chạy actual backup, notify on complete/fail
   - CleanupOldBackups: apply retention policies

5. Controller: BackupController
   - GET /api/v1/backups - list backups (user's own, admin: all)
   - POST /api/v1/backups - create backup now
   - GET /api/v1/backups/{backup} - detail
   - POST /api/v1/backups/{backup}/restore - restore
   - DELETE /api/v1/backups/{backup} - delete
   - GET /api/v1/backups/{backup}/download - download

6. Controller: BackupConfigController
   - GET /api/v1/backup-configs - list configs
   - POST /api/v1/backup-configs - create schedule
   - PUT /api/v1/backup-configs/{config} - update
   - DELETE /api/v1/backup-configs/{config} - delete
   - POST /api/v1/backup-configs/{config}/test - test destination connection

7. Vue pages:
   - BackupPage.vue: tabs (Backups, Schedules, Settings)
   - BackupsTab.vue: timeline/list view
     * Each backup: type icon, date, size, duration, status badge
     * Actions: Restore, Download, Delete
   - RestoreWizard.vue: multi-step
     * Step 1: Select backup
     * Step 2: Choose what to restore (files/databases/emails checkboxes)
     * Step 3: Confirmation + warnings
     * Step 4: Progress bar + log output
   - ScheduleConfigForm.vue:
     * Schedule: preset buttons (Daily/Weekly/Monthly) + custom cron
     * Destination: type select → config form (S3 bucket, FTP host, etc.)
     * Retention: daily/weekly/monthly sliders
     * Test connection button
   - BackupProgressModal.vue: real-time progress via WebSocket

8. Notifications:
   - BackupCompletedNotification
   - BackupFailedNotification (urgent)
   - BackupStorageLowNotification

9. Tests:
   - Test backup creation flow (mock restic)
   - Test restore selective options
   - Test retention policy application
   - Test scheduled backup job dispatch
   - Test destination configuration
```

---

## Task 4.5: Security Dashboard
**Thời gian:** ~30 phút
**Kiểm tra:** Security overview page với tất cả security features

```
>>> PROMPT cho Claude Code:
Tạo Security Dashboard page tổng hợp cho VSISPanel:

1. SecurityPage.vue (/security):
   Tab navigation: Overview, Firewall, Fail2Ban, WAF, Malware, IP Management, Audit Log

   Overview tab:
   - Security Score card (0-100): tính dựa trên:
     * Firewall enabled (+20)
     * Fail2Ban active (+15)
     * WAF enabled (+15)
     * 2FA enabled for admin (+15)
     * SSL on all domains (+15)
     * No malware found (+10)
     * Backup configured (+10)
   - Score gauge chart (green >80, yellow 60-80, red <60)
   - Quick status cards row: Firewall (on/off), Fail2Ban (banned count), WAF (mode), Last Scan (date)
   - Recommendations list: actionable items to improve score
   - Recent security events (last 20 from audit log)

2. AuditLogTab.vue:
   - DataTable: timestamp, user, action, resource, IP address, details
   - Filters: date range, user, action type, resource type
   - Export to CSV
   - Sử dụng spatie/laravel-activitylog data

3. Service: SecurityScoreService
   - calculateScore(): SecurityScore DTO
   - getRecommendations(): array
   - getRecentEvents(int $limit = 20): Collection

4. Controller: SecurityController
   - GET /api/v1/security/overview
   - GET /api/v1/security/score
   - GET /api/v1/security/recommendations
   - GET /api/v1/security/audit-log?filters...

5. Tests:
   - Test security score calculation
   - Test recommendations logic
```

---

## Checklist Phase 4 Hoàn Thành

```
>>> PROMPT cho Claude Code:
Verify Phase 4 completion:

1. Firewall rules CRUD qua panel → UFW updated
2. Essential rules không thể xóa
3. Fail2Ban jails configurable
4. Ban/unban IP hoạt động
5. IP whitelist/blacklist management
6. ModSecurity WAF toggle hoạt động
7. ClamAV scan returns results (mock)
8. Full backup created → snapshot exists
9. Selective restore hoạt động
10. Scheduled backup config saved → job dispatched
11. Backup download hoạt động
12. Security score calculated correctly
13. Audit log shows all actions

Chạy: php artisan test
Liệt kê PASS/FAIL và fix.
```

---

## Commit Convention Phase 4
```
feat(firewall): UFW firewall management with GUI
feat(fail2ban): Fail2Ban integration and monitoring
feat(waf): ModSecurity WAF management
feat(malware): ClamAV malware scanning
feat(backup): Restic backup with multiple destinations
feat(backup): scheduled backups and retention policies
feat(backup): restore wizard with selective options
feat(security): security dashboard and scoring
feat(audit): comprehensive audit logging
```
