<?php

declare(strict_types=1);

namespace App\Modules\Auth\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The module namespace.
     */
    protected string $moduleNamespace = 'App\Modules\Auth\Http\Controllers';

    /**
     * Register services.
     */
    public function register(): void
    {
        // Register module services, bindings, or singletons here
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerTranslations();
    }

    /**
     * Register module routes.
     */
    protected function registerRoutes(): void
    {
        $modulePath = __DIR__ . '/..';

        // API Routes
        if (file_exists($modulePath . '/Routes/api.php')) {
            Route::middleware('api')
                ->prefix('api/v1')
                ->namespace($this->moduleNamespace)
                ->group($modulePath . '/Routes/api.php');
        }

        // Web Routes
        if (file_exists($modulePath . '/Routes/web.php')) {
            Route::middleware('web')
                ->namespace($this->moduleNamespace)
                ->group($modulePath . '/Routes/web.php');
        }
    }

    /**
     * Register module migrations.
     */
    protected function registerMigrations(): void
    {
        $modulePath = __DIR__ . '/..';
        $migrationsPath = $modulePath . '/Database/Migrations';

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Register module config.
     */
    protected function registerConfig(): void
    {
        $modulePath = __DIR__ . '/..';
        $configPath = $modulePath . '/Config';

        if (is_dir($configPath)) {
            foreach (glob($configPath . '/*.php') as $configFile) {
                $configName = 'auth.' . basename($configFile, '.php');
                $this->mergeConfigFrom($configFile, $configName);
            }
        }
    }

    /**
     * Register module views.
     */
    protected function registerViews(): void
    {
        $modulePath = __DIR__ . '/..';
        $viewsPath = $modulePath . '/Resources/views';

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, 'auth');
        }
    }

    /**
     * Register module translations.
     */
    protected function registerTranslations(): void
    {
        $modulePath = __DIR__ . '/..';
        $translationsPath = $modulePath . '/Resources/lang';

        if (is_dir($translationsPath)) {
            $this->loadTranslationsFrom($translationsPath, 'auth');
        }
    }
}
