<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Providers;

use App\Modules\Monitoring\Services\AlertEvaluator;
use App\Modules\Monitoring\Services\Evaluators\BackupFailureEvaluator;
use App\Modules\Monitoring\Services\Evaluators\PanelIntrusionEvaluator;
use App\Modules\Monitoring\Services\Evaluators\ResourceEvaluator;
use App\Modules\Monitoring\Services\Evaluators\ServiceDownEvaluator;
use App\Modules\Monitoring\Services\Evaluators\SshBruteForceEvaluator;
use App\Modules\Monitoring\Services\Evaluators\SslExpiryEvaluator;
use App\Modules\Monitoring\Services\MetricsCollector;
use Illuminate\Support\ServiceProvider;

class MonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetricsCollector::class);
        $this->app->singleton(ResourceEvaluator::class);
        $this->app->singleton(ServiceDownEvaluator::class);
        $this->app->singleton(SshBruteForceEvaluator::class);
        $this->app->singleton(PanelIntrusionEvaluator::class);
        $this->app->singleton(BackupFailureEvaluator::class);
        $this->app->singleton(SslExpiryEvaluator::class);

        $this->app->singleton(AlertEvaluator::class, function ($app) {
            return new AlertEvaluator([
                $app->make(ResourceEvaluator::class),
                $app->make(ServiceDownEvaluator::class),
                $app->make(SshBruteForceEvaluator::class),
                $app->make(PanelIntrusionEvaluator::class),
                $app->make(BackupFailureEvaluator::class),
                $app->make(SslExpiryEvaluator::class),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}
