<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Jobs;

use App\Modules\Monitoring\Models\AlertHistory;
use App\Modules\Monitoring\Models\ServerMetric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupOldMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $retentionDays = config('monitoring.retention_days', 90);
        $cutoff = now()->subDays($retentionDays);

        $deleted = ServerMetric::where('recorded_at', '<', $cutoff)->delete();
        $alertsDeleted = AlertHistory::where('triggered_at', '<', $cutoff)->delete();

        Log::info("Monitoring cleanup: deleted {$deleted} metrics, {$alertsDeleted} alert history records older than {$retentionDays} days");
    }
}
