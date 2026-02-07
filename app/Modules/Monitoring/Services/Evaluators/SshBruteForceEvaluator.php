<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services\Evaluators;

use App\Modules\Monitoring\Models\AlertRule;
use Illuminate\Support\Facades\Process;

class SshBruteForceEvaluator implements AlertEvaluatorInterface
{
    public function supportedMetrics(): array
    {
        return ['ssh_brute_force'];
    }

    public function evaluate(AlertRule $rule): array
    {
        $failedCount = $this->getRecentFailedAttempts();

        $triggered = match ($rule->condition) {
            'above' => $failedCount > $rule->threshold,
            'below' => $failedCount < $rule->threshold,
            'equals' => abs($failedCount - $rule->threshold) < 0.01,
            default => false,
        };

        $message = $triggered
            ? sprintf('SSH brute force detected: %d failed login attempts in the last 10 minutes (threshold: %.0f)', $failedCount, $rule->threshold)
            : null;

        return ['triggered' => $triggered, 'currentValue' => (float) $failedCount, 'message' => $message];
    }

    private function getRecentFailedAttempts(): int
    {
        // Try fail2ban first
        $result = Process::timeout(5)->run('fail2ban-client status sshd 2>/dev/null');

        if ($result->successful()) {
            if (preg_match('/Currently banned:\s+(\d+)/', $result->output(), $matches)) {
                $banned = (int) $matches[1];
                if ($banned > 0) {
                    return $banned;
                }
            }
        }

        // Fallback: parse auth.log for failed attempts in last 10 minutes
        $since = now()->subMinutes(10)->format('Y-m-d H:i:s');
        $result = Process::timeout(10)->run(
            "journalctl _COMM=sshd --since " . escapeshellarg($since) . " --no-pager 2>/dev/null | grep -c 'Failed password' || echo 0"
        );

        return (int) trim($result->output());
    }
}
