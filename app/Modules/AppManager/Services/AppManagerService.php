<?php

declare(strict_types=1);

namespace App\Modules\AppManager\Services;

use App\Modules\AppManager\Models\ManagedApp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class AppManagerService
{
    /**
     * Get all apps grouped by category with live status.
     */
    public function getAllApps(): array
    {
        $this->syncFromConfig();

        $apps = ManagedApp::orderBy('category')->orderBy('name')->get();

        // Refresh running status
        foreach ($apps as $app) {
            $this->refreshStatus($app);
        }

        $categories = config('appmanager.categories', []);
        $grouped = [];

        foreach ($categories as $key => $cat) {
            $items = $apps->where('category', $key)->values();
            if ($items->isNotEmpty()) {
                $grouped[] = [
                    'key' => $key,
                    'label' => $cat['label'],
                    'order' => $cat['order'],
                    'apps' => $items,
                ];
            }
        }

        usort($grouped, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $grouped;
    }

    /**
     * Get single app detail.
     */
    public function getApp(string $slug): ?ManagedApp
    {
        $app = ManagedApp::where('slug', $slug)->first();
        if ($app) {
            $this->refreshStatus($app);
        }

        return $app;
    }

    /**
     * Get per-version details for multi-version apps.
     */
    public function getVersionDetails(ManagedApp $app): array
    {
        $config = config("appmanager.apps.{$app->slug}", []);

        if ($config['type'] !== 'multi_version') {
            return [];
        }

        $available = $config['available_versions'] ?? [];
        $installed = $app->installed_versions ?? [];
        $versions = [];

        foreach ($available as $version) {
            $serviceName = isset($config['service_pattern'])
                ? str_replace('{version}', $version, $config['service_pattern'])
                : null;

            $isInstalled = in_array($version, $installed);
            $isRunning = false;
            $isEnabled = false;

            if ($isInstalled && $serviceName) {
                $result = Process::timeout(5)->run("systemctl is-active " . escapeshellarg($serviceName) . " 2>/dev/null");
                $isRunning = $result->successful() && trim($result->output()) === 'active';

                $result2 = Process::timeout(5)->run("systemctl is-enabled " . escapeshellarg($serviceName) . " 2>/dev/null");
                $isEnabled = $result2->successful() && trim($result2->output()) === 'enabled';
            }

            // Resolve config files for this version
            $configFiles = [];
            foreach ($config['config_pattern'] ?? [] as $key => $path) {
                $configFiles[$key] = str_replace('{version}', $version, $path);
            }

            $versions[] = [
                'version' => $version,
                'installed' => $isInstalled,
                'is_running' => $isRunning,
                'is_enabled' => $isEnabled,
                'is_default' => $version === $app->active_version,
                'service_name' => $serviceName,
                'config_files' => array_keys($configFiles),
            ];
        }

        return $versions;
    }

    /**
     * Scan system and sync config to database.
     */
    public function syncFromConfig(): void
    {
        $appConfigs = config('appmanager.apps', []);

        foreach ($appConfigs as $slug => $config) {
            $app = ManagedApp::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $config['name'],
                    'category' => $config['category'],
                    'type' => $config['type'],
                    'service_name' => $config['service_name'] ?? null,
                    'config_files' => $config['config_files'] ?? null,
                ],
            );

            // Detect installed status
            $this->detectInstallation($app, $config);
        }
    }

    /**
     * Detect if app is installed on the system.
     */
    private function detectInstallation(ManagedApp $app, array $config): void
    {
        // Skip detection if currently installing/uninstalling
        if (in_array($app->status, ['installing', 'uninstalling'])) {
            return;
        }

        if ($config['type'] === 'multi_version') {
            $versions = $this->detectInstalledVersions($app, $config);
            $app->update([
                'status' => ! empty($versions) ? 'installed' : 'not_installed',
                'installed_versions' => $versions,
                'active_version' => $this->detectActiveVersion($app, $config),
                'last_checked_at' => now(),
            ]);
        } else {
            $isInstalled = $this->detectSingleInstallation($config);

            if ($isInstalled && $app->status === 'not_installed') {
                $app->update([
                    'status' => 'installed',
                    'installed_at' => $app->installed_at ?? now(),
                    'last_checked_at' => now(),
                ]);
            } elseif (! $isInstalled && $app->status === 'installed') {
                $app->update([
                    'status' => 'not_installed',
                    'last_checked_at' => now(),
                ]);
            }
        }
    }

    /**
     * Detect if a single-version app is installed.
     */
    private function detectSingleInstallation(array $config): bool
    {
        // Custom detection command (e.g., Composer)
        if (! empty($config['detect_command'])) {
            $result = Process::timeout(5)->run($config['detect_command'] . ' 2>/dev/null');

            return $result->successful() && ! empty(trim($result->output()));
        }

        // Standard package detection via dpkg
        if (! empty($config['packages'])) {
            $pkg = escapeshellarg($config['packages'][0]);
            $result = Process::timeout(5)->run("dpkg -s {$pkg} 2>/dev/null | grep -q '^Status: install ok installed'  && echo yes || echo no");

            return trim($result->output()) === 'yes';
        }

        // Panel services / systemd-based - check if unit file exists
        $serviceName = $config['service_name'] ?? null;
        if ($serviceName) {
            $svc = escapeshellarg($serviceName . '.service');
            $result = Process::timeout(5)->run("systemctl list-unit-files {$svc} 2>/dev/null | grep -q {$svc} && echo yes || echo no");

            return trim($result->output()) === 'yes';
        }

        return false;
    }

    /**
     * Detect installed versions for multi-version apps.
     */
    private function detectInstalledVersions(ManagedApp $app, array $config): array
    {
        $versions = [];
        $available = $config['available_versions'] ?? [];

        // Node.js: check binary install directory
        if ($app->slug === 'nodejs') {
            $installDir = $config['install_dir'] ?? '/usr/local/lib/nodejs';
            foreach ($available as $version) {
                if (is_dir("{$installDir}/node-{$version}")) {
                    $versions[] = $version;
                }
            }

            return $versions;
        }

        // PHP, Python: check dpkg packages
        foreach ($available as $version) {
            $packageName = str_replace('{version}', $version, $config['package_pattern'] ?? '');
            if (empty($packageName)) {
                continue;
            }

            $result = Process::timeout(5)->run('dpkg -l ' . escapeshellarg($packageName) . ' 2>/dev/null | grep -c "^ii"');
            if (trim($result->output()) === '1') {
                $versions[] = $version;
            }
        }

        return $versions;
    }

    /**
     * Detect active/default version for multi-version apps.
     */
    private function detectActiveVersion(ManagedApp $app, array $config): ?string
    {
        if ($app->slug === 'php') {
            $result = Process::timeout(5)->run('php -r "echo PHP_MAJOR_VERSION.\'.\'.PHP_MINOR_VERSION;" 2>/dev/null');
            return $result->successful() ? trim($result->output()) : null;
        }

        if ($app->slug === 'nodejs') {
            $result = Process::timeout(5)->run('node -v 2>/dev/null');
            if ($result->successful()) {
                $v = ltrim(trim($result->output()), 'v');
                $major = explode('.', $v)[0] ?? null;

                return $major;
            }
        }

        if ($app->slug === 'python') {
            $result = Process::timeout(5)->run('python3 --version 2>/dev/null');
            if ($result->successful() && preg_match('/(\d+\.\d+)/', trim($result->output()), $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Refresh service status for an app.
     */
    public function refreshStatus(ManagedApp $app): void
    {
        $serviceName = $app->service_name;

        if ($app->isMultiVersion() && $app->slug === 'php') {
            $activeVersion = $app->active_version ?? '8.3';
            $serviceName = "php{$activeVersion}-fpm";
        }

        if (! $serviceName) {
            return;
        }

        $result = Process::timeout(5)->run("systemctl is-active " . escapeshellarg($serviceName) . " 2>/dev/null");
        $isRunning = $result->successful() && trim($result->output()) === 'active';

        $result2 = Process::timeout(5)->run("systemctl is-enabled " . escapeshellarg($serviceName) . " 2>/dev/null");
        $isEnabled = $result2->successful() && trim($result2->output()) === 'enabled';

        $app->update([
            'is_running' => $isRunning,
            'is_enabled' => $isEnabled,
        ]);
    }

    /**
     * Start a service.
     */
    public function startApp(ManagedApp $app, ?string $version = null): array
    {
        $service = $this->resolveServiceName($app, $version);

        $result = Process::timeout(30)->run("sudo systemctl start " . escapeshellarg($service));

        if ($result->successful()) {
            $this->refreshStatus($app);

            return ['success' => true, 'message' => "Service {$service} started"];
        }

        return ['success' => false, 'message' => "Failed to start {$service}: " . $result->errorOutput()];
    }

    /**
     * Stop a service.
     */
    public function stopApp(ManagedApp $app, ?string $version = null): array
    {
        $service = $this->resolveServiceName($app, $version);

        $result = Process::timeout(30)->run("sudo systemctl stop " . escapeshellarg($service));

        if ($result->successful()) {
            $this->refreshStatus($app);

            return ['success' => true, 'message' => "Service {$service} stopped"];
        }

        return ['success' => false, 'message' => "Failed to stop {$service}: " . $result->errorOutput()];
    }

    /**
     * Restart a service.
     */
    public function restartApp(ManagedApp $app, ?string $version = null): array
    {
        $service = $this->resolveServiceName($app, $version);

        $result = Process::timeout(30)->run("sudo systemctl restart " . escapeshellarg($service));

        if ($result->successful()) {
            $this->refreshStatus($app);

            return ['success' => true, 'message' => "Service {$service} restarted"];
        }

        return ['success' => false, 'message' => "Failed to restart {$service}: " . $result->errorOutput()];
    }

    /**
     * Enable a service.
     */
    public function enableApp(ManagedApp $app, ?string $version = null): array
    {
        $service = $this->resolveServiceName($app, $version);
        $result = Process::timeout(15)->run("sudo systemctl enable " . escapeshellarg($service));

        $this->refreshStatus($app);

        return ['success' => $result->successful(), 'message' => $result->successful() ? "Service {$service} enabled" : $result->errorOutput()];
    }

    /**
     * Disable a service.
     */
    public function disableApp(ManagedApp $app, ?string $version = null): array
    {
        $service = $this->resolveServiceName($app, $version);
        $result = Process::timeout(15)->run("sudo systemctl disable " . escapeshellarg($service));

        $this->refreshStatus($app);

        return ['success' => $result->successful(), 'message' => $result->successful() ? "Service {$service} disabled" : $result->errorOutput()];
    }

    /**
     * Set default version for multi-version app.
     */
    public function setDefaultVersion(ManagedApp $app, string $version): array
    {
        if (! $app->isMultiVersion()) {
            return ['success' => false, 'message' => 'Not a multi-version app'];
        }

        if (! in_array($version, $app->installed_versions ?? [])) {
            return ['success' => false, 'message' => "Version {$version} is not installed"];
        }

        if ($app->slug === 'php') {
            Process::timeout(15)->run("sudo update-alternatives --set php /usr/bin/php{$version} 2>/dev/null");
        }

        if ($app->slug === 'nodejs') {
            $config = config("appmanager.apps.nodejs", []);
            $installDir = $config['install_dir'] ?? '/usr/local/lib/nodejs';
            $binDir = "{$installDir}/node-{$version}/bin";
            Process::timeout(5)->run("sudo ln -sf {$binDir}/node /usr/local/bin/node");
            Process::timeout(5)->run("sudo ln -sf {$binDir}/npm /usr/local/bin/npm");
            Process::timeout(5)->run("sudo ln -sf {$binDir}/npx /usr/local/bin/npx");
        }

        if ($app->slug === 'python') {
            Process::timeout(5)->run("sudo update-alternatives --install /usr/bin/python3 python3 /usr/bin/python{$version} 1 2>/dev/null");
        }

        $app->update(['active_version' => $version]);

        return ['success' => true, 'message' => "Default version set to {$version}"];
    }

    /**
     * Get extensions for a versioned app (PHP).
     */
    public function getExtensions(ManagedApp $app, ?string $version = null): array
    {
        if ($app->slug === 'php') {
            $v = $version ?? $app->active_version ?? '8.3';
            $result = Process::timeout(10)->run("php{$v} -m 2>/dev/null");

            if ($result->successful()) {
                return array_filter(array_map('trim', explode("\n", $result->output())), fn ($l) => $l && ! str_starts_with($l, '['));
            }
        }

        return [];
    }

    /**
     * Get available PHP extensions with install status.
     */
    public function getAvailableExtensions(ManagedApp $app, ?string $version = null): array
    {
        if ($app->slug !== 'php') {
            return [];
        }

        $v = $version ?? $app->active_version ?? '8.3';

        // Get installed extensions
        $installedResult = Process::timeout(10)->run("php{$v} -m 2>/dev/null");
        $installedModules = [];
        if ($installedResult->successful()) {
            $installedModules = array_map(
                'strtolower',
                array_filter(array_map('trim', explode("\n", $installedResult->output())), fn ($l) => $l && ! str_starts_with($l, '['))
            );
        }

        // Get installed packages via dpkg
        $dpkgResult = Process::timeout(10)->run("dpkg -l 'php{$v}-*' 2>/dev/null | grep '^ii' | awk '{print $2}'");
        $installedPackages = [];
        if ($dpkgResult->successful()) {
            $installedPackages = array_filter(array_map('trim', explode("\n", $dpkgResult->output())));
        }

        // Get available packages via apt-cache (only php{version}-* packages)
        $cacheResult = Process::timeout(15)->run("apt-cache search ^php{$v}- 2>/dev/null | grep '^php{$v}-' | sort");
        if (! $cacheResult->successful()) {
            return [];
        }

        // Skip meta/dev/dbg packages and core packages
        $skipSuffixes = ['-dbgsym', '-dev', '-dbg'];
        $skipPackages = ["php{$v}-fpm", "php{$v}-cli", "php{$v}-common", "php{$v}-phpdbg", "php{$v}-cgi"];

        $extensions = [];
        foreach (explode("\n", $cacheResult->output()) as $line) {
            $line = trim($line);
            if (! $line) {
                continue;
            }

            // Format: "php8.3-curl - CURL module for PHP"
            [$package, $description] = array_pad(explode(' - ', $line, 2), 2, '');
            $package = trim($package);
            $description = trim($description);

            if (! $package || in_array($package, $skipPackages, true)) {
                continue;
            }

            // Skip debug/dev packages
            $skip = false;
            foreach ($skipSuffixes as $suffix) {
                if (str_ends_with($package, $suffix)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            // Extract extension name from package (e.g., "php8.3-curl" → "curl")
            $extName = str_replace("php{$v}-", '', $package);

            $extensions[] = [
                'name' => $extName,
                'package' => $package,
                'description' => $description,
                'installed' => in_array($package, $installedPackages, true),
            ];
        }

        return $extensions;
    }

    /**
     * Read a config file.
     */
    public function getConfigFile(ManagedApp $app, string $key, ?string $version = null): array
    {
        $configFiles = $this->resolveConfigFiles($app, $version);
        $path = $configFiles[$key] ?? null;

        if (! $path || ! File::exists($path)) {
            return ['success' => false, 'message' => 'Config file not found'];
        }

        return [
            'success' => true,
            'data' => [
                'key' => $key,
                'path' => $path,
                'content' => File::get($path),
            ],
        ];
    }

    /**
     * Save a config file with backup.
     */
    public function saveConfigFile(ManagedApp $app, string $key, string $content, ?string $version = null): array
    {
        $configFiles = $this->resolveConfigFiles($app, $version);
        $path = $configFiles[$key] ?? null;

        if (! $path) {
            return ['success' => false, 'message' => 'Config file not found'];
        }

        // Backup before save
        if (File::exists($path)) {
            $backupPath = $path . '.bak.' . now()->format('Ymd_His');
            File::copy($path, $backupPath);
        }

        File::put($path, $content);

        // Validate config if possible
        $validation = $this->validateConfig($app, $key);
        if ($validation && ! $validation['valid']) {
            // Restore backup
            if (isset($backupPath)) {
                File::copy($backupPath, $path);
            }

            return ['success' => false, 'message' => 'Config validation failed: ' . $validation['error']];
        }

        return ['success' => true, 'message' => 'Configuration saved'];
    }

    /**
     * Get service logs.
     */
    public function getServiceLogs(ManagedApp $app, int $lines = 100, ?string $version = null): string
    {
        $service = $this->resolveServiceName($app, $version);
        $output = shell_exec("journalctl -u " . escapeshellarg($service) . " --no-pager -n {$lines} 2>/dev/null") ?? '';

        return $output;
    }

    /**
     * Resolve the systemd service name.
     */
    private function resolveServiceName(ManagedApp $app, ?string $version = null): string
    {
        if ($app->isMultiVersion()) {
            $config = config("appmanager.apps.{$app->slug}", []);
            $v = $version ?? $app->active_version ?? ($config['available_versions'][0] ?? '');
            $pattern = $config['service_pattern'] ?? '';

            return str_replace('{version}', $v, $pattern);
        }

        return $app->service_name ?? $app->slug;
    }

    /**
     * Resolve config file paths (handle version patterns).
     */
    private function resolveConfigFiles(ManagedApp $app, ?string $version = null): array
    {
        $config = config("appmanager.apps.{$app->slug}", []);

        if ($app->isMultiVersion() && isset($config['config_pattern'])) {
            $version = $version ?? $app->active_version ?? ($config['available_versions'][0] ?? '');
            $files = [];
            foreach ($config['config_pattern'] as $key => $path) {
                $files[$key] = str_replace('{version}', $version, $path);
            }

            return $files;
        }

        return $config['config_files'] ?? [];
    }

    /**
     * Validate config file syntax.
     */
    private function validateConfig(ManagedApp $app, string $key): ?array
    {
        if ($app->slug === 'nginx') {
            $result = Process::timeout(10)->run('sudo nginx -t 2>&1');

            return ['valid' => $result->successful(), 'error' => $result->errorOutput()];
        }

        if ($app->slug === 'php' && $key === 'fpm') {
            $version = $app->active_version ?? '8.3';
            $result = Process::timeout(10)->run("sudo php-fpm{$version} -t 2>&1");

            return ['valid' => $result->successful(), 'error' => $result->errorOutput()];
        }

        return null;
    }
}
