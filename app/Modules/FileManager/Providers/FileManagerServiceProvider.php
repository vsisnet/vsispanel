<?php

declare(strict_types=1);

namespace App\Modules\FileManager\Providers;

use App\Modules\FileManager\Services\FileManagerService;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FileManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FileManagerService::class, function ($app) {
            return new FileManagerService($app->make(SystemCommandExecutor::class));
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/filemanager.php',
            'filemanager'
        );
    }

    public function boot(): void
    {
        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
