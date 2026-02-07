<?php

declare(strict_types=1);

namespace App\Modules\Server\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ServerServiceProvider extends ServiceProvider
{
    /**
     * The module namespace.
     */
    protected string $moduleNamespace = 'App\Modules\Server\Http\Controllers';

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \App\Modules\Server\Services\SystemInfoService::class,
            \App\Modules\Server\Services\SystemInfoService::class
        );

        $this->mergeConfigFrom(__DIR__ . '/../Config/terminal.php', 'terminal');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
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
    }
}
