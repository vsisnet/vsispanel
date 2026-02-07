<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Services;

use App\Modules\Domain\Models\Domain;
use App\Modules\Marketplace\Models\AppInstallation;
use App\Modules\Marketplace\Models\AppTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

class AppInstallerService
{
    /**
     * Get all available app templates.
     */
    public function getAvailableApps(): Collection
    {
        return AppTemplate::where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Extension name aliases: package names that map to different PHP module names.
     * e.g. php8.3-mysql installs 'mysqli' and 'mysqlnd', not 'mysql' (removed in PHP 7).
     */
    private const EXTENSION_ALIASES = [
        'mysql' => ['mysqli', 'mysqlnd', 'pdo_mysql'],
        'xml' => ['dom', 'simplexml', 'xmlreader', 'xmlwriter', 'xml'],
    ];

    /**
     * Check if a domain meets the app requirements.
     */
    public function checkRequirements(Domain $domain, AppTemplate $app): array
    {
        $requirements = $app->requirements ?? [];
        $checks = [];
        $allPassed = true;

        // Use the domain's PHP version, not the panel's
        $domainPhpVersion = $domain->php_version ?? (PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);

        // Check PHP version
        if (! empty($requirements['php_version'])) {
            $passed = version_compare($domainPhpVersion, $requirements['php_version'], '>=');
            $checks[] = [
                'name' => 'PHP Version',
                'required' => '>= ' . $requirements['php_version'],
                'current' => $domainPhpVersion,
                'passed' => $passed,
            ];
            if (! $passed) $allPassed = false;
        }

        // Check PHP extensions using the domain's PHP version
        if (! empty($requirements['extensions'])) {
            $loadedModules = $this->getLoadedModules($domainPhpVersion);

            foreach ($requirements['extensions'] as $ext) {
                $passed = $this->isExtensionLoaded($ext, $loadedModules);
                $checks[] = [
                    'name' => "PHP Extension: {$ext}",
                    'required' => 'installed',
                    'current' => $passed ? 'installed' : 'missing',
                    'passed' => $passed,
                ];
                if (! $passed) $allPassed = false;
            }
        }

        // Check disk space
        if (! empty($requirements['min_disk_mb'])) {
            $username = $domain->user->username ?? $domain->user->name;
            $docRoot = $domain->document_root ?: "/home/{$username}/domains/{$domain->name}/public_html";
            $parentDir = dirname($docRoot);
            $freeBytes = @disk_free_space($parentDir) ?: 0;
            $freeMb = (int) ($freeBytes / 1024 / 1024);
            $passed = $freeMb >= $requirements['min_disk_mb'];
            $checks[] = [
                'name' => 'Disk Space',
                'required' => $requirements['min_disk_mb'] . ' MB',
                'current' => $freeMb . ' MB free',
                'passed' => $passed,
            ];
            if (! $passed) $allPassed = false;
        }

        return [
            'passed' => $allPassed,
            'checks' => $checks,
        ];
    }

    /**
     * Get loaded PHP modules for a specific PHP version.
     *
     * @return array<string> Lowercase module names
     */
    private function getLoadedModules(string $phpVersion): array
    {
        $binary = "/usr/bin/php{$phpVersion}";
        $result = Process::timeout(10)->run("{$binary} -m 2>/dev/null");

        if (! $result->successful()) {
            // Fallback to current PHP process
            return array_map('strtolower', get_loaded_extensions());
        }

        return array_filter(
            array_map('strtolower', array_map('trim', explode("\n", $result->output()))),
            fn (string $line) => $line !== '' && ! str_starts_with($line, '['),
        );
    }

    /**
     * Check if an extension (or one of its aliases) is loaded.
     */
    private function isExtensionLoaded(string $ext, array $loadedModules): bool
    {
        $extLower = strtolower($ext);

        if (in_array($extLower, $loadedModules, true)) {
            return true;
        }

        // Check aliases (e.g. 'mysql' → 'mysqli', 'mysqlnd')
        $aliases = self::EXTENSION_ALIASES[$extLower] ?? [];
        foreach ($aliases as $alias) {
            if (in_array(strtolower($alias), $loadedModules, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Start an app installation.
     */
    public function install(Domain $domain, AppTemplate $app, array $options, string $userId): AppInstallation
    {
        $installation = AppInstallation::create([
            'domain_id' => $domain->id,
            'app_template_id' => $app->id,
            'installed_by' => $userId,
            'app_version' => $app->version,
            'status' => 'pending',
            'progress' => 0,
            'options' => $options,
        ]);

        // Dispatch installation job
        dispatch(new \App\Modules\Marketplace\Jobs\InstallAppJob($installation));

        return $installation;
    }

    /**
     * Get installation status.
     */
    public function getInstallationStatus(string $installationId): ?AppInstallation
    {
        return AppInstallation::with('template')->find($installationId);
    }

    /**
     * Get installations for a domain.
     */
    public function getDomainInstallations(Domain $domain): Collection
    {
        return AppInstallation::where('domain_id', $domain->id)
            ->with('template')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
