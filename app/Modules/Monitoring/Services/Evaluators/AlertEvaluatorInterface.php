<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services\Evaluators;

use App\Modules\Monitoring\Models\AlertRule;

interface AlertEvaluatorInterface
{
    /**
     * Which metric types this evaluator handles.
     *
     * @return string[]
     */
    public function supportedMetrics(): array;

    /**
     * Evaluate an alert rule.
     *
     * @return array{triggered: bool, currentValue: float|null, message: string|null}
     */
    public function evaluate(AlertRule $rule): array;
}
