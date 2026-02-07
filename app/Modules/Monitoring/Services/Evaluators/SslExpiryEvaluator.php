<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services\Evaluators;

use App\Modules\Monitoring\Models\AlertRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SslExpiryEvaluator implements AlertEvaluatorInterface
{
    public function supportedMetrics(): array
    {
        return ['ssl_expiry'];
    }

    public function evaluate(AlertRule $rule): array
    {
        $minDaysUntilExpiry = $this->getMinDaysUntilExpiry();

        if ($minDaysUntilExpiry === null) {
            return ['triggered' => false, 'currentValue' => null, 'message' => null];
        }

        $daysBefore = $rule->config['days_before'] ?? (int) $rule->threshold;

        // Trigger if any cert expires within the configured days
        $triggered = $minDaysUntilExpiry <= $daysBefore;

        $message = $triggered
            ? sprintf('SSL certificate expiring in %d days (threshold: %d days)', $minDaysUntilExpiry, $daysBefore)
            : null;

        return ['triggered' => $triggered, 'currentValue' => (float) $minDaysUntilExpiry, 'message' => $message];
    }

    private function getMinDaysUntilExpiry(): ?int
    {
        try {
            if (! DB::getSchemaBuilder()->hasTable('ssl_certificates')) {
                return null;
            }

            $nearest = DB::table('ssl_certificates')
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->orderBy('expires_at')
                ->value('expires_at');

            if (! $nearest) {
                return null;
            }

            return (int) now()->diffInDays($nearest, false);
        } catch (\Exception $e) {
            Log::warning('SslExpiryEvaluator: ' . $e->getMessage());
            return null;
        }
    }
}
