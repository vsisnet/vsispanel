<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Jobs;

use App\Modules\Monitoring\Services\AlertEvaluator;
use App\Modules\Monitoring\Services\MetricsCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CollectMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 30;

    public function handle(MetricsCollector $collector, AlertEvaluator $evaluator): void
    {
        try {
            $collector->collectAndSave();
        } catch (\Exception $e) {
            Log::error('Failed to collect metrics: ' . $e->getMessage());
        }

        // Evaluate alerts separately so it still runs (via Redis cache)
        // even if metrics saving failed due to MySQL being down
        try {
            $evaluator->evaluate();
        } catch (\Exception $e) {
            Log::error('Failed to evaluate alerts: ' . $e->getMessage());
        }
    }
}
