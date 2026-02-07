<?php

declare(strict_types=1);

namespace App\Modules\FTP\Providers;

use App\Modules\FTP\Services\FtpService;
use Illuminate\Support\ServiceProvider;

class FTPServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(FtpService::class, function ($app) {
            return new FtpService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
