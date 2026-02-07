<?php

declare(strict_types=1);

namespace App\Modules\WebServer\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use App\Services\CommandResult;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PhpFpmService
{
    protected string $configDir;
    protected string $socketDir;
    protected array $availableVersions;
    protected array $defaultPoolSettings;
    protected array $defaultPhpSettings;

    public function __construct(
        protected SystemCommandExecutor $executor,
        protected ?NginxService $nginxService = null
    ) {
        $this->configDir = config('webserver.php_fpm.config_dir', '/etc/php');
        $this->socketDir = config('webserver.php_fpm.socket_dir', '/run/php');
        $this->availableVersions = config('webserver.php_fpm.available_versions', ['7.4', '8.0', '8.1', '8.2', '8.3']);
        $this->defaultPoolSettings = config('webserver.php_fpm.default_pool_settings', [
            'pm' => 'dynamic',
            'max_children' => 5,
            'start_servers' => 2,
            'min_spare_servers' => 1,
            'max_spare_servers' => 3,
            'max_requests' => 500,
        ]);
        $this->defaultPhpSettings = [
            'memory_limit' => '256M',
            'upload_max_filesize' => '64M',
            'post_max_size' => '64M',
            'max_execution_time' => '30',
            'max_input_time' => '60',
            'display_errors' => 'off',
            'max_input_vars' => '1000',
        ];
    }

    /**
     * Get all installed PHP versions with their status.
     */
    public function getInstalledVersions(): array
    {
        $versions = [];

        foreach ($this->availableVersions as $version) {
            $versionDir = "{$this->configDir}/{$version}";
            $serviceName = "php{$version}-fpm";

            // Check if FPM is actually installed (not just CLI/directory)
            $fpmBinary = "/usr/sbin/php-fpm{$version}";
            $fpmCheck = $this->executor->execute('test', ['-x', $fpmBinary]);
            $installed = $fpmCheck->success;

            $running = false;
            if ($installed) {
                $statusResult = $this->executor->executeAsRoot('systemctl', ['is-active', $serviceName]);
                $running = $statusResult->success && trim($statusResult->stdout) === 'active';
            }

            $versions[$version] = [
                'version' => $version,
                'installed' => $installed,
                'running' => $running,
                'service_name' => $serviceName,
                'config_dir' => $versionDir,
                'pool_dir' => $this->getPoolDir($version),
            ];
        }

        return $versions;
    }

    /**
     * Create a PHP-FPM pool for a user.
     */
    public function createPool(User $user, string $phpVersion, array $settings = []): void
    {
        $username = $this->getUsername($user);
        $this->ensureSystemUserExists($username);
        $poolDir = $this->getPoolDir($phpVersion);
        $poolPath = "{$poolDir}/{$username}.conf";

        // Merge with default settings
        $settings = array_merge($this->defaultPoolSettings, $settings);

        // Generate pool configuration
        $content = $this->generatePoolConfig($user, $phpVersion, $settings);

        // Ensure pool directory exists
        $this->executor->executeAsRoot('mkdir', ['-p', $poolDir]);

        // Write pool configuration
        $this->writeConfig($poolPath, $content);

        // Restart PHP-FPM service
        $this->restartService($phpVersion);

        Log::channel('commands')->info('PHP-FPM pool created', [
            'username' => $username,
            'php_version' => $phpVersion,
            'pool_path' => $poolPath,
        ]);
    }

    /**
     * Delete a PHP-FPM pool for a user.
     */
    public function deletePool(User $user, string $phpVersion): void
    {
        $username = $this->getUsername($user);
        $poolDir = $this->getPoolDir($phpVersion);
        $poolPath = "{$poolDir}/{$username}.conf";

        // Check if pool exists
        $result = $this->executor->execute('test', ['-f', $poolPath]);

        if (!$result->success) {
            return; // Pool doesn't exist
        }

        // Backup pool configuration
        $backupDir = config('webserver.nginx.config_backup_dir', '/var/vsispanel/backups') . '/php-fpm';
        $backupPath = "{$backupDir}/{$username}_{$phpVersion}_" . now()->format('YmdHis') . '.conf';

        $this->executor->executeAsRoot('mkdir', ['-p', $backupDir]);
        $this->executor->executeAsRoot('mv', [$poolPath, $backupPath]);

        // Restart PHP-FPM service
        $this->restartService($phpVersion);

        Log::channel('commands')->info('PHP-FPM pool deleted', [
            'username' => $username,
            'php_version' => $phpVersion,
        ]);
    }

    /**
     * Update a PHP-FPM pool configuration.
     */
    public function updatePool(User $user, string $phpVersion, array $settings): void
    {
        $username = $this->getUsername($user);
        $poolDir = $this->getPoolDir($phpVersion);
        $poolPath = "{$poolDir}/{$username}.conf";

        // Backup current configuration
        $backupDir = config('webserver.nginx.config_backup_dir', '/var/vsispanel/backups') . '/php-fpm';
        $backupPath = "{$backupDir}/{$username}_{$phpVersion}_" . now()->format('YmdHis') . '.conf';

        $this->executor->executeAsRoot('mkdir', ['-p', $backupDir]);
        $this->executor->executeAsRoot('cp', [$poolPath, $backupPath]);

        // Merge with default settings
        $settings = array_merge($this->defaultPoolSettings, $settings);

        // Generate new pool configuration
        $content = $this->generatePoolConfig($user, $phpVersion, $settings);

        // Write new configuration
        $this->writeConfig($poolPath, $content);

        // Restart PHP-FPM service
        $this->restartService($phpVersion);

        Log::channel('commands')->info('PHP-FPM pool updated', [
            'username' => $username,
            'php_version' => $phpVersion,
        ]);
    }

    /**
     * Switch PHP version for a domain.
     */
    public function switchVersion(Domain $domain, string $fromVersion, string $toVersion): void
    {
        $user = $domain->user;
        $username = $this->getUsername($user);

        // Ensure pool exists for the new version
        $poolPath = $this->getPoolDir($toVersion) . "/{$username}.conf";
        $result = $this->executor->execute('test', ['-f', $poolPath]);

        if (!$result->success) {
            // Create pool for new version
            $this->createPool($user, $toVersion);
        }

        // Update domain PHP version
        $domain->update(['php_version' => $toVersion]);

        // Wait for the new PHP-FPM socket to be ready before updating nginx
        $socketPath = $this->getDomainSocketPath($domain);
        $this->waitForSocket($socketPath);

        // Update Nginx vhost to use new PHP socket
        if ($this->nginxService && $domain->web_server_type === 'nginx') {
            $this->nginxService->updateVhost($domain);
        }

        Log::channel('commands')->info('Domain PHP version switched', [
            'domain' => $domain->name,
            'from' => $fromVersion,
            'to' => $toVersion,
        ]);
    }

    /**
     * Get PHP information for a specific version.
     */
    public function getPhpInfo(string $version): array
    {
        $info = [
            'version' => $version,
            'full_version' => null,
            'extensions' => [],
            'ini_settings' => [],
            'disabled_functions' => [],
        ];

        // Get full version
        $result = $this->executor->execute("php{$version}", ['-v']);
        if ($result->success) {
            preg_match('/PHP (\d+\.\d+\.\d+)/', $result->stdout, $matches);
            $info['full_version'] = $matches[1] ?? $version;
        }

        // Get loaded extensions
        $result = $this->executor->execute("php{$version}", ['-m']);
        if ($result->success) {
            $info['extensions'] = array_filter(
                array_map('trim', explode("\n", $result->stdout)),
                fn($ext) => !empty($ext) && !str_starts_with($ext, '[')
            );
        }

        // Get common ini settings
        $settings = [
            'memory_limit',
            'upload_max_filesize',
            'post_max_size',
            'max_execution_time',
            'max_input_time',
            'display_errors',
            'error_reporting',
            'date.timezone',
            'open_basedir',
        ];

        foreach ($settings as $setting) {
            $result = $this->executor->execute("php{$version}", ['-i']);
            if ($result->success && preg_match("/{$setting}\s*=>\s*([^\n]+)/", $result->stdout, $matches)) {
                $info['ini_settings'][$setting] = trim(explode('=>', $matches[1])[0] ?? $matches[1]);
            }
        }

        // Get disabled functions
        $result = $this->executor->execute("php{$version}", ['-r', 'echo ini_get("disable_functions");']);
        if ($result->success && !empty($result->stdout)) {
            $info['disabled_functions'] = array_filter(
                array_map('trim', explode(',', $result->stdout))
            );
        }

        return $info;
    }

    /**
     * Update PHP.ini settings for a user.
     */
    public function updatePhpIni(User $user, string $version, array $settings): void
    {
        $username = $this->getUsername($user);

        // Whitelist of allowed settings
        $allowedSettings = [
            'memory_limit',
            'upload_max_filesize',
            'post_max_size',
            'max_execution_time',
            'max_input_time',
            'display_errors',
            'log_errors',
            'error_log',
            'date.timezone',
            'session.gc_maxlifetime',
            'session.cookie_lifetime',
            'max_input_vars',
        ];

        // Filter settings to only allowed ones
        $filteredSettings = array_intersect_key($settings, array_flip($allowedSettings));

        if (empty($filteredSettings)) {
            return;
        }

        // Generate custom ini content
        $content = "; Custom PHP settings for {$username}\n";
        $content .= "; Generated by VSISPanel at " . now()->toISOString() . "\n\n";

        foreach ($filteredSettings as $key => $value) {
            $content .= "{$key} = {$value}\n";
        }

        // Custom ini path
        $confDir = "{$this->configDir}/{$version}/fpm/conf.d";
        $iniPath = "{$confDir}/vsispanel-{$username}.ini";

        // Ensure directory exists
        $this->executor->executeAsRoot('mkdir', ['-p', $confDir]);

        // Write ini file
        $this->writeConfig($iniPath, $content);

        // Restart PHP-FPM
        $this->restartService($version);

        Log::channel('commands')->info('PHP.ini settings updated', [
            'username' => $username,
            'php_version' => $version,
            'settings' => array_keys($filteredSettings),
        ]);
    }

    /**
     * Get custom PHP settings for a user.
     * @deprecated Use getDomainPhpSettings instead for per-domain settings
     */
    public function getUserPhpSettings(User $user, string $version): array
    {
        $username = $this->getUsername($user);
        $iniPath = "{$this->configDir}/{$version}/fpm/conf.d/vsispanel-{$username}.ini";

        $result = $this->executor->execute('test', ['-f', $iniPath]);

        if (!$result->success) {
            return [];
        }

        $result = $this->executor->executeAsRoot('cat', [$iniPath]);

        if (!$result->success) {
            return [];
        }

        $settings = [];
        $lines = explode("\n", $result->stdout);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, ';')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    /**
     * Create a PHP-FPM pool for a domain.
     */
    public function createDomainPool(Domain $domain, array $settings = []): void
    {
        $poolName = $this->getDomainPoolName($domain);
        $phpVersion = $domain->php_version ?? '8.3';
        $poolDir = $this->getPoolDir($phpVersion);
        $poolPath = "{$poolDir}/{$poolName}.conf";

        // Ensure the system user exists (PHP-FPM requires a valid Linux user)
        $username = $this->getUsername($domain->user);
        $this->ensureSystemUserExists($username);

        // Merge with default settings
        $poolSettings = array_merge($this->defaultPoolSettings, $settings['pool'] ?? []);
        $phpSettings = array_merge($this->defaultPhpSettings, $settings['php'] ?? []);

        // Generate pool configuration
        $content = $this->generateDomainPoolConfig($domain, $phpVersion, $poolSettings, $phpSettings);

        // Ensure pool directory exists
        $this->executor->executeAsRoot('mkdir', ['-p', $poolDir]);

        // Write pool configuration
        $this->writeConfig($poolPath, $content);

        // Restart PHP-FPM service
        $this->restartService($phpVersion);

        Log::channel('commands')->info('PHP-FPM domain pool created', [
            'domain' => $domain->name,
            'pool_name' => $poolName,
            'php_version' => $phpVersion,
            'pool_path' => $poolPath,
        ]);
    }

    /**
     * Delete a PHP-FPM pool for a domain.
     */
    public function deleteDomainPool(Domain $domain, ?string $phpVersion = null): void
    {
        $poolName = $this->getDomainPoolName($domain);
        $phpVersion = $phpVersion ?? $domain->php_version ?? '8.3';
        $poolDir = $this->getPoolDir($phpVersion);
        $poolPath = "{$poolDir}/{$poolName}.conf";

        // Check if pool exists
        $result = $this->executor->execute('test', ['-f', $poolPath]);

        if (!$result->success) {
            return; // Pool doesn't exist
        }

        // Backup pool configuration
        $backupDir = config('webserver.nginx.config_backup_dir', '/var/vsispanel/backups') . '/php-fpm';
        $backupPath = "{$backupDir}/{$poolName}_{$phpVersion}_" . now()->format('YmdHis') . '.conf';

        $this->executor->executeAsRoot('mkdir', ['-p', $backupDir]);
        $this->executor->executeAsRoot('mv', [$poolPath, $backupPath]);

        // Restart PHP-FPM service
        $this->restartService($phpVersion);

        Log::channel('commands')->info('PHP-FPM domain pool deleted', [
            'domain' => $domain->name,
            'pool_name' => $poolName,
            'php_version' => $phpVersion,
        ]);
    }

    /**
     * Update PHP settings for a specific domain.
     * Settings are stored in the domain's pool configuration file using php_admin_value directives.
     */
    public function updateDomainPhpSettings(Domain $domain, array $settings): void
    {
        $poolName = $this->getDomainPoolName($domain);
        $phpVersion = $domain->php_version ?? '8.3';
        $poolDir = $this->getPoolDir($phpVersion);
        $poolPath = "{$poolDir}/{$poolName}.conf";

        // Check if pool exists
        $result = $this->executor->execute('test', ['-f', $poolPath]);

        if (!$result->success) {
            // Create the pool first
            $this->createDomainPool($domain, ['php' => $settings]);
            return;
        }

        // Whitelist of allowed PHP settings
        $allowedSettings = [
            'memory_limit',
            'upload_max_filesize',
            'post_max_size',
            'max_execution_time',
            'max_input_time',
            'display_errors',
            'log_errors',
            'date.timezone',
            'session.gc_maxlifetime',
            'session.cookie_lifetime',
            'max_input_vars',
        ];

        // Filter settings to only allowed ones
        $filteredSettings = array_intersect_key($settings, array_flip($allowedSettings));

        if (empty($filteredSettings)) {
            return;
        }

        // Backup current configuration
        $backupDir = config('webserver.nginx.config_backup_dir', '/var/vsispanel/backups') . '/php-fpm';
        $backupPath = "{$backupDir}/{$poolName}_{$phpVersion}_" . now()->format('YmdHis') . '.conf';

        $this->executor->executeAsRoot('mkdir', ['-p', $backupDir]);
        $this->executor->executeAsRoot('cp', [$poolPath, $backupPath]);

        // Read current pool configuration
        $result = $this->executor->executeAsRoot('cat', [$poolPath]);
        if (!$result->success) {
            throw new RuntimeException("Failed to read pool configuration: {$poolPath}");
        }

        $content = $result->stdout;

        // Update each setting in the pool file
        foreach ($filteredSettings as $key => $value) {
            // Determine if this is a flag (on/off) or value setting
            $isFlag = in_array($key, ['display_errors', 'log_errors']);
            $directive = $isFlag ? 'php_admin_flag' : 'php_admin_value';

            // Pattern to find existing setting
            $pattern = "/^{$directive}\[{$key}\]\s*=\s*.+$/m";

            // Format value
            $formattedValue = $isFlag ? ($value === 'on' || $value === true || $value === '1' ? 'on' : 'off') : $value;
            $newLine = "{$directive}[{$key}] = {$formattedValue}";

            if (preg_match($pattern, $content)) {
                // Replace existing setting
                $content = preg_replace($pattern, $newLine, $content);
            } else {
                // Add new setting before the last line or at the end
                // Find the position to insert (after other php_admin_* lines)
                $lines = explode("\n", $content);
                $insertIndex = count($lines) - 1;

                // Find the last php_admin_* line
                for ($i = count($lines) - 1; $i >= 0; $i--) {
                    if (str_starts_with(trim($lines[$i]), 'php_admin_')) {
                        $insertIndex = $i + 1;
                        break;
                    }
                }

                array_splice($lines, $insertIndex, 0, $newLine);
                $content = implode("\n", $lines);
            }
        }

        // Write updated configuration
        $this->writeConfig($poolPath, $content);

        // Restart PHP-FPM service
        $this->restartService($phpVersion);

        Log::channel('commands')->info('Domain PHP settings updated', [
            'domain' => $domain->name,
            'php_version' => $phpVersion,
            'settings' => array_keys($filteredSettings),
        ]);
    }

    /**
     * Get PHP settings for a specific domain from its pool configuration.
     */
    public function getDomainPhpSettings(Domain $domain): array
    {
        $poolName = $this->getDomainPoolName($domain);
        $phpVersion = $domain->php_version ?? '8.3';
        $poolDir = $this->getPoolDir($phpVersion);
        $poolPath = "{$poolDir}/{$poolName}.conf";

        $result = $this->executor->execute('test', ['-f', $poolPath]);

        if (!$result->success) {
            // Return defaults if pool doesn't exist
            return $this->defaultPhpSettings;
        }

        $result = $this->executor->executeAsRoot('cat', [$poolPath]);

        if (!$result->success) {
            return $this->defaultPhpSettings;
        }

        $settings = [];
        $lines = explode("\n", $result->stdout);

        // Parse php_admin_value and php_admin_flag lines
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, ';')) {
                continue;
            }

            // Match php_admin_value[setting] = value
            if (preg_match('/^php_admin_value\[([^\]]+)\]\s*=\s*(.+)$/', $line, $matches)) {
                $settings[$matches[1]] = trim($matches[2]);
            }
            // Match php_admin_flag[setting] = value
            elseif (preg_match('/^php_admin_flag\[([^\]]+)\]\s*=\s*(.+)$/', $line, $matches)) {
                $settings[$matches[1]] = trim($matches[2]);
            }
        }

        // Merge with defaults for any missing settings
        return array_merge($this->defaultPhpSettings, array_intersect_key($settings, $this->defaultPhpSettings));
    }

    /**
     * Get socket path for a domain.
     */
    public function getDomainSocketPath(Domain $domain): string
    {
        $poolName = $this->getDomainPoolName($domain);
        $phpVersion = $domain->php_version ?? '8.3';
        return "{$this->socketDir}/php{$phpVersion}-fpm-{$poolName}.sock";
    }

    /**
     * Check if a domain pool exists.
     */
    public function domainPoolExists(Domain $domain): bool
    {
        $poolName = $this->getDomainPoolName($domain);
        $phpVersion = $domain->php_version ?? '8.3';
        $poolPath = $this->getPoolDir($phpVersion) . "/{$poolName}.conf";

        $result = $this->executor->execute('test', ['-f', $poolPath]);
        return $result->success;
    }

    /**
     * Switch PHP version for a domain (domain-based pool).
     */
    public function switchDomainVersion(Domain $domain, string $fromVersion, string $toVersion): void
    {
        // Verify the target PHP-FPM version is installed
        $fpmBinary = "/usr/sbin/php-fpm{$toVersion}";
        $checkResult = $this->executor->execute('test', ['-x', $fpmBinary]);
        if (!$checkResult->success) {
            throw new RuntimeException("PHP {$toVersion} FPM is not installed. Install php{$toVersion}-fpm first.");
        }

        // Get current settings before switching
        $currentSettings = $this->getDomainPhpSettings($domain);

        // Step 1: Create new pool FIRST (keep old pool alive as fallback)
        $domain->update(['php_version' => $toVersion]);
        $this->createDomainPool($domain, ['php' => $currentSettings]);

        // Step 2: Wait for the new socket
        $socketPath = $this->getDomainSocketPath($domain);
        $this->waitForSocket($socketPath);

        // Step 3: Update Nginx vhost
        try {
            if ($this->nginxService && $domain->web_server_type === 'nginx') {
                $this->nginxService->updateVhost($domain);
            }
        } catch (\Exception $e) {
            // Nginx update failed - rollback: restore old version and delete new pool
            $this->deleteDomainPool($domain, $toVersion);
            $domain->update(['php_version' => $fromVersion]);
            throw $e;
        }

        // Step 4: Success - now safe to delete old pool
        $this->deleteDomainPool($domain, $fromVersion);

        Log::channel('commands')->info('Domain PHP version switched', [
            'domain' => $domain->name,
            'from' => $fromVersion,
            'to' => $toVersion,
        ]);
    }

    /**
     * Wait for a PHP-FPM socket file to become available.
     */
    protected function waitForSocket(string $socketPath, int $maxWaitSeconds = 10): void
    {
        for ($i = 0; $i < $maxWaitSeconds * 10; $i++) {
            $result = $this->executor->execute('test', ['-S', $socketPath]);
            if ($result->success) {
                return;
            }
            usleep(100_000); // 100ms
        }

        Log::channel('commands')->warning('PHP-FPM socket not ready after waiting', [
            'socket' => $socketPath,
            'waited_seconds' => $maxWaitSeconds,
        ]);
    }

    /**
     * Get sanitized pool name for a domain.
     */
    protected function getDomainPoolName(Domain $domain): string
    {
        // Use domain name with dots replaced by underscores
        // e.g., demo.example.com -> demo_example_com
        return Str::slug($domain->name, '_');
    }

    /**
     * Generate pool configuration for a domain.
     */
    protected function generateDomainPoolConfig(Domain $domain, string $phpVersion, array $poolSettings, array $phpSettings): string
    {
        $poolName = $this->getDomainPoolName($domain);
        $socketPath = $this->getDomainSocketPath($domain);
        $username = $this->getUsername($domain->user);
        $disabledFunctions = implode(',', config('webserver.php_fpm.disabled_functions', []));

        $data = [
            'domain' => $domain,
            'user' => $domain->user,
            'username' => $username,
            'pool_name' => $poolName,
            'php_version' => $phpVersion,
            'socket_path' => $socketPath,
            'settings' => $poolSettings,
            'php_settings' => $phpSettings,
            'disabled_functions' => $disabledFunctions,
        ];

        return view('templates.php-fpm.domain-pool', $data)->render();
    }

    /**
     * Get socket path for a user and PHP version.
     */
    public function getSocketPath(User $user, string $phpVersion): string
    {
        $username = $this->getUsername($user);
        return "{$this->socketDir}/php{$phpVersion}-fpm-{$username}.sock";
    }

    /**
     * Check if a pool exists for a user.
     */
    public function poolExists(User $user, string $phpVersion): bool
    {
        $username = $this->getUsername($user);
        $poolPath = $this->getPoolDir($phpVersion) . "/{$username}.conf";

        $result = $this->executor->execute('test', ['-f', $poolPath]);
        return $result->success;
    }

    /**
     * Restart PHP-FPM service for a specific version.
     */
    public function restartService(string $phpVersion): CommandResult
    {
        $serviceName = "php{$phpVersion}-fpm";
        return $this->executor->executeAsRoot('systemctl', ['restart', $serviceName]);
    }

    /**
     * Reload PHP-FPM service for a specific version.
     */
    public function reloadService(string $phpVersion): CommandResult
    {
        $serviceName = "php{$phpVersion}-fpm";
        return $this->executor->executeAsRoot('systemctl', ['reload', $serviceName]);
    }

    /**
     * Get service status for a PHP version.
     */
    public function getServiceStatus(string $phpVersion): array
    {
        $serviceName = "php{$phpVersion}-fpm";

        $result = $this->executor->executeAsRoot('systemctl', ['is-active', $serviceName]);
        $isActive = $result->success && trim($result->stdout) === 'active';

        return [
            'service' => $serviceName,
            'running' => $isActive,
            'php_version' => $phpVersion,
        ];
    }

    /**
     * Generate pool configuration content.
     */
    protected function generatePoolConfig(User $user, string $phpVersion, array $settings): string
    {
        $username = $this->getUsername($user);
        $socketPath = $this->getSocketPath($user, $phpVersion);
        $disabledFunctions = implode(',', config('webserver.php_fpm.disabled_functions', []));

        $data = [
            'user' => $user,
            'username' => $username,
            'php_version' => $phpVersion,
            'socket_path' => $socketPath,
            'settings' => $settings,
            'disabled_functions' => $disabledFunctions,
        ];

        return view('templates.php-fpm.pool', $data)->render();
    }

    /**
     * Get pool directory for a PHP version.
     */
    protected function getPoolDir(string $version): string
    {
        $template = config('webserver.php_fpm.pool_dir', '/etc/php/{version}/fpm/pool.d');
        return str_replace('{version}', $version, $template);
    }

    /**
     * Get username from user.
     */
    protected function getUsername(User $user): string
    {
        return $user->username ?? \Illuminate\Support\Str::slug($user->name, '_');
    }

    /**
     * Ensure the system user exists on the Linux system.
     * PHP-FPM pools require a valid system user to run as.
     */
    protected function ensureSystemUserExists(string $username): void
    {
        $result = $this->executor->execute('id', [$username]);
        if ($result->success) {
            return; // User already exists
        }

        // Create system user with no login shell and home directory
        $homePath = "/home/{$username}";
        $this->executor->executeAsRoot('useradd', [
            '-r',
            '-d', $homePath,
            '-s', '/usr/sbin/nologin',
            '-M', // Don't create home dir (it may already exist)
            $username,
        ]);

        // Ensure home directory exists and is owned correctly
        $this->executor->executeAsRoot('mkdir', ['-p', $homePath]);
        $this->executor->executeAsRoot('chown', ['-R', "{$username}:{$username}", $homePath]);

        Log::channel('commands')->info('System user created for PHP-FPM', [
            'username' => $username,
        ]);
    }

    /**
     * Write configuration to file.
     */
    protected function writeConfig(string $path, string $content): void
    {
        $tempFile = '/tmp/phpfpm_' . md5($path) . '_' . time() . '.conf';
        file_put_contents($tempFile, $content);

        $this->executor->executeAsRoot('cp', [$tempFile, $path]);
        $this->executor->executeAsRoot('chmod', ['644', $path]);
        $this->executor->executeAsRoot('chown', ['root:root', $path]);

        @unlink($tempFile);
    }
}
