<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services\Evaluators;

use App\Modules\Monitoring\Models\AlertRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PanelIntrusionEvaluator implements AlertEvaluatorInterface
{
    public function supportedMetrics(): array
    {
        return ['panel_intrusion'];
    }

    public function evaluate(AlertRule $rule): array
    {
        $failedCount = $this->getRecentFailedLogins();

        $triggered = match ($rule->condition) {
            'above' => $failedCount > $rule->threshold,
            'below' => $failedCount < $rule->threshold,
            'equals' => abs($failedCount - $rule->threshold) < 0.01,
            default => false,
        };

        $message = $triggered
            ? sprintf('Panel intrusion detected: %d failed login attempts in the last 15 minutes (threshold: %.0f)', $failedCount, $rule->threshold)
            : null;

        return ['triggered' => $triggered, 'currentValue' => (float) $failedCount, 'message' => $message];
    }

    private function getRecentFailedLogins(): int
    {
        try {
            // Query activity_log from spatie/laravel-activitylog
            if (! DB::getSchemaBuilder()->hasTable('activity_log')) {
                return 0;
            }

            // Count failed logins grouped by IP in the last 15 minutes
            $maxFromSingleIp = DB::table('activity_log')
                ->where('description', 'login_failed')
                ->where('created_at', '>=', now()->subMinutes(15))
                ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.ip')) as ip, COUNT(*) as cnt")
                ->groupBy('ip')
                ->orderByDesc('cnt')
                ->value('cnt');

            return (int) ($maxFromSingleIp ?? 0);
        } catch (\Exception $e) {
            Log::warning('PanelIntrusionEvaluator: ' . $e->getMessage());
            return 0;
        }
    }
}
