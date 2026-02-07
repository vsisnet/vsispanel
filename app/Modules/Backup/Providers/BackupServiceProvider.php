<?php

declare(strict_types=1);

namespace App\Modules\Backup\Providers;

use App\Modules\Backup\Console\Commands\RunScheduledBackups;
use App\Modules\Backup\Services\BackupService;
use Illuminate\Support\ServiceProvider;

class BackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BackupService::class, function ($app) {
            return new BackupService();
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/backup.php',
            'backup'
        );
    }

    public function boot(): void
    {
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunScheduledBackups::class,
            ]);
        }
    }
}
