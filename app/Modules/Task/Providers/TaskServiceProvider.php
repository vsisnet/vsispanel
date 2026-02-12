<?php

declare(strict_types=1);

namespace App\Modules\Task\Providers;

use App\Modules\Task\Console\Commands\CleanupStuckTasks;
use Illuminate\Support\ServiceProvider;

class TaskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Modules\Task\Services\TaskService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupStuckTasks::class,
            ]);
        }
    }
}
