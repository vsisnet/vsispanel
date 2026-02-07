<?php

declare(strict_types=1);

namespace App\Modules\Database\Providers;

use App\Modules\Database\Models\DatabaseUser;
use App\Modules\Database\Models\ManagedDatabase;
use App\Modules\Database\Policies\DatabaseUserPolicy;
use App\Modules\Database\Policies\ManagedDatabasePolicy;
use App\Modules\Database\Services\DatabaseService;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register DatabaseService as singleton
        $this->app->singleton(DatabaseService::class, function ($app) {
            return new DatabaseService(
                $app->make(SystemCommandExecutor::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerPolicies();
    }

    /**
     * Register the module's policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(ManagedDatabase::class, ManagedDatabasePolicy::class);
        Gate::policy(DatabaseUser::class, DatabaseUserPolicy::class);
    }
}
