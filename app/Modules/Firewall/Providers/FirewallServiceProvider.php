<?php

declare(strict_types=1);

namespace App\Modules\Firewall\Providers;

use App\Modules\Firewall\Services\Fail2BanService;
use App\Modules\Firewall\Services\FirewallService;
use App\Modules\Firewall\Services\IpManagementService;
use App\Modules\Firewall\Services\MalwareScanService;
use App\Modules\Firewall\Services\WafService;
use Illuminate\Support\ServiceProvider;

class FirewallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FirewallService::class, function ($app) {
            return new FirewallService();
        });

        $this->app->singleton(Fail2BanService::class, function ($app) {
            return new Fail2BanService();
        });

        $this->app->singleton(IpManagementService::class, function ($app) {
            return new IpManagementService(
                $app->make(FirewallService::class),
                $app->make(Fail2BanService::class)
            );
        });

        $this->app->singleton(WafService::class, function ($app) {
            return new WafService();
        });

        $this->app->singleton(MalwareScanService::class, function ($app) {
            return new MalwareScanService();
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/firewall.php',
            'firewall'
        );
    }

    public function boot(): void
    {
        // Routes and migrations are loaded by ModuleServiceProvider
    }
}
