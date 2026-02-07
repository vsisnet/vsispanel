<?php

declare(strict_types=1);

namespace App\Modules\Cron\Providers;

use App\Modules\Cron\Services\CronService;
use Illuminate\Support\ServiceProvider;

class CronServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CronService::class);
    }

    public function boot(): void
    {
        //
    }
}
