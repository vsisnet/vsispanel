<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services\Evaluators;

use App\Modules\Monitoring\Models\AlertRule;
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
