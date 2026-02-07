# Phase 3: Email & DNS (Tuần 13-18)
> **Mục tiêu:** Hệ thống email hoàn chỉnh (Postfix/Dovecot), DNS management (PowerDNS), FTP accounts.
> **Tiền đề:** Phase 2 hoàn thành — domains, PHP, databases, SSL hoạt động.

---

## Task 3.1: Postfix & Dovecot Service Integration
**Thời gian:** ~1 giờ
**Kiểm tra:** Mail server config generated đúng format

```
>>> PROMPT cho Claude Code:
Tạo services tích hợp Postfix và Dovecot cho VSISPanel:

1. Service: PostfixService (app/Modules/Mail/Services/PostfixService.php)
   - addDomain(string $domain): void
     * Thêm domain vào /etc/postfix/virtual_domains
     * Reload postfix
   - removeDomain(string $domain): void
   - addMailbox(string $email, string $password): void
     * Thêm vào /etc/postfix/virtual_mailboxes
     * Hash password: doveadm pw -s SSHA512
     * Thêm vào /etc/postfix/virtual_users
     * Tạo maildir: /var/mail/vhosts/{domain}/{user}/
   - removeMailbox(string $email): void
   - addAlias(string $source, string $destination): void
     * Thêm vào /etc/postfix/virtual_aliases
   - removeAlias(string $source): void
   - addForwarding(string $email, string $forwardTo): void
   - setQuota(string $email, int $quotaMB): void
   - reload(): CommandResult
   - getQueueStatus(): array (mailq)
   - flushQueue(): CommandResult

2. Service: DovecotService (app/Modules/Mail/Services/DovecotService.php)
   - createMailbox(string $email, string $password, int $quotaMB): void
     * Tạo entry trong userdb
     * Set quota: userdb_quota_rule=*:bytes={quota}
   - deleteMailbox(string $email): void
   - changePassword(string $email, string $newPassword): void
   - getMailboxSize(string $email): int (bytes)
   - getMailboxInfo(string $email): array (messages count, last login)
   - reload(): CommandResult

3. Blade config templates:
   - resources/views/templates/postfix/main.cf.blade.php
   - resources/views/templates/postfix/master.cf.blade.php
   - resources/views/templates/dovecot/dovecot.conf.blade.php
   - resources/views/templates/dovecot/10-mail.conf.blade.php
   - resources/views/templates/dovecot/10-auth.conf.blade.php

4. Service: MailSecurityService
   - generateSPF(string $domain, string $serverIp): string
     * Return TXT record value: "v=spf1 mx a ip4:{serverIp} ~all"
   - generateDKIM(string $domain): array [selector, publicKey, privateKey]
     * opendkim-genkey
     * Return DNS record value
   - generateDMARC(string $domain, string $adminEmail): string
     * Return TXT record value
   - Auto-add DNS records khi tạo mail domain

5. Tests:
   - Test config file generation
   - Test password hashing
   - Test SPF/DKIM/DMARC record generation
   - Test maildir path calculation
```

---

## Task 3.2: Email Account Management Module
**Thời gian:** ~45 phút
**Kiểm tra:** CRUD email accounts, forwarding, auto-responder

```
>>> PROMPT cho Claude Code:
Tạo module Mail hoàn chỉnh cho VSISPanel:

1. Models:
   - MailDomain: domain_id, is_active, catch_all_address, max_accounts
   - MailAccount: mail_domain_id, user_id, email, password_hash, quota_mb, quota_used_bytes, status (active/suspended/disabled), auto_responder_enabled, auto_responder_subject, auto_responder_message, last_login_at
   - MailForward: mail_account_id, forward_to, keep_copy (boolean)
   - MailAlias: mail_domain_id, source_address, destination_address

2. Service: MailAccountService
   - create(User $user, MailDomain $mailDomain, array $data): MailAccount
     * Check quota (email_accounts_limit)
     * Call PostfixService::addMailbox()
     * Call DovecotService::createMailbox()
     * If auto_create_dns: add MX, SPF, DKIM records
   - delete(MailAccount $account): void
   - changePassword(MailAccount $account, string $newPassword): void
   - suspend/unsuspend
   - setAutoResponder(MailAccount $account, string $subject, string $message): void
   - disableAutoResponder(MailAccount $account): void
   - addForwarding(MailAccount $account, string $forwardTo, bool $keepCopy): MailForward
   - removeForwarding(MailForward $forward): void
   - getUsageStats(MailAccount $account): array

3. Controller: MailDomainController
   - GET /api/v1/mail/domains - list mail domains
   - POST /api/v1/mail/domains - enable mail for domain
   - DELETE /api/v1/mail/domains/{mailDomain} - disable mail for domain

4. Controller: MailAccountController
   - GET /api/v1/mail/accounts?domain={id} - list accounts
   - POST /api/v1/mail/accounts - create account
   - PUT /api/v1/mail/accounts/{account} - update
   - DELETE /api/v1/mail/accounts/{account} - delete
   - PUT /api/v1/mail/accounts/{account}/password - change password
   - PUT /api/v1/mail/accounts/{account}/auto-responder - set auto-responder
   - POST /api/v1/mail/accounts/{account}/forwards - add forwarding
   - DELETE /api/v1/mail/forwards/{forward} - remove forwarding

5. Controller: MailAliasController - CRUD cho aliases

6. Webmail Integration:
   - Service: WebmailService
   - GET /api/v1/mail/accounts/{account}/webmail-url
   - SSO vào Roundcube (auth_token method)
   - Config Roundcube kết nối Dovecot

7. Vue pages:
   - EmailPage.vue: tabs (Accounts, Aliases, Forwards, Spam Settings)
   - MailAccountsTab.vue: DataTable (email, quota usage bar, status, last login, actions)
   - CreateMailAccountModal.vue: email prefix input + @domain select, password, quota slider
   - AutoResponderModal.vue: subject, message, date range
   - ForwardingDrawer.vue: forward-to list, keep copy toggle
   - MailConfigInfo.vue: IMAP/POP3/SMTP server settings display (cho user copy)

8. Tests:
   - Test account creation calls PostfixService + DovecotService
   - Test quota check prevents over-limit
   - Test forwarding CRUD
   - Test auto-responder toggle
   - Test SPF/DKIM/DMARC auto-creation
```

---

## Task 3.3: Spam Filtering (Rspamd)
**Thời gian:** ~30 phút
**Kiểm tra:** Spam settings configurable per domain

```
>>> PROMPT cho Claude Code:
Tạo integration Rspamd spam filtering cho VSISPanel:

1. Service: RspamdService (app/Modules/Mail/Services/RspamdService.php)
   - getStatus(): array (rspamd statistics)
   - getSpamScore(string $domain): float (current threshold)
   - setSpamScore(string $domain, float $score): void
   - addToWhitelist(string $email): void
   - removeFromWhitelist(string $email): void
   - addToBlacklist(string $email): void
   - removeFromBlacklist(string $email): void
   - getWhitelist(): array
   - getBlacklist(): array
   - getStatistics(): array (ham/spam counts, learn counts)
   - trainHam(string $messageFile): void
   - trainSpam(string $messageFile): void

2. Config template: rspamd local.d/actions.conf
   - reject = 15; (hard reject)
   - add_header = 6; (add spam header)
   - greylist = 4; (greylist)

3. Controller: SpamController
   - GET /api/v1/mail/spam/settings
   - PUT /api/v1/mail/spam/settings
   - GET /api/v1/mail/spam/whitelist
   - POST /api/v1/mail/spam/whitelist
   - DELETE /api/v1/mail/spam/whitelist/{entry}
   - GET /api/v1/mail/spam/blacklist
   - POST /api/v1/mail/spam/blacklist
   - DELETE /api/v1/mail/spam/blacklist/{entry}

4. Vue component: SpamSettingsTab.vue
   - Spam score threshold slider (1-20)
   - Whitelist/Blacklist management (add/remove entries)
   - Statistics display

5. Tests:
   - Test Rspamd API calls (mock)
   - Test whitelist/blacklist CRUD
```

---

## Task 3.4: PowerDNS Integration & DNS Editor
**Thời gian:** ~45 phút
**Kiểm tra:** DNS zones tạo/sửa qua panel, records CRUD

```
>>> PROMPT cho Claude Code:
Tạo module DNS với PowerDNS integration cho VSISPanel:

1. Models:
   - DnsZone: domain_id, zone_name, serial, refresh, retry, expire, minimum_ttl, status
   - DnsRecord: dns_zone_id, name, type (enum: A, AAAA, CNAME, MX, TXT, SRV, NS, CAA, PTR), content, ttl, priority (nullable), disabled (boolean)

2. Service: PowerDnsService (app/Modules/DNS/Services/PowerDnsService.php)
   Sử dụng PowerDNS HTTP API (port 8081):

   - createZone(Domain $domain, string $serverIp): DnsZone
     * POST /api/v1/servers/localhost/zones
     * Auto-create records: SOA, NS, A (server IP), www CNAME
     * If mail enabled: MX record
   - deleteZone(DnsZone $zone): void
     * DELETE /api/v1/servers/localhost/zones/{zone}
   - getZone(string $zoneName): array
     * GET /api/v1/servers/localhost/zones/{zone}
   - addRecord(DnsZone $zone, array $data): DnsRecord
     * PATCH /api/v1/servers/localhost/zones/{zone}
     * Validate record theo type (A phải là IP, MX phải có priority, etc.)
   - updateRecord(DnsRecord $record, array $data): DnsRecord
   - deleteRecord(DnsRecord $record): void
   - incrementSerial(DnsZone $zone): void
   - validateRecord(string $type, string $name, string $content): bool
   - getZoneExport(DnsZone $zone): string (BIND format export)

3. DNS Templates: resources/views/templates/dns/
   - default.json: standard zone template (SOA, NS, A, www, MX)
   - google-workspace.json: Google Workspace MX + verification records
   - office365.json: Microsoft 365 MX + autodiscover
   - email-only.json: MX + SPF + DKIM + DMARC

4. Controller: DnsZoneController
   - GET /api/v1/dns/zones - list zones
   - POST /api/v1/dns/zones - create zone
   - GET /api/v1/dns/zones/{zone} - zone detail với records
   - DELETE /api/v1/dns/zones/{zone} - delete zone
   - POST /api/v1/dns/zones/{zone}/apply-template - apply DNS template
   - GET /api/v1/dns/zones/{zone}/export - export BIND format

5. Controller: DnsRecordController
   - GET /api/v1/dns/zones/{zone}/records - list records
   - POST /api/v1/dns/zones/{zone}/records - add record
   - PUT /api/v1/dns/records/{record} - update record
   - DELETE /api/v1/dns/records/{record} - delete record
   - PUT /api/v1/dns/records/{record}/toggle - enable/disable record

6. Vue pages:
   - DnsPage.vue: zone list + chọn zone → hiện records
   - DnsZoneEditor.vue:
     * Toolbar: Add Record, Apply Template, Export, Refresh
     * Records DataTable: type badge (color-coded), name, content, TTL, priority, status toggle, edit/delete
     * Inline edit: click record → edit mode
     * Add Record modal: type select → dynamic form fields based on type
       - A: name + IPv4 input
       - AAAA: name + IPv6 input
       - CNAME: name + target
       - MX: name + mail server + priority
       - TXT: name + value (textarea for long values like DKIM)
       - SRV: name + target + priority + weight + port
       - CAA: name + flag + tag + value
     * Record type badges: A=blue, CNAME=green, MX=purple, TXT=orange, NS=gray
   - DnsTemplateModal.vue: chọn template, preview records, apply

7. Tests:
   - Test PowerDNS API calls (mock HTTP client)
   - Test record validation per type
   - Test zone creation with auto records
   - Test template application
   - Test serial increment
```

---

## Task 3.5: FTP Account Management
**Thời gian:** ~30 phút
**Kiểm tra:** Tạo FTP account, connect được

```
>>> PROMPT cho Claude Code:
Tạo module FTP cho VSISPanel:

1. Model: FtpAccount
   - user_id, domain_id, username (prefixed), password_hash
   - home_directory, status (active/suspended)
   - quota_mb, bandwidth_limit_kbps
   - ip_whitelist (JSON array, nullable)
   - last_login_at, last_login_ip

2. Service: FtpService (app/Modules/FTP/Services/FtpService.php)
   Hỗ trợ ProFTPD hoặc Pure-FTPd:

   - create(User $user, Domain $domain, array $data): FtpAccount
     * Username format: {username}_{ftpuser}
     * Hash password
     * Add to FTP virtual user database (/etc/proftpd/ftpd.passwd hoặc Pure-FTPd virtual users)
     * Set home directory (default: domain document root)
     * Restrict to home directory (no chroot escape)
     * Check ftp_accounts_limit quota
   - delete(FtpAccount $account): void
   - changePassword(FtpAccount $account, string $newPassword): void
   - suspend/unsuspend
   - setIpWhitelist(FtpAccount $account, array $ips): void
   - getActiveSessions(): array (ftpwho / pure-ftpwho)
   - killSession(string $sessionId): void
   - reload(): CommandResult

3. Config templates:
   - proftpd.conf.blade.php
   - Per-user config restrictions

4. Controller: FtpController
   - CRUD /api/v1/ftp-accounts
   - PUT /api/v1/ftp-accounts/{account}/password
   - GET /api/v1/ftp/sessions - active sessions
   - DELETE /api/v1/ftp/sessions/{session} - kill session

5. Vue page: FtpPage.vue
   - DataTable: username, domain, home dir, quota, status, last login, actions
   - Create modal: username, password (generate button), domain select, directory picker
   - Active sessions list
   - FTP connection info card (host, port, type FTPS)

6. Tests:
   - Test FTP account creation
   - Test chroot directory restriction
   - Test quota enforcement
   - Test IP whitelist
```

---

## Checklist Phase 3 Hoàn Thành

```
>>> PROMPT cho Claude Code:
Verify Phase 3 completion:

1. Tạo mail domain cho "test.local" → Postfix config updated
2. Tạo email account "info@test.local" → maildir created
3. SPF/DKIM/DMARC records auto-created trong DNS
4. Email forwarding hoạt động
5. Auto-responder configurable
6. Spam settings adjustable
7. DNS zone created với default records khi tạo domain
8. DNS editor: CRUD records, inline edit
9. DNS template apply hoạt động
10. FTP account creation → can connect (mock test)
11. FTP sessions monitoring
12. Email page UI hiển thị đúng

Chạy: php artisan test
Liệt kê PASS/FAIL và fix.
```

---

## Commit Convention Phase 3
```
feat(mail): Postfix/Dovecot integration services
feat(mail): email account CRUD with quota
feat(mail): SPF/DKIM/DMARC auto-configuration
feat(mail): forwarding, aliases, auto-responder
feat(spam): Rspamd spam filtering integration
feat(dns): PowerDNS integration with API
feat(dns): zone editor UI with templates
feat(ftp): FTP account management module
```
