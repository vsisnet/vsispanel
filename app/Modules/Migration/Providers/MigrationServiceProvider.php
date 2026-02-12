<?php

declare(strict_types=1);

namespace App\Modules\Migration\Providers;

use App\Modules\Migration\Services\MigrationService;
use Illuminate\Support\ServiceProvider;

class MigrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MigrationService::class);
    }

    public function boot(): void
    {
        //
    }
}
