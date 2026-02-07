<?php

declare(strict_types=1);

namespace App\Modules\DNS\Providers;

use App\Modules\DNS\Services\PowerDnsService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DNSServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PowerDnsService::class, function ($app) {
            return new PowerDnsService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
