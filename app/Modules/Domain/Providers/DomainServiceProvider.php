<?php

declare(strict_types=1);

namespace App\Modules\Domain\Providers;

use App\Modules\DNS\Services\PowerDnsService;
use App\Modules\Domain\Models\Domain;
use App\Modules\Domain\Policies\DomainPolicy;
use App\Modules\Domain\Services\DomainService;
use App\Modules\SSL\Services\SslService;
use App\Modules\WebServer\Services\NginxService;
use App\Modules\WebServer\Services\PhpFpmService;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register DomainService with all dependencies
        $this->app->singleton(DomainService::class, function ($app) {
            return new DomainService(
                $app->make(SystemCommandExecutor::class),
                $app->bound(NginxService::class) ? $app->make(NginxService::class) : null,
                $app->bound(PhpFpmService::class) ? $app->make(PhpFpmService::class) : null,
                $app->bound(SslService::class) ? $app->make(SslService::class) : null,
                $app->bound(PowerDnsService::class) ? $app->make(PowerDnsService::class) : null,
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Migrations and routes are loaded by ModuleServiceProvider
        $this->registerPolicies();
    }

    /**
     * Register the module's policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Domain::class, DomainPolicy::class);
    }
}
