<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services\Evaluators;

use App\Modules\Monitoring\Models\AlertRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackupFailureEvaluator implements AlertEvaluatorInterface
{
    public function supportedMetrics(): array
    {
        return ['backup_failed'];
    }

    public function evaluate(AlertRule $rule): array
    {
        $failedCount = $this->getRecentFailures($rule);

        $triggered = match ($rule->condition) {
            'above' => $failedCount > $rule->threshold,
            'below' => $failedCount < $rule->threshold,
            'equals' => abs($failedCount - $rule->threshold) < 0.01,
            default => false,
        };

        $message = $triggered
            ? sprintf('Backup failure detected: %d backup(s) failed since last alert', $failedCount)
            : null;

        return ['triggered' => $triggered, 'currentValue' => (float) $failedCount, 'message' => $message];
    }

    private function getRecentFailures(AlertRule $rule): int
    {
        try {
            if (! DB::getSchemaBuilder()->hasTable('backups')) {
                return 0;
            }

            $query = DB::table('backups')->where('status', 'failed');

            // Only count failures since the last time this rule triggered
            if ($rule->last_triggered_at) {
                $query->where('created_at', '>', $rule->last_triggered_at);
            } else {
                // First run: check last 24 hours
                $query->where('created_at', '>=', now()->subDay());
            }

            return (int) $query->count();
        } catch (\Exception $e) {
            Log::warning('BackupFailureEvaluator: ' . $e->getMessage());
            return 0;
        }
    }
}
