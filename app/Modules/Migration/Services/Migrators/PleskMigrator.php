<?php

declare(strict_types=1);

namespace App\Modules\Migration\Services\Migrators;

use App\Modules\Migration\Models\MigrationJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PleskMigrator extends BaseMigrator
{
    public function testConnection(array $credentials): array
    {
        if (!empty($credentials['api_key'])) {
            return $this->testApiConnection($credentials);
        }

        $result = $this->sshExec($credentials, 'test -d /usr/local/psa && plesk version 2>/dev/null || echo "NOT_PLESK"', 15);

        if ($result['success'] && !str_contains($result['stdout'], 'NOT_PLESK')) {
            return [
                'success' => true,
                'message' => 'Plesk server detected via SSH',
                'version' => trim($result['stdout']),
            ];
        }

        return [
            'success' => false,
            'message' => 'Could not connect to Plesk server: ' . ($result['stderr'] ?: 'Not a Plesk server'),
        ];
    }

    public function discover(array $credentials): array
    {
        $data = [
            'domains' => [],
            'databases' => [],
            'emails' => [],
            'crons' => [],
            'server_type' => 'plesk',
        ];

        if (!empty($credentials['api_key'])) {
            return $this->discoverViaApi($credentials, $data);
        }

        return $this->discoverViaSsh($credentials, $data);
    }

    public function migrate(MigrationJob $job): void
    {
        $credentials = $job->source_credentials;
        $items = $job->items ?? [];
        $discovered = $job->discovered_data ?? [];
        $selectedDomains = $items['domains'] ?? [];
        $migrateFiles = $items['files'] ?? true;
        $migrateSsl = $items['ssl'] ?? true;

        foreach ($selectedDomains as $domainInfo) {
            $domainName = is_array($domainInfo) ? ($domainInfo['name'] ?? '') : $domainInfo;
            if (!$domainName) continue;

            $job->appendLog("=== Migrating domain: {$domainName} ===");

            // ─── STEP 1: Add domain on VsisPanel (like Add Website) ───
            $job->appendLog("[Step 1] Creating domain on VsisPanel...");
            $domain = $this->createDomain($domainName, $job->user_id, $job);
            if (!$domain) {
                $job->appendLog("CRITICAL: Could not create/find domain {$domainName}, skipping");
                continue;
            }

            // Load user relationship
            $domain->loadMissing('user');
            $username = $domain->user->username ?? 'administrator';
            $localPath = "/home/{$username}/domains/{$domainName}/public_html/";

            // Ensure local path exists
            if (!is_dir($localPath)) {
                mkdir($localPath, 0755, true);
            }

            $job->updateProgress(10);

            // ─── STEP 2: Get WP config from source (DB info) ───
            $wpConfig = is_array($domainInfo) ? ($domainInfo['wp_config'] ?? null) : null;
            $dbResult = null;

            if ($wpConfig && !empty($wpConfig['db_name'])) {
                $job->appendLog("[Step 2] WordPress detected. Source DB: {$wpConfig['db_name']}, User: {$wpConfig['db_user']}");

                // ─── STEP 3: Create database + user on VsisPanel ───
                $job->appendLog("[Step 3] Creating database and user...");

                // Use clean name based on domain
                $cleanName = str_replace(['.', '-'], '_', $domainName);
                $cleanName = substr($cleanName, 0, 40);
                $dbResult = $this->createLocalDatabaseWithName($domain, $cleanName, $job);

                if ($dbResult) {
                    $job->appendLog("  Database: {$dbResult['db_name']}");
                    $job->appendLog("  User: {$dbResult['db_user']}");
                    $job->appendLog("  Host: {$dbResult['db_host']}");
                } else {
                    $job->appendLog("WARNING: Failed to create database, will try direct method");
                }
            } else {
                $job->appendLog("[Step 2] No WordPress config found, skipping database");
            }

            $job->updateProgress(30);

            // ─── STEP 4: Migrate code (rsync files from source) ───
            if ($migrateFiles) {
                $remotePath = $this->resolvePleskPath($domainInfo, $credentials);
                $job->appendLog("[Step 4] Syncing files: {$remotePath} -> {$localPath}");

                $success = $this->rsyncFrom($credentials, $remotePath, $localPath, $job);
                if ($success) {
                    $job->appendLog("  Files synced successfully");

                    // Fix ownership and permissions
                    $chown = new \Symfony\Component\Process\Process(['chown', '-R', "{$username}:{$username}", $localPath]);
                    $chown->setTimeout(120);
                    $chown->run();
                    $chmod = new \Symfony\Component\Process\Process(['chmod', '-R', '755', $localPath]);
                    $chmod->setTimeout(120);
                    $chmod->run();
                    $job->appendLog("  Ownership set to {$username}, permissions 755");
                } else {
                    $job->appendLog("  WARNING: File sync failed");
                }
            }

            $job->updateProgress(60);

            // ─── STEP 5: Import SQL database ───
            if ($dbResult && $wpConfig && !empty($wpConfig['db_name'])) {
                $job->appendLog("[Step 5] Dumping database from source...");

                $dumpFile = "/tmp/migration_{$job->id}_{$wpConfig['db_name']}.sql";

                // Dump from Plesk source using admin credentials
                $dumpCmd = 'MYSQL_PWD=$(cat /etc/psa/.psa.shadow) mysqldump -u admin --single-transaction --routines --triggers '
                    . escapeshellarg($wpConfig['db_name']) . ' 2>/dev/null';
                $result = $this->sshExec($credentials, $dumpCmd, 600);

                if (!$result['success'] || strlen($result['stdout']) < 100) {
                    // Fallback: try with WP credentials
                    $dumpCmd = 'MYSQL_PWD=' . escapeshellarg($wpConfig['db_pass'] ?? '') 
                        . ' mysqldump -u ' . escapeshellarg($wpConfig['db_user'] ?? 'root')
                        . ' --single-transaction --routines --triggers '
                        . escapeshellarg($wpConfig['db_name']) . ' 2>/dev/null';
                    $result = $this->sshExec($credentials, $dumpCmd, 600);
                }

                if ($result['success'] && strlen($result['stdout']) > 100) {
                    $sqlSize = strlen($result['stdout']);
                    $job->appendLog("  SQL dump size: " . number_format($sqlSize / 1024, 1) . " KB");
                    file_put_contents($dumpFile, $result['stdout']);

                    $imported = $this->importDatabase($dbResult['db_name'], $dumpFile, $job);
                    if ($imported) {
                        $job->appendLog("  Database imported successfully");
                    } else {
                        $job->appendLog("  WARNING: Database import failed");
                    }
                    @unlink($dumpFile);
                } else {
                    $job->appendLog("  WARNING: Could not dump source database");
                }

                // ─── STEP 6: Update wp-config.php with new credentials ───
                $job->appendLog("[Step 6] Updating wp-config.php...");
                $this->updateWpConfig($localPath, $dbResult, $job);
            }

            $job->updateProgress(80);

            // ─── STEP 7: SSL certificate ───
            if ($migrateSsl) {
                $job->appendLog("[Step 7] Requesting SSL certificate...");
                try {
                    $sslService = app(\App\Modules\SSL\Services\SslService::class);
                    $sslService->issueLetsEncrypt($domain);
                    $job->appendLog("  SSL issued for {$domainName}");
                } catch (\Exception $e) {
                    $job->appendLog("  SSL failed: {$e->getMessage()}");
                }
            }

            $job->appendLog("=== Domain {$domainName} migration complete ===");
        }

        $job->updateProgress(100, 'Migration completed');
    }

    // =========================================================================
    // Plesk-specific helpers
    // =========================================================================

    /**
     * Resolve the actual file path for a Plesk domain/subdomain.
     */
    private function resolvePleskPath(mixed $domainInfo, array $credentials): ?string
    {
        if (is_array($domainInfo) && !empty($domainInfo['path'])) {
            return rtrim($domainInfo['path'], '/') . '/';
        }

        $domainName = is_array($domainInfo) ? ($domainInfo['name'] ?? '') : $domainInfo;

        $tryPaths = ["/var/www/vhosts/{$domainName}/httpdocs/"];

        // Subdomains may be under parent domain
        $parts = explode('.', $domainName);
        if (count($parts) > 2) {
            $parentDomain = implode('.', array_slice($parts, 1));
            $tryPaths[] = "/var/www/vhosts/{$parentDomain}/{$domainName}/";
        }

        foreach ($tryPaths as $path) {
            $result = $this->sshExec($credentials, "test -d " . escapeshellarg($path) . " && echo EXISTS", 5);
            if ($result['success'] && str_contains($result['stdout'], 'EXISTS')) {
                return $path;
            }
        }

        return "/var/www/vhosts/{$domainName}/httpdocs/";
    }

    /**
     * Create a local database + user for the migrated site.
     */
    /**
     * Create database with a meaningful name based on domain.
     */
    private function createLocalDatabaseWithName(object $domain, string $cleanName, ?MigrationJob $job = null): ?array
    {
        try {
            $dbService = app(\App\Modules\Database\Services\DatabaseService::class);
            $domain->loadMissing('user');
            $user = $domain->user;

            if (!$user) {
                $job?->appendLog("WARNING: Domain has no user, using direct DB creation");
                return $this->createDatabaseDirect($cleanName, $job);
            }

            // MySQL username max 32 chars. Prefix "administrator_" = 14 chars, so name max 18
            $prefix = $user->username ?? 'administrator';
            $maxLen = 32 - strlen($prefix) - 1; // -1 for underscore
            $dbName = substr($cleanName, 0, $maxLen);
            $dbUserName = substr($cleanName, 0, $maxLen);
            $dbPass = bin2hex(random_bytes(12));

            // Clean up soft-deleted records
            \App\Modules\Database\Models\ManagedDatabase::withTrashed()
                ->where('user_id', $user->id)
                ->where('original_name', $cleanName)
                ->forceDelete();
            \App\Modules\Database\Models\DatabaseUser::withTrashed()
                ->where('user_id', $user->id)
                ->where('original_username', $cleanName)
                ->forceDelete();

            // Also clean up active records with same name (re-migration)
            \App\Modules\Database\Models\ManagedDatabase::where('user_id', $user->id)
                ->where('original_name', $cleanName)
                ->forceDelete();
            \App\Modules\Database\Models\DatabaseUser::where('user_id', $user->id)
                ->where('original_username', $cleanName)
                ->forceDelete();

            // Drop existing MySQL database and user (from previous failed migrations)
            $fullDbName = "{$prefix}_{$dbName}";
            $fullUserName = "{$prefix}_{$dbUserName}";
            $dropProcess = new \Symfony\Component\Process\Process(['mysql', '-e',
                "DROP DATABASE IF EXISTS `{$fullDbName}`; DROP USER IF EXISTS '{$fullUserName}'@'localhost'; FLUSH PRIVILEGES;"
            ]);
            $dropProcess->setTimeout(15);
            $dropProcess->run();

            $database = $dbService->createDatabase($user, $dbName, $domain);
            $dbUser = $dbService->createDatabaseUser($user, $dbUserName, $dbPass);
            $dbService->grantAccess($dbUser, $database);

            return [
                'db_name' => $database->name,
                'db_user' => $dbUser->username,
                'db_pass' => $dbPass,
                'db_host' => 'localhost',
            ];
        } catch (\Exception $e) {
            $job?->appendLog("DatabaseService error: {$e->getMessage()}");
            // Fallback to direct MySQL creation
            return $this->createDatabaseDirect($cleanName, $job);
        }
    }

        private function createLocalDatabase(object $domain, string $originalDbName, ?MigrationJob $job = null): ?array
    {
        try {
            $dbService = app(\App\Modules\Database\Services\DatabaseService::class);

            $shortHash = substr(md5($originalDbName), 0, 8);
            $dbName = "mig{$shortHash}";
            $dbUserName = "mig{$shortHash}";
            $dbPass = bin2hex(random_bytes(12));

            $database = $dbService->createDatabase($domain->user, $dbName, $domain);
            $dbUser = $dbService->createDatabaseUser($domain->user, $dbUserName, $dbPass);
            $dbService->grantAccess($dbUser, $database);

            $job?->appendLog("Created database: {$database->name}, user: {$dbUser->username}");

            return [
                'db_name' => $database->name,
                'db_user' => $dbUser->username,
                'db_pass' => $dbPass,
                'db_host' => 'localhost',
            ];
        } catch (\Exception $e) {
            $job?->appendLog("Failed to create database via DatabaseService: {$e->getMessage()}");
            return $this->createDatabaseDirect($originalDbName, $job);
        }
    }

    /**
     * Fallback: create database directly via MySQL CLI.
     */
    private function createDatabaseDirect(string $originalDbName, ?MigrationJob $job = null): ?array
    {
        $dbName = 'mig_' . substr(md5($originalDbName . time()), 0, 12);
        $dbUser = $dbName;
        $dbPass = bin2hex(random_bytes(12));

        $sql = "CREATE DATABASE IF NOT EXISTS `{$dbName}`; "
             . "CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPass}'; "
             . "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'localhost'; "
             . "FLUSH PRIVILEGES;";

        $process = new \Symfony\Component\Process\Process(['mysql', '-e', $sql]);
        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful()) {
            $job?->appendLog("Created database (direct): {$dbName}");
            return ['db_name' => $dbName, 'db_user' => $dbUser, 'db_pass' => $dbPass, 'db_host' => 'localhost'];
        }

        $job?->appendLog("Failed to create database directly: {$process->getErrorOutput()}");
        return null;
    }

    /**
     * Update wp-config.php with new database credentials.
     */
    private function updateWpConfig(string $localPath, array $dbCredentials, ?MigrationJob $job = null): void
    {
        $wpConfigPath = rtrim($localPath, '/') . '/wp-config.php';

        if (!file_exists($wpConfigPath)) {
            $job?->appendLog("wp-config.php not found at {$wpConfigPath}");
            return;
        }

        $content = file_get_contents($wpConfigPath);
        $original = $content;

        $replacements = [
            'DB_NAME' => $dbCredentials['db_name'],
            'DB_USER' => $dbCredentials['db_user'],
            'DB_PASSWORD' => $dbCredentials['db_pass'],
            'DB_HOST' => $dbCredentials['db_host'],
        ];

        foreach ($replacements as $key => $value) {
            // Match both single and double quoted define() calls
            $escaped = preg_quote($key, '/');
            $content = preg_replace(
                '/define\s*\(\s*[\'"]' . $escaped . '[\'"]\s*,\s*[\'"][^\'"]*[\'"]\s*\)/',
                "define('" . $key . "', '" . addslashes($value) . "')",
                $content
            );
        }

        if ($content !== $original) {
            file_put_contents($wpConfigPath, $content);
            $job?->appendLog("wp-config.php updated with new DB credentials");
        } else {
            $job?->appendLog("WARNING: wp-config.php was not modified (pattern mismatch?)");
        }
    }

    private function calcProgress(int $current, int $total): int
    {
        if ($total <= 0) return 90;
        return min(95, (int) ($current / $total * 90));
    }

    // =========================================================================
    // API methods
    // =========================================================================

    private function testApiConnection(array $credentials): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->withoutVerifying()->get(
                "https://{$credentials['host']}:8443/api/v2/server"
            );

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Plesk API connection successful',
                    'version' => $response->json('version') ?? 'unknown',
                ];
            }

            return ['success' => false, 'message' => 'Plesk API error: ' . $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Plesk API connection failed: ' . $e->getMessage()];
        }
    }

    private function discoverViaApi(array $credentials, array $data): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->withoutVerifying()->get(
                "https://{$credentials['host']}:8443/api/v2/domains"
            );

            if ($response->successful()) {
                foreach ($response->json() as $domain) {
                    $data['domains'][] = [
                        'name' => $domain['name'] ?? $domain['ascii_name'] ?? '',
                        'path' => "/var/www/vhosts/" . ($domain['name'] ?? '') . "/httpdocs/",
                        'type' => 'plesk',
                    ];
                }
            }
        } catch (\Exception) {
            return $this->discoverViaSsh($credentials, $data);
        }

        return $data;
    }

    private function discoverViaSsh(array $credentials, array $data): array
    {
        // Use plesk CLI to list all domains
        $result = $this->sshExec($credentials, "plesk bin site --list 2>/dev/null", 15);
        $pleskDomains = [];
        if ($result['success']) {
            $pleskDomains = array_filter(array_map('trim', explode("\n", trim($result['stdout']))));
        }

        // Fallback: scan vhosts directory
        if (empty($pleskDomains)) {
            $result = $this->sshExec($credentials, 'ls -1 /var/www/vhosts/ 2>/dev/null | grep -v -E "^(\\..*|default|chroot|system)$"', 15);
            if ($result['success']) {
                foreach (array_filter(explode("\n", trim($result['stdout']))) as $d) {
                    $d = trim($d);
                    if ($d && str_contains($d, '.')) {
                        $pleskDomains[] = $d;
                    }
                }
            }
        }

        // Find wp-config.php files to detect WordPress + get DB info
        $wpConfigMap = [];
        $result = $this->sshExec($credentials, "find /var/www/vhosts -maxdepth 4 -name wp-config.php 2>/dev/null", 15);
        if ($result['success']) {
            foreach (array_filter(explode("\n", trim($result['stdout']))) as $wpPath) {
                $wpPath = trim($wpPath);
                if (!$wpPath) continue;

                $grepCmd = "grep -E 'DB_(NAME|USER|PASSWORD|HOST)' " . escapeshellarg($wpPath) . " 2>/dev/null";
                $wpResult = $this->sshExec($credentials, $grepCmd, 10);
                if ($wpResult['success']) {
                    $dbInfo = $this->parseWpConfig($wpResult['stdout']);
                    $docRoot = dirname($wpPath) . '/';
                    $wpConfigMap[$docRoot] = $dbInfo;
                }
            }
        }

        // Build domain list with resolved paths and WP info
        foreach ($pleskDomains as $domainName) {
            $path = $this->resolvePleskPathRemote($domainName, $credentials);
            $domainEntry = [
                'name' => $domainName,
                'path' => $path,
                'type' => 'plesk',
            ];

            // Check WordPress
            if (isset($wpConfigMap[$path])) {
                $domainEntry['has_wordpress'] = true;
                $domainEntry['wp_config'] = $wpConfigMap[$path];
            }

            // Get disk size
            $sizeResult = $this->sshExec($credentials, "du -sh " . escapeshellarg(rtrim($path, '/')) . " 2>/dev/null | awk '{print \\$1}'", 10);
            if ($sizeResult['success'] && trim($sizeResult['stdout'])) {
                $domainEntry['size'] = trim($sizeResult['stdout']);
            }

            $data['domains'][] = $domainEntry;
        }

        // List databases
        $result = $this->sshExec($credentials,
            'MYSQL_PWD=$(cat /etc/psa/.psa.shadow) mysql -u admin -N -e "SHOW DATABASES" 2>/dev/null | grep -v -E "^(information_schema|performance_schema|mysql|sys|psa|phpmyadmin|roundcubemail|apsc)$"',
            15
        );
        if ($result['success']) {
            foreach (array_filter(explode("\n", trim($result['stdout']))) as $db) {
                $db = trim($db);
                if ($db) {
                    $data['databases'][] = ['name' => $db];
                }
            }
        }

        // Cron jobs
        $result = $this->sshExec($credentials, 'crontab -l 2>/dev/null | grep -v "^#" | grep -v "^$"', 15);
        if ($result['success']) {
            foreach (array_filter(explode("\n", trim($result['stdout']))) as $cron) {
                $cron = trim($cron);
                if ($cron) {
                    $data['crons'][] = $cron;
                }
            }
        }

        return $data;
    }

    /**
     * Resolve domain path on remote Plesk server during discovery.
     */
    private function resolvePleskPathRemote(string $domainName, array $credentials): string
    {
        $stdPath = "/var/www/vhosts/{$domainName}/httpdocs/";
        $result = $this->sshExec($credentials, "test -d " . escapeshellarg($stdPath) . " && echo EXISTS", 5);
        if ($result['success'] && str_contains($result['stdout'], 'EXISTS')) {
            return $stdPath;
        }

        // Subdomain under parent (e.g. blog.vsis.net -> /var/www/vhosts/vsis.net/blog.vsis.net/)
        $parts = explode('.', $domainName);
        if (count($parts) > 2) {
            $parentDomain = implode('.', array_slice($parts, 1));
            $subPath = "/var/www/vhosts/{$parentDomain}/{$domainName}/";
            $result = $this->sshExec($credentials, "test -d " . escapeshellarg($subPath) . " && echo EXISTS", 5);
            if ($result['success'] && str_contains($result['stdout'], 'EXISTS')) {
                return $subPath;
            }
        }

        return $stdPath;
    }

    /**
     * Parse wp-config.php grep output to extract DB credentials.
     */
    private function parseWpConfig(string $output): array
    {
        $config = [];
        $mapping = [
            'DB_NAME' => 'db_name',
            'DB_USER' => 'db_user',
            'DB_PASSWORD' => 'db_pass',
            'DB_HOST' => 'db_host',
        ];

        foreach ($mapping as $wpKey => $key) {
            if (preg_match('/[\'"]' . preg_quote($wpKey, '/') . '[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]/', $output, $m)) {
                $config[$key] = $m[1];
            }
        }

        return $config;
    }
}
