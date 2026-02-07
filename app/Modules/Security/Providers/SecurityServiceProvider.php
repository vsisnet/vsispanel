<?php

declare(strict_types=1);

namespace App\Modules\Security\Providers;

use App\Modules\Security\Services\AuditLogService;
use App\Modules\Security\Services\SecurityScoreService;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogService::class, function ($app) {
            return new AuditLogService();
        });

        $this->app->singleton(SecurityScoreService::class, function ($app) {
            return new SecurityScoreService(
                $app->make(\App\Modules\Firewall\Services\FirewallService::class),
                $app->make(\App\Modules\Firewall\Services\Fail2BanService::class),
                $app->make(\App\Modules\Firewall\Services\WafService::class)
            );
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/security.php',
            'security'
        );
    }

    public function boot(): void
    {
        // Routes and migrations are loaded by ModuleServiceProvider
    }
}
