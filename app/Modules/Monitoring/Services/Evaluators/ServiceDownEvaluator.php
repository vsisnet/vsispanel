<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services\Evaluators;

use App\Modules\Monitoring\Models\AlertRule;
use Illuminate\Support\Facades\Process;

class ServiceDownEvaluator implements AlertEvaluatorInterface
{
    public function supportedMetrics(): array
    {
        return ['service_down'];
    }

    public function evaluate(AlertRule $rule): array
    {
        $serviceName = $rule->config['service_name'] ?? null;

        if (! $serviceName) {
            return ['triggered' => false, 'currentValue' => null, 'message' => 'No service_name configured'];
        }

        $result = Process::timeout(5)->run("systemctl is-active " . escapeshellarg($serviceName));
        $isRunning = $result->successful() && trim($result->output()) === 'active';

        // currentValue: 1 = running, 0 = down
        $currentValue = $isRunning ? 1.0 : 0.0;
        $triggered = ! $isRunning;

        $message = $triggered
            ? sprintf('Service "%s" is down', $serviceName)
            : null;

        return ['triggered' => $triggered, 'currentValue' => $currentValue, 'message' => $message];
    }
}
