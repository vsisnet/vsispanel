<?php

declare(strict_types=1);

namespace App\Modules\SSL\Providers;

use App\Modules\SSL\Models\SslCertificate;
use App\Modules\SSL\Policies\SslCertificatePolicy;
use App\Modules\SSL\Services\SslService;
use App\Modules\WebServer\Services\NginxService;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SSLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SslService::class, function ($app) {
            return new SslService(
                $app->make(SystemCommandExecutor::class),
                $app->make(NginxService::class)
            );
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/ssl.php',
            'ssl'
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerRoutes();
        $this->registerPolicies();
        $this->registerRouteModelBindings();
    }

    protected function registerRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/api.php');
    }

    protected function registerPolicies(): void
    {
        Gate::policy(SslCertificate::class, SslCertificatePolicy::class);
    }

    protected function registerRouteModelBindings(): void
    {
        Route::model('ssl', SslCertificate::class);
    }
}
