<?php

declare(strict_types=1);

namespace App\Modules\Migration\Services\Migrators;

use App\Modules\Migration\Models\MigrationJob;

class SshMigrator extends BaseMigrator
{
    public function testConnection(array $credentials): array
    {
        $result = $this->sshExec($credentials, 'echo "VSISPanel migration test OK" && hostname && uname -a', 15);

        if ($result['success'] && str_contains($result['stdout'], 'VSISPanel migration test OK')) {
            return [
                'success' => true,
                'message' => 'SSH connection successful',
                'hostname' => trim(explode("\n", $result['stdout'])[1] ?? ''),
            ];
        }

        return [
            'success' => false,
            'message' => 'SSH connection failed: ' . ($result['stderr'] ?: 'Unknown error'),
        ];
    }

    public function discover(array $credentials): array
    {
        $data = [
            'domains' => [],
            'databases' => [],
            'emails' => [],
            'crons' => [],
            'server_type' => 'unknown',
        ];

        // Detect server type
        $data['server_type'] = $this->detectServerType($credentials);

        // Discover domains
        $data['domains'] = $this->discoverDomains($credentials, $data['server_type']);

        // Discover databases
        $data['databases'] = $this->discoverDatabases($credentials);

        // Discover cron jobs
        $data['crons'] = $this->discoverCrons($credentials);

        return $data;
    }

    public function migrate(MigrationJob $job): void
    {
        $credentials = $job->source_credentials;
        $items = $job->items ?? [];
        $discovered = $job->discovered_data ?? [];
        $selectedDomains = $items['domains'] ?? [];
        $selectedDatabases = $items['databases'] ?? [];
        $migrateFiles = $items['files'] ?? true;
        $migrateEmails = $items['emails'] ?? false;
        $migrateCrons = $items['crons'] ?? false;
        $migrateSsl = $items['ssl'] ?? true;

        $totalSteps = count($selectedDomains) * ($migrateFiles ? 2 : 1) + count($selectedDatabases) + ($migrateCrons ? 1 : 0);
        $currentStep = 0;

        // Server type for path detection
        $serverType = $discovered['server_type'] ?? 'unknown';

        // Migrate each selected domain
        foreach ($selectedDomains as $domainInfo) {
            $domainName = is_array($domainInfo) ? ($domainInfo['name'] ?? $domainInfo['domain'] ?? '') : $domainInfo;
            if (!$domainName) continue;

            $job->appendLog("--- Migrating domain: {$domainName} ---");

            // Create domain in VSISPanel
            $domain = $this->createDomain($domainName, $job->user_id, $job);
            if (!$domain) {
                $job->appendLog("Skipping domain {$domainName} - could not create");
                continue;
            }

            // Rsync files
            if ($migrateFiles) {
                $remotePath = $this->getDomainPath($domainInfo, $serverType);
                $localPath = "/home/{$domain->user->username}/domains/{$domainName}/public_html/";

                if ($remotePath) {
                    $job->appendLog("Syncing files from {$remotePath}");
                    $success = $this->rsyncFrom($credentials, $remotePath, $localPath, $job);
                    $job->appendLog($success ? "Files synced successfully" : "File sync failed (continuing)");

                    // Fix permissions
                    $username = $domain->user->username ?? 'www-data';
                    $process = new \Symfony\Component\Process\Process(['chown', '-R', "{$username}:{$username}", $localPath]);
                    $process->run();
                }
                $currentStep++;
                $job->updateProgress((int) ($currentStep / max($totalSteps, 1) * 90));
            }

            // Issue SSL if requested
            if ($migrateSsl) {
                try {
                    $sslService = app(\App\Modules\SSL\Services\SslService::class);
                    $sslService->issueLetsEncrypt($domain);
                    $job->appendLog("SSL certificate issued for {$domainName}");
                } catch (\Exception $e) {
                    $job->appendLog("SSL issue failed for {$domainName}: {$e->getMessage()}");
                }
            }

            $currentStep++;
            $job->updateProgress((int) ($currentStep / max($totalSteps, 1) * 90));
        }

        // Migrate databases
        foreach ($selectedDatabases as $dbInfo) {
            $dbName = is_array($dbInfo) ? ($dbInfo['name'] ?? '') : $dbInfo;
            if (!$dbName) continue;

            $job->appendLog("--- Migrating database: {$dbName} ---");

            // Dump from remote
            $dumpFile = "/tmp/migration_{$job->id}_{$dbName}.sql";
            $dumpCmd = "mysqldump --single-transaction --routines --triggers '{$dbName}' 2>/dev/null";
            $result = $this->sshExec($credentials, $dumpCmd, 600);

            if ($result['success'] && !empty($result['stdout'])) {
                file_put_contents($dumpFile, $result['stdout']);
                $this->importDatabase($dbName, $dumpFile, $job);
                @unlink($dumpFile);
            } else {
                $job->appendLog("Failed to dump database {$dbName}: {$result['stderr']}");
            }

            $currentStep++;
            $job->updateProgress((int) ($currentStep / max($totalSteps, 1) * 90));
        }

        // Migrate cron jobs
        if ($migrateCrons && !empty($discovered['crons'])) {
            $job->appendLog("--- Migrating cron jobs ---");
            foreach ($discovered['crons'] as $cron) {
                $job->appendLog("Cron: {$cron}");
                // Add to root crontab
                $process = new \Symfony\Component\Process\Process([
                    'bash', '-c',
                    "(crontab -l 2>/dev/null; echo " . escapeshellarg($cron) . ") | sort -u | crontab -"
                ]);
                $process->run();
            }
            $currentStep++;
            $job->updateProgress((int) ($currentStep / max($totalSteps, 1) * 90));
        }

        $job->updateProgress(100);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function detectServerType(array $credentials): string
    {
        // Check for Plesk
        $result = $this->sshExec($credentials, 'test -d /usr/local/psa && echo "plesk"', 10);
        if ($result['success'] && str_contains($result['stdout'], 'plesk')) return 'plesk';

        // Check for cPanel
        $result = $this->sshExec($credentials, 'test -d /usr/local/cpanel && echo "cpanel"', 10);
        if ($result['success'] && str_contains($result['stdout'], 'cpanel')) return 'cpanel';

        // Check for aaPanel
        $result = $this->sshExec($credentials, 'test -d /www/server/panel && echo "aapanel"', 10);
        if ($result['success'] && str_contains($result['stdout'], 'aapanel')) return 'aapanel';

        // Check for DirectAdmin
        $result = $this->sshExec($credentials, 'test -d /usr/local/directadmin && echo "directadmin"', 10);
        if ($result['success'] && str_contains($result['stdout'], 'directadmin')) return 'directadmin';

        return 'generic';
    }

    private function discoverDomains(array $credentials, string $serverType): array
    {
        $domains = [];

        switch ($serverType) {
            case 'plesk':
                $result = $this->sshExec($credentials, "ls -1 /var/www/vhosts/ 2>/dev/null | grep -v '^\\.' | grep -v 'default' | grep -v 'chroot' | grep -v 'system'", 15);
                if ($result['success']) {
                    foreach (array_filter(explode("\n", trim($result['stdout']))) as $domain) {
                        $domain = trim($domain);
                        if ($domain && str_contains($domain, '.')) {
                            $domains[] = [
                                'name' => $domain,
                                'path' => "/var/www/vhosts/{$domain}/httpdocs/",
                                'type' => 'plesk',
                            ];
                        }
                    }
                }
                break;

            case 'cpanel':
                $result = $this->sshExec($credentials, "cat /etc/trueuserdomains 2>/dev/null || ls -1 /home/*/public_html/../.cpanel-datastore 2>/dev/null | sed 's|/home/\\(.*\\)/\\.cpanel.*|\\1|' | sort -u", 15);
                if ($result['success']) {
                    foreach (array_filter(explode("\n", trim($result['stdout']))) as $line) {
                        $parts = preg_split('/[:\\s]+/', trim($line));
                        $domain = $parts[0] ?? '';
                        if ($domain && str_contains($domain, '.')) {
                            $domains[] = [
                                'name' => $domain,
                                'path' => "/home/" . ($parts[1] ?? $domain) . "/public_html/",
                                'type' => 'cpanel',
                            ];
                        }
                    }
                }
                break;

            case 'aapanel':
                $result = $this->sshExec($credentials, "ls -1 /www/wwwroot/ 2>/dev/null | grep -v '^\\.' | grep '\\.'", 15);
                if ($result['success']) {
                    foreach (array_filter(explode("\n", trim($result['stdout']))) as $domain) {
                        $domain = trim($domain);
                        if ($domain && str_contains($domain, '.')) {
                            $domains[] = [
                                'name' => $domain,
                                'path' => "/www/wwwroot/{$domain}/",
                                'type' => 'aapanel',
                            ];
                        }
                    }
                }
                break;

            default:
                // Try common paths
                $result = $this->sshExec($credentials, "ls -1 /var/www/vhosts/ 2>/dev/null; ls -1 /var/www/ 2>/dev/null; ls -1 /home/*/public_html/.. 2>/dev/null", 15);
                if ($result['success']) {
                    foreach (array_filter(explode("\n", trim($result['stdout']))) as $item) {
                        $item = trim($item);
                        if ($item && str_contains($item, '.') && !in_array($item, ['html', 'default', '.', '..'])) {
                            $domains[] = [
                                'name' => $item,
                                'path' => "/var/www/{$item}/",
                                'type' => 'generic',
                            ];
                        }
                    }
                }
                break;
        }

        return $domains;
    }

    private function discoverDatabases(array $credentials): array
    {
        $databases = [];
        $result = $this->sshExec($credentials, "mysql -N -e \"SHOW DATABASES\" 2>/dev/null | grep -v -E '^(information_schema|performance_schema|mysql|sys|phpmyadmin|psa)\$'", 15);

        if ($result['success']) {
            foreach (array_filter(explode("\n", trim($result['stdout']))) as $db) {
                $db = trim($db);
                if ($db) {
                    $databases[] = ['name' => $db];
                }
            }
        }

        return $databases;
    }

    private function discoverCrons(array $credentials): array
    {
        $crons = [];
        $result = $this->sshExec($credentials, "crontab -l 2>/dev/null | grep -v '^#' | grep -v '^\$'", 15);

        if ($result['success']) {
            foreach (array_filter(explode("\n", trim($result['stdout']))) as $cron) {
                $cron = trim($cron);
                if ($cron && !str_starts_with($cron, '#')) {
                    $crons[] = $cron;
                }
            }
        }

        return $crons;
    }

    private function getDomainPath(mixed $domainInfo, string $serverType): ?string
    {
        if (is_array($domainInfo) && !empty($domainInfo['path'])) {
            return rtrim($domainInfo['path'], '/') . '/';
        }

        $domainName = is_array($domainInfo) ? ($domainInfo['name'] ?? '') : $domainInfo;

        return match ($serverType) {
            'plesk' => "/var/www/vhosts/{$domainName}/httpdocs/",
            'cpanel' => "/home/{$domainName}/public_html/",
            'aapanel' => "/www/wwwroot/{$domainName}/",
            default => "/var/www/{$domainName}/",
        };
    }
}
