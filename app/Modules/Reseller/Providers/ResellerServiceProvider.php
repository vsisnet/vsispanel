<?php

declare(strict_types=1);

namespace App\Modules\Reseller\Providers;

use App\Modules\Reseller\Services\ResellerReportService;
use App\Modules\Reseller\Services\ResellerService;
use Illuminate\Support\ServiceProvider;

class ResellerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResellerService::class);
        $this->app->singleton(ResellerReportService::class);
    }

    public function boot(): void
    {
        //
    }
}
