<?php

declare(strict_types=1);

namespace App\Modules\WebServer\Services;

use App\Modules\Domain\Models\Domain;
use App\Services\CommandResult;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NginxService
{
    protected string $sitesAvailable;
    protected string $sitesEnabled;
    protected string $backupDir;
    protected string $templatePath;
    protected string $socketDir;

    public function __construct(
        protected SystemCommandExecutor $executor
    ) {
        $this->sitesAvailable = config('webserver.nginx.sites_available', '/etc/nginx/sites-available');
        $this->sitesEnabled = config('webserver.nginx.sites_enabled', '/etc/nginx/sites-enabled');
        $this->backupDir = config('webserver.nginx.config_backup_dir', '/var/vsispanel/backups/nginx');
        $this->templatePath = config('webserver.nginx.template_path', resource_path('views/templates/nginx'));
        $this->socketDir = config('webserver.php_fpm.socket_dir', '/run/php');
    }

    /**
     * Create a virtual host configuration for a domain.
     */
    public function createVhost(Domain $domain): void
    {
        $configPath = $this->getConfigPath($domain);
        $enabledPath = $this->getEnabledPath($domain);

        // Generate configuration content
        $content = $this->generateVhostConfig($domain);

        // Ensure directories exist
        $this->ensureDirectoriesExist();

        // Write configuration file
        $this->writeConfig($configPath, $content);

        // Create symlink to sites-enabled
        $this->enableSite($domain);

        // Test configuration
        if (!$this->testConfig()) {
            // Rollback on failure
            $this->rollback($configPath, $enabledPath);
            throw new RuntimeException("Nginx configuration test failed for domain: {$domain->name}");
        }

        // Reload nginx
        $this->reload();

        Log::channel('commands')->info('Nginx vhost created', [
            'domain' => $domain->name,
            'config_path' => $configPath,
        ]);
    }

    /**
     * Delete a virtual host configuration.
     */
    public function deleteVhost(Domain $domain): void
    {
        $configPath = $this->getConfigPath($domain);
        $enabledPath = $this->getEnabledPath($domain);

        // Backup before deletion
        $this->backupConfig($configPath, $domain->name);

        // Remove symlink from sites-enabled
        $this->disableSite($domain);

        // Archive config (move to backup, don't delete)
        if ($this->fileExists($configPath)) {
            $archivePath = $this->backupDir . '/archived/' . $domain->name . '_' . now()->format('YmdHis') . '.conf';
            $this->executor->executeAsRoot('mkdir', ['-p', dirname($archivePath)]);
            $this->executor->executeAsRoot('mv', [$configPath, $archivePath]);
        }

        // Reload nginx
        $this->reload();

        Log::channel('commands')->info('Nginx vhost deleted', [
            'domain' => $domain->name,
        ]);
    }

    /**
     * Update a virtual host configuration.
     */
    public function updateVhost(Domain $domain): void
    {
        $configPath = $this->getConfigPath($domain);
        $enabledPath = $this->getEnabledPath($domain);

        // Backup current config
        $backupPath = $this->backupConfig($configPath, $domain->name);

        // Generate new configuration
        if ($domain->ssl_enabled) {
            $redirectContent = $this->generateRedirectConfig($domain);
            $sslContent = $this->generateSslVhostConfig($domain);
            $content = $redirectContent . "\n\n" . $sslContent;
        } else {
            $content = $this->generateVhostConfig($domain);
        }

        // Write new configuration
        $this->writeConfig($configPath, $content);

        // Test configuration
        if (!$this->testConfig()) {
            // Rollback on failure
            if ($backupPath && $this->fileExists($backupPath)) {
                $this->executor->executeAsRoot('cp', [$backupPath, $configPath]);
            }
            throw new RuntimeException("Nginx configuration test failed during update for domain: {$domain->name}");
        }

        // Reload nginx
        $this->reload();

        Log::channel('commands')->info('Nginx vhost updated', [
            'domain' => $domain->name,
        ]);
    }

    /**
     * Enable SSL for a domain.
     */
    public function enableSsl(Domain $domain, string $certPath, string $keyPath, ?string $chainPath = null): void
    {
        $configPath = $this->getConfigPath($domain);

        // Backup current config
        $backupPath = $this->backupConfig($configPath, $domain->name);

        // Generate SSL configuration
        $content = $this->generateSslVhostConfig($domain, $certPath, $keyPath, $chainPath);

        // Also add HTTP to HTTPS redirect
        $redirectContent = $this->generateRedirectConfig($domain);
        $content = $redirectContent . "\n\n" . $content;

        // Write new configuration
        $this->writeConfig($configPath, $content);

        // Test configuration
        if (!$this->testConfig()) {
            // Rollback on failure
            if ($backupPath && $this->fileExists($backupPath)) {
                $this->executor->executeAsRoot('cp', [$backupPath, $configPath]);
            }
            throw new RuntimeException("Nginx SSL configuration test failed for domain: {$domain->name}");
        }

        // Update domain model
        $domain->enableSsl();

        // Reload nginx
        $this->reload();

        Log::channel('commands')->info('SSL enabled for domain', [
            'domain' => $domain->name,
            'cert_path' => $certPath,
        ]);
    }

    /**
     * Disable SSL for a domain.
     */
    public function disableSsl(Domain $domain): void
    {
        $configPath = $this->getConfigPath($domain);

        // Backup current config
        $this->backupConfig($configPath, $domain->name);

        // Generate standard HTTP configuration
        $content = $this->generateVhostConfig($domain);

        // Write configuration
        $this->writeConfig($configPath, $content);

        // Test configuration
        if (!$this->testConfig()) {
            throw new RuntimeException("Nginx configuration test failed while disabling SSL for domain: {$domain->name}");
        }

        // Update domain model
        $domain->disableSsl();

        // Reload nginx
        $this->reload();

        Log::channel('commands')->info('SSL disabled for domain', [
            'domain' => $domain->name,
        ]);
    }

    /**
     * Test nginx configuration.
     */
    public function testConfig(): bool
    {
        $result = $this->executor->executeAsRoot('nginx', ['-t']);

        if (!$result->success) {
            Log::channel('commands')->error('Nginx config test failed', [
                'stderr' => $result->stderr,
            ]);
        }

        return $result->success;
    }

    /**
     * Reload nginx configuration.
     */
    public function reload(): CommandResult
    {
        $result = $this->executor->executeAsRoot('systemctl', ['reload', 'nginx']);

        if (!$result->success) {
            Log::channel('commands')->error('Nginx reload failed', [
                'stderr' => $result->stderr,
            ]);
        }

        return $result;
    }

    /**
     * Restart nginx service.
     */
    public function restart(): CommandResult
    {
        return $this->executor->executeAsRoot('systemctl', ['restart', 'nginx']);
    }

    /**
     * Get access log for a domain.
     */
    public function getAccessLog(Domain $domain, int $lines = 100): string
    {
        $logPath = $domain->access_log_path;

        if (!$this->fileExists($logPath)) {
            return '';
        }

        $result = $this->executor->executeAsRoot('tail', ['-n', (string) $lines, $logPath]);

        return $result->success ? $result->stdout : '';
    }

    /**
     * Get error log for a domain.
     */
    public function getErrorLog(Domain $domain, int $lines = 100): string
    {
        $logPath = $domain->error_log_path;

        if (!$this->fileExists($logPath)) {
            return '';
        }

        $result = $this->executor->executeAsRoot('tail', ['-n', (string) $lines, $logPath]);

        return $result->success ? $result->stdout : '';
    }

    /**
     * Get real-time log stream (for live tailing).
     */
    public function tailLog(Domain $domain, string $type = 'access', int $lines = 50): string
    {
        $logPath = $type === 'access' ? $domain->access_log_path : $domain->error_log_path;

        if (!$this->fileExists($logPath)) {
            return '';
        }

        $result = $this->executor->executeAsRoot('tail', ['-n', (string) $lines, $logPath]);

        return $result->success ? $result->stdout : '';
    }

    /**
     * Generate standard HTTP vhost configuration.
     */
    public function generateVhostConfig(Domain $domain): string
    {
        $data = $this->prepareVhostData($domain);

        return view('templates.nginx.vhost', $data)->render();
    }

    /**
     * Generate SSL vhost configuration.
     */
    public function generateSslVhostConfig(
        Domain $domain,
        ?string $certPath = null,
        ?string $keyPath = null,
        ?string $chainPath = null
    ): string {
        $data = $this->prepareVhostData($domain);

        // Add SSL paths
        $data['cert_path'] = $certPath ?? $this->getDefaultCertPath($domain);
        $data['key_path'] = $keyPath ?? $this->getDefaultKeyPath($domain);
        $data['chain_path'] = $chainPath ?? $this->getDefaultChainPath($domain);

        // SSL settings
        $data['ssl_protocols'] = config('webserver.ssl.protocols', 'TLSv1.2 TLSv1.3');
        $data['ssl_ciphers'] = config('webserver.ssl.ciphers');
        $data['hsts_enabled'] = config('webserver.ssl.hsts_enabled', true);
        $data['hsts_max_age'] = config('webserver.ssl.hsts_max_age', 31536000);
        $data['ocsp_stapling'] = config('webserver.ssl.ocsp_stapling', true);

        return view('templates.nginx.vhost-ssl', $data)->render();
    }

    /**
     * Generate HTTP to HTTPS redirect configuration.
     */
    public function generateRedirectConfig(Domain $domain): string
    {
        $data = [
            'domain' => $domain,
            'server_name' => $domain->name,
            'www_server_name' => 'www.' . $domain->name,
        ];

        return view('templates.nginx.vhost-redirect', $data)->render();
    }

    /**
     * Prepare common vhost data.
     */
    protected function prepareVhostData(Domain $domain): array
    {
        $username = $domain->user->username ?? Str::slug($domain->user->name, '_');
        $poolName = $this->getDomainPoolName($domain);

        return [
            'domain' => $domain,
            'server_name' => $domain->name,
            'www_server_name' => 'www.' . $domain->name,
            'document_root' => $domain->document_root_path,
            'access_log' => $domain->access_log_path,
            'error_log' => $domain->error_log_path,
            'php_version' => $domain->php_version,
            'username' => $username,
            // Use domain-based socket path for per-domain PHP settings
            'php_socket' => "{$this->socketDir}/php{$domain->php_version}-fpm-{$poolName}.sock",
            'security_headers' => config('webserver.security_headers', []),
        ];
    }

    /**
     * Get sanitized pool name for a domain.
     * Must match the pool name used in PhpFpmService.
     */
    protected function getDomainPoolName(Domain $domain): string
    {
        return Str::slug($domain->name, '_');
    }

    /**
     * Get configuration file path for a domain.
     */
    protected function getConfigPath(Domain $domain): string
    {
        return $this->sitesAvailable . '/' . $domain->name . '.conf';
    }

    /**
     * Get enabled symlink path for a domain.
     */
    protected function getEnabledPath(Domain $domain): string
    {
        return $this->sitesEnabled . '/' . $domain->name . '.conf';
    }

    /**
     * Enable a site by creating symlink.
     */
    protected function enableSite(Domain $domain): void
    {
        $configPath = $this->getConfigPath($domain);
        $enabledPath = $this->getEnabledPath($domain);

        // Remove existing symlink if exists
        if ($this->fileExists($enabledPath)) {
            $this->executor->executeAsRoot('rm', ['-f', $enabledPath]);
        }

        // Create new symlink
        $this->executor->executeAsRoot('ln', ['-s', $configPath, $enabledPath]);
    }

    /**
     * Disable a site by removing symlink.
     */
    protected function disableSite(Domain $domain): void
    {
        $enabledPath = $this->getEnabledPath($domain);

        if ($this->fileExists($enabledPath)) {
            $this->executor->executeAsRoot('rm', ['-f', $enabledPath]);
        }
    }

    /**
     * Ensure required directories exist.
     */
    protected function ensureDirectoriesExist(): void
    {
        $dirs = [
            $this->sitesAvailable,
            $this->sitesEnabled,
            $this->backupDir,
        ];

        foreach ($dirs as $dir) {
            $this->executor->executeAsRoot('mkdir', ['-p', $dir]);
        }
    }

    /**
     * Write configuration to file.
     */
    protected function writeConfig(string $path, string $content): void
    {
        // Write to temp file first
        $tempFile = '/tmp/nginx_' . md5($path) . '_' . time() . '.conf';
        file_put_contents($tempFile, $content);

        // Move to destination with proper permissions
        $this->executor->executeAsRoot('cp', [$tempFile, $path]);
        $this->executor->executeAsRoot('chmod', ['644', $path]);
        $this->executor->executeAsRoot('chown', ['root:root', $path]);

        // Clean up temp file
        @unlink($tempFile);
    }

    /**
     * Backup configuration file.
     */
    protected function backupConfig(string $configPath, string $domainName): ?string
    {
        if (!$this->fileExists($configPath)) {
            return null;
        }

        $backupPath = $this->backupDir . '/' . $domainName . '_' . now()->format('YmdHis') . '.conf';

        $this->executor->executeAsRoot('mkdir', ['-p', $this->backupDir]);
        $this->executor->executeAsRoot('cp', [$configPath, $backupPath]);

        return $backupPath;
    }

    /**
     * Rollback configuration changes.
     */
    protected function rollback(string $configPath, string $enabledPath): void
    {
        // Remove failed configuration
        if ($this->fileExists($configPath)) {
            $this->executor->executeAsRoot('rm', ['-f', $configPath]);
        }

        // Remove symlink
        if ($this->fileExists($enabledPath)) {
            $this->executor->executeAsRoot('rm', ['-f', $enabledPath]);
        }

        Log::channel('commands')->warning('Nginx config rolled back');
    }

    /**
     * Check if file exists.
     */
    protected function fileExists(string $path): bool
    {
        $result = $this->executor->execute('test', ['-e', $path]);
        return $result->success;
    }

    /**
     * Get default SSL certificate path for a domain.
     */
    protected function getDefaultCertPath(Domain $domain): string
    {
        $letsencryptPath = config('webserver.ssl.letsencrypt_dir', '/etc/letsencrypt/live');
        return "{$letsencryptPath}/{$domain->name}/fullchain.pem";
    }

    /**
     * Get default SSL key path for a domain.
     */
    protected function getDefaultKeyPath(Domain $domain): string
    {
        $letsencryptPath = config('webserver.ssl.letsencrypt_dir', '/etc/letsencrypt/live');
        return "{$letsencryptPath}/{$domain->name}/privkey.pem";
    }

    /**
     * Get default SSL chain path for a domain.
     */
    protected function getDefaultChainPath(Domain $domain): string
    {
        $letsencryptPath = config('webserver.ssl.letsencrypt_dir', '/etc/letsencrypt/live');
        return "{$letsencryptPath}/{$domain->name}/chain.pem";
    }

    /**
     * Get nginx service status.
     */
    public function getStatus(): array
    {
        $result = $this->executor->executeAsRoot('systemctl', ['is-active', 'nginx']);
        $isActive = $result->success && trim($result->stdout) === 'active';

        $result = $this->executor->executeAsRoot('nginx', ['-v']);
        $version = $result->success ? trim($result->stderr) : 'unknown';

        return [
            'running' => $isActive,
            'version' => $version,
        ];
    }

    /**
     * List all virtual hosts.
     */
    public function listVhosts(): array
    {
        $vhosts = [];

        $result = $this->executor->execute('ls', ['-1', $this->sitesAvailable]);

        if ($result->success && !empty($result->stdout)) {
            $files = explode("\n", trim($result->stdout));

            foreach ($files as $file) {
                if (empty($file) || !str_ends_with($file, '.conf')) {
                    continue;
                }

                $domain = str_replace('.conf', '', $file);
                $enabledPath = $this->sitesEnabled . '/' . $file;

                $isEnabled = $this->fileExists($enabledPath);

                $vhosts[] = [
                    'domain' => $domain,
                    'config_file' => $this->sitesAvailable . '/' . $file,
                    'enabled' => $isEnabled,
                ];
            }
        }

        return $vhosts;
    }
}
