<?php

declare(strict_types=1);

namespace App\Modules\Migration\Services\Migrators;

use App\Modules\Migration\Models\MigrationJob;
use Illuminate\Support\Facades\Http;

class PleskMigrator extends BaseMigrator
{
    public function testConnection(array $credentials): array
    {
        // Try Plesk REST API first
        if (!empty($credentials['api_key'])) {
            return $this->testApiConnection($credentials);
        }

        // Fall back to SSH
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
        // Plesk migration uses SSH under the hood for file transfer
        $sshMigrator = new SshMigrator();
        $sshMigrator->migrate($job);
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

            return [
                'success' => false,
                'message' => 'Plesk API error: ' . $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Plesk API connection failed: ' . $e->getMessage(),
            ];
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
                        'hosting_type' => $domain['hosting_type'] ?? 'virtual',
                    ];
                }
            }
        } catch (\Exception) {
            // Fall back to SSH discovery
            return $this->discoverViaSsh($credentials, $data);
        }

        return $data;
    }

    private function discoverViaSsh(array $credentials, array $data): array
    {
        // List domains
        $result = $this->sshExec($credentials, "ls -1 /var/www/vhosts/ 2>/dev/null | grep -v -E '^(\\..*|default|chroot|system)\$'", 15);
        if ($result['success']) {
            foreach (array_filter(explode("\n", trim($result['stdout']))) as $domain) {
                $domain = trim($domain);
                if ($domain && str_contains($domain, '.')) {
                    $data['domains'][] = [
                        'name' => $domain,
                        'path' => "/var/www/vhosts/{$domain}/httpdocs/",
                        'type' => 'plesk',
                    ];
                }
            }
        }

        // List databases via plesk CLI
        $result = $this->sshExec($credentials, "mysql -N -e \"SHOW DATABASES\" 2>/dev/null | grep -v -E '^(information_schema|performance_schema|mysql|sys|psa)\$'", 15);
        if ($result['success']) {
            foreach (array_filter(explode("\n", trim($result['stdout']))) as $db) {
                $db = trim($db);
                if ($db) $data['databases'][] = ['name' => $db];
            }
        }

        // Cron jobs
        $result = $this->sshExec($credentials, "crontab -l 2>/dev/null | grep -v '^#' | grep -v '^\$'", 15);
        if ($result['success']) {
            foreach (array_filter(explode("\n", trim($result['stdout']))) as $cron) {
                $cron = trim($cron);
                if ($cron) $data['crons'][] = $cron;
            }
        }

        return $data;
    }
}
