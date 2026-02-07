<?php

declare(strict_types=1);

namespace App\Modules\WebServer\Providers;

use App\Modules\WebServer\Services\NginxService;
use App\Modules\WebServer\Services\PhpFpmService;
use Illuminate\Support\ServiceProvider;

class WebServerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config - config file is in the root config folder
        if (file_exists(config_path('webserver.php'))) {
            $this->mergeConfigFrom(
                config_path('webserver.php'),
                'webserver'
            );
        }

        // Register NginxService as singleton
        $this->app->singleton(NginxService::class, function ($app) {
            return new NginxService(
                $app->make(\App\Services\SystemCommandExecutor::class)
            );
        });

        // Register PhpFpmService as singleton
        $this->app->singleton(PhpFpmService::class, function ($app) {
            return new PhpFpmService(
                $app->make(\App\Services\SystemCommandExecutor::class),
                $app->make(NginxService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load views for nginx templates
        $this->loadViewsFrom(
            resource_path('views/templates/nginx'),
            'nginx'
        );

        // Load views for php-fpm templates
        $this->loadViewsFrom(
            resource_path('views/templates/php-fpm'),
            'php-fpm'
        );
    }
}
