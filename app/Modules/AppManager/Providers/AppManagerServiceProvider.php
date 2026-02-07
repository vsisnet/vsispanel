<?php

declare(strict_types=1);

namespace App\Modules\AppManager\Providers;

use App\Modules\AppManager\Services\AppManagerService;
use Illuminate\Support\ServiceProvider;

class AppManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/appmanager.php', 'appmanager');

        $this->app->singleton(AppManagerService::class);
    }

    public function boot(): void
    {
        //
    }
}
