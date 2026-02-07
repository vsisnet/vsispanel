<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerModules();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->bootModules();
    }

    /**
     * Register all module service providers.
     */
    protected function registerModules(): void
    {
        $modulesPath = app_path('Modules');

        if (!File::isDirectory($modulesPath)) {
            return;
        }

        $modules = File::directories($modulesPath);

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);
            $providerPath = "$modulePath/Providers/{$moduleName}ServiceProvider.php";

            if (File::exists($providerPath)) {
                $providerClass = "App\\Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";

                if (class_exists($providerClass)) {
                    $this->app->register($providerClass);
                }
            }
        }
    }

    /**
     * Boot all module routes, migrations, and configs.
     */
    protected function bootModules(): void
    {
        $modulesPath = app_path('Modules');

        if (!File::isDirectory($modulesPath)) {
            return;
        }

        $modules = File::directories($modulesPath);

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);

            // Load module routes
            $this->loadModuleRoutes($modulePath, $moduleName);

            // Load module migrations
            $this->loadModuleMigrations($modulePath, $moduleName);

            // Load module config
            $this->loadModuleConfig($modulePath, $moduleName);
        }
    }

    /**
     * Load module routes.
     * Skip modules whose ServiceProvider already loads routes via registerRoutes().
     */
    protected function loadModuleRoutes(string $modulePath, string $moduleName): void
    {
        $providerClass = "App\\Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
        if (class_exists($providerClass) && method_exists($providerClass, 'registerRoutes')) {
            return;
        }

        $apiRoutesPath = "$modulePath/Routes/api.php";
        $webRoutesPath = "$modulePath/Routes/web.php";

        if (File::exists($apiRoutesPath)) {
            $this->loadRoutesFrom($apiRoutesPath);
        }

        if (File::exists($webRoutesPath)) {
            $this->loadRoutesFrom($webRoutesPath);
        }
    }

    /**
     * Load module migrations.
     */
    protected function loadModuleMigrations(string $modulePath, string $moduleName): void
    {
        $migrationsPath = "$modulePath/Database/Migrations";

        if (File::isDirectory($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Load module config.
     */
    protected function loadModuleConfig(string $modulePath, string $moduleName): void
    {
        $configPath = "$modulePath/Config";

        if (!File::isDirectory($configPath)) {
            return;
        }

        $configFiles = File::files($configPath);

        foreach ($configFiles as $configFile) {
            $configName = strtolower($moduleName) . '.' . $configFile->getFilenameWithoutExtension();

            $this->mergeConfigFrom($configFile->getPathname(), $configName);
        }
    }

    /**
     * Get all modules.
     */
    public static function getModules(): array
    {
        $modulesPath = app_path('Modules');

        if (!File::isDirectory($modulesPath)) {
            return [];
        }

        $modules = [];
        $directories = File::directories($modulesPath);

        foreach ($directories as $directory) {
            $moduleName = basename($directory);
            $providerPath = "$directory/Providers/{$moduleName}ServiceProvider.php";

            $modules[$moduleName] = [
                'name' => $moduleName,
                'path' => $directory,
                'enabled' => File::exists($providerPath),
            ];
        }

        return $modules;
    }
}
