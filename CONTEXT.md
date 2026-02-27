# VsisPanel Migration Module - Context

## Architecture
- **Framework**: Laravel 11 + Vue 3 + MySQL 8 + Redis + Nginx
- **Module path**: `app/Modules/Migration/`
- **Frontend**: `resources/js/pages/MigrationPage.vue`

## Migration Flow
1. User selects source type (Plesk/cPanel/aaPanel/DirectAdmin/SSH)
2. Enters connection credentials (host, port, username, password/key/API key)
3. Test connection → Discover resources (domains, databases, crons)
4. Select items to migrate
5. Review & start migration job (queued on `installs` queue)
6. Job runs in background with progress tracking

## Key Files
- `PleskMigrator.php` - Full Plesk-specific migration logic
- `SshMigrator.php` - Generic SSH migration (also used as base for others)
- `BaseMigrator.php` - SSH/rsync/importDB/createDomain helpers
- `MigrationService.php` - Service layer, routes to correct migrator
- `MigrationController.php` - API endpoints
- `RunMigrationJob.php` - Queue job wrapper
- `MigrationJob.php` - Eloquent model with encrypted credentials

## PleskMigrator Features (as of 2026-02-27)
- **Discovery**: Uses `plesk bin site --list` + filesystem scan for domains
- **WordPress detection**: Finds wp-config.php, extracts DB credentials
- **Subdomain path resolution**: Handles Plesk's non-standard subdomain paths
  - Standard: `/var/www/vhosts/domain.com/httpdocs/`
  - Subdomain: `/var/www/vhosts/parent.com/sub.parent.com/`
- **Database dump**: Uses Plesk admin auth (`MYSQL_PWD=$(cat /etc/psa/.psa.shadow) mysql -u admin`)
- **Database creation**: Via VsisPanel's DatabaseService (createDatabase + createDatabaseUser + grantAccess)
- **wp-config.php update**: Auto-updates DB_NAME, DB_USER, DB_PASSWORD, DB_HOST after migration
- **SSL**: Auto-issues Let's Encrypt after domain creation

## Plesk Source Server (34.124.220.228)
- Domains: vsis.net, blog.vsis.net, doc.vsis.net, proxyvietnamgiare.vsis.net, + others
- WordPress sites found at:
  - vsis.net → /var/www/vhosts/vsis.net/httpdocs/ (596M)
  - blog.vsis.net → /var/www/vhosts/vsis.net/blog.vsis.net/ (1.1G)
  - doc.vsis.net → /var/www/vhosts/doc.vsis.net/httpdocs/ (1.4G)
  - proxyvietnamgiare.vsis.net → /var/www/vhosts/proxyvietnamgiare.vsis.net/httpdocs/
  - vsislogin.vsis.net → /var/www/vhosts/vsislogin.vsis.net/httpdocs/

## Servers
- **Dev**: root@161.248.184.106 (/opt/vsispanel)
- **Product test**: root@161.248.184.16 (/opt/vsispanel)
- **Plesk source**: root@34.124.220.228

## UI Rules
- NO unicode emoji — use SVG icons (heroicons) or text labels only
