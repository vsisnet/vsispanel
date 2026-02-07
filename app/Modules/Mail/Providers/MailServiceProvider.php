<?php

declare(strict_types=1);

namespace App\Modules\Mail\Providers;

use App\Modules\Mail\Services\DovecotService;
use App\Modules\Mail\Services\MailSecurityService;
use App\Modules\Mail\Services\PostfixService;
use App\Modules\Mail\Services\RspamdService;
use App\Modules\Mail\Services\WebmailService;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register PostfixService
        $this->app->singleton(PostfixService::class, function ($app) {
            return new PostfixService(
                $app->make(SystemCommandExecutor::class)
            );
        });

        // Register DovecotService
        $this->app->singleton(DovecotService::class, function ($app) {
            return new DovecotService(
                $app->make(SystemCommandExecutor::class)
            );
        });

        // Register MailSecurityService
        $this->app->singleton(MailSecurityService::class, function ($app) {
            return new MailSecurityService(
                $app->make(SystemCommandExecutor::class)
            );
        });

        // Register WebmailService
        $this->app->singleton(WebmailService::class, function ($app) {
            return new WebmailService();
        });

        // Register RspamdService for spam filtering
        $this->app->singleton(RspamdService::class, function ($app) {
            return new RspamdService(
                $app->make(SystemCommandExecutor::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Register views
        $this->loadViewsFrom(resource_path('views/templates'), 'mail-templates');
    }
}
