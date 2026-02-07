<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Providers;

use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use App\Modules\Hosting\Policies\PlanPolicy;
use App\Modules\Hosting\Policies\SubscriptionPolicy;
use App\Modules\Hosting\Services\PlanService;
use App\Modules\Hosting\Services\QuotaService;
use App\Modules\Hosting\Services\SubscriptionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class HostingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register services as singletons
        $this->app->singleton(QuotaService::class, function ($app) {
            return new QuotaService();
        });

        $this->app->singleton(PlanService::class, function ($app) {
            return new PlanService();
        });

        $this->app->singleton(SubscriptionService::class, function ($app) {
            return new SubscriptionService(
                $app->make(QuotaService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }

    /**
     * Register the module's policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
    }
}
