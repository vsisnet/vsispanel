<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services\Evaluators;

use App\Modules\Monitoring\Models\AlertRule;
use App\Modules\Monitoring\Models\ServerMetric;
use App\Modules\Monitoring\Services\MetricsCollector;

class ResourceEvaluator implements AlertEvaluatorInterface
{
    public function __construct(
        private MetricsCollector $collector,
    ) {}

    public function supportedMetrics(): array
    {
        return ['cpu', 'memory', 'disk'];
    }

    public function evaluate(AlertRule $rule): array
    {
        $values = $this->getCurrentValues();
        $currentValue = $values[$rule->metric] ?? null;

        if ($currentValue === null) {
            return ['triggered' => false, 'currentValue' => null, 'message' => null];
        }

        $triggered = match ($rule->condition) {
            'above' => $currentValue > $rule->threshold,
            'below' => $currentValue < $rule->threshold,
            'equals' => abs($currentValue - $rule->threshold) < 0.01,
            default => false,
        };

        $message = $triggered
            ? sprintf('%s usage is %.1f%% (threshold: %s %.1f%%)', ucfirst($rule->metric), $currentValue, $rule->condition, $rule->threshold)
            : null;

        return ['triggered' => $triggered, 'currentValue' => $currentValue, 'message' => $message];
    }

    private function getCurrentValues(): array
    {
        // Read from latest saved metric instead of re-collecting
        // Avoids double-collection where cached /proc/stat diff is near-zero
        $latest = ServerMetric::latest('recorded_at')->first();

        if ($latest && $latest->recorded_at->diffInMinutes(now()) < 10) {
            $diskPercentage = 0;
            $diskData = $latest->disk_usage;
            if (is_array($diskData)) {
                foreach ($diskData as $d) {
                    if (($d['mount'] ?? '') === '/' || ($d['mount'] ?? '') === '/dev/root') {
                        $diskPercentage = $d['percentage'] ?? 0;
                        break;
                    }
                }
            }

            $memPercent = $latest->memory_total > 0
                ? round(($latest->memory_used / $latest->memory_total) * 100, 1)
                : 0;

            return [
                'cpu' => $latest->cpu_usage,
                'memory' => $memPercent,
                'disk' => $diskPercentage,
            ];
        }

        // Fallback to direct collection if no recent metrics
        $cpu = $this->collector->collectCpuUsage();
        $memory = $this->collector->collectMemoryUsage();
        $disk = $this->collector->collectDiskUsage();

        $diskPercentage = 0;
        foreach ($disk as $d) {
            if ($d['mount'] === '/' || $d['mount'] === '/dev/root') {
                $diskPercentage = $d['percentage'];
                break;
            }
        }

        return [
            'cpu' => $cpu['percentage'],
            'memory' => $memory['percentage'],
            'disk' => $diskPercentage,
        ];
    }
}
