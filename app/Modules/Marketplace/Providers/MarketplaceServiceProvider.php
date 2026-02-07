<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Providers;

use App\Modules\Marketplace\Services\AppInstallerService;
use Illuminate\Support\ServiceProvider;

class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppInstallerService::class);
    }

    public function boot(): void
    {
        //
    }
}
