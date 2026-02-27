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
        $selectedDatabases = $items['databases'] ?? [];
        $migrateFiles = $items['files'] ?? true;
        $migrateSsl = $items['ssl'] ?? true;
        $migrateCrons = $items['crons'] ?? false;

        $totalSteps = count($selectedDomains) * 3 + count($selectedDatabases);
        $currentStep = 0;

        foreach ($selectedDomains as $domainInfo) {
            $domainName = is_array($domainInfo) ? ($domainInfo['name'] ?? '') : $domainInfo;
            if (!$domainName) continue;

            $job->appendLog("=== Migrating domain: {$domainName} ===");

            // 1. Create domain in VsisPanel
            $domain = $this->createDomain($domainName, $job->user_id, $job);
            if (!$domain) {
                $job->appendLog("SKIP: Could not create domain {$domainName}");
                $currentStep += 3;
                $job->updateProgress($this->calcProgress($currentStep, $totalSteps));
                continue;
            }
            $currentStep++;
            $job->updateProgress($this->calcProgress($currentStep, $totalSteps));

            // 2. Rsync files
            if ($migrateFiles) {
                $remotePath = $this->resolvePleskPath($domainInfo, $credentials);
                $localPath = "/home/{$domain->user->username}/domains/{$domainName}/public_html/";

                if ($remotePath) {
                    $job->appendLog("Syncing files: {$remotePath} -> {$localPath}");
                    $success = $this->rsyncFrom($credentials, $remotePath, $localPath, $job);
                    $job->appendLog($success ? 'Files synced OK' : 'File sync FAILED (continuing)');

                    // Fix ownership
                    $username = $domain->user->username ?? 'www-data';
                    $process = new \Symfony\Component\Process\Process(['chown', '-R', "{$username}:{$username}", $localPath]);
                    $process->setTimeout(120);
                    $process->run();
                }
            }
            $currentStep++;
            $job->updateProgress($this->calcProgress($currentStep, $totalSteps));

            // 3. WordPress database migration
            $wpConfig = is_array($domainInfo) ? ($domainInfo['wp_config'] ?? null) : null;
            if ($wpConfig && !empty($wpConfig['db_name'])) {
                $job->appendLog("WordPress detected - migrating database: {$wpConfig['db_name']}");

                $dumpFile = "/tmp/migration_{$job->id}_{$wpConfig['db_name']}.sql";

                // Try Plesk admin credentials first
                $dumpCmd = 'MYSQL_PWD=$(cat /etc/psa/.psa.shadow) mysqldump -u admin --single-transaction --routines --triggers '
                    . escapeshellarg($wpConfig['db_name']) . ' 2>/dev/null';
                $result = $this->sshExec($credentials, $dumpCmd, 600);

                // Fallback to root mysql
                if (!$result['success'] || strlen($result['stdout']) < 100) {
                    $dumpCmd = 'mysqldump --single-transaction --routines --triggers '
                        . escapeshellarg($wpConfig['db_name']) . ' 2>/dev/null';
                    $result = $this->sshExec($credentials, $dumpCmd, 600);
                }

                if ($result['success'] && strlen($result['stdout']) > 100) {
                    file_put_contents($dumpFile, $result['stdout']);

                    $dbResult = $this->createLocalDatabase($domain, $wpConfig['db_name'], $job);
                    if ($dbResult) {
                        $this->importDatabase($dbResult['db_name'], $dumpFile, $job);

                        $localWpPath = "/home/{$domain->user->username}/domains/{$domainName}/public_html/";
                        $this->updateWpConfig($localWpPath, $dbResult, $job);
                    }
                    @unlink($dumpFile);
                } else {
                    $job->appendLog("DB dump failed for {$wpConfig['db_name']}");
                }
            }
            $currentStep++;
            $job->updateProgress($this->calcProgress($currentStep, $totalSteps));

            // 4. SSL certificate
            if ($migrateSsl) {
                try {
                    $sslService = app(\App\Modules\SSL\Services\SslService::class);
                    $sslService->issueLetsEncrypt($domain);
                    $job->appendLog("SSL issued for {$domainName}");
                } catch (\Exception $e) {
                    $job->appendLog("SSL failed for {$domainName}: {$e->getMessage()}");
                }
            }
        }

        // Migrate standalone databases
        foreach ($selectedDatabases as $dbInfo) {
            $dbName = is_array($dbInfo) ? ($dbInfo['name'] ?? '') : $dbInfo;
            if (!$dbName) continue;

            $job->appendLog("=== Migrating standalone database: {$dbName} ===");

            $dumpFile = "/tmp/migration_{$job->id}_{$dbName}.sql";
            $dumpCmd = 'MYSQL_PWD=$(cat /etc/psa/.psa.shadow) mysqldump -u admin --single-transaction --routines --triggers '
                . escapeshellarg($dbName) . ' 2>/dev/null';
            $result = $this->sshExec($credentials, $dumpCmd, 600);

            if ($result['success'] && strlen($result['stdout']) > 100) {
                file_put_contents($dumpFile, $result['stdout']);
                $this->importDatabase($dbName, $dumpFile, $job);
                @unlink($dumpFile);
            } else {
                $job->appendLog("Failed to dump database {$dbName}");
            }

            $currentStep++;
            $job->updateProgress($this->calcProgress($currentStep, $totalSteps));
        }

        // Cron jobs
        if ($migrateCrons && !empty($discovered['crons'])) {
            $job->appendLog("=== Migrating cron jobs ===");
            foreach ($discovered['crons'] as $cron) {
                $job->appendLog("Cron: {$cron}");
                $process = new \Symfony\Component\Process\Process([
                    'bash', '-c',
                    "(crontab -l 2>/dev/null; echo " . escapeshellarg($cron) . ") | sort -u | crontab -"
                ]);
                $process->run();
            }
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
