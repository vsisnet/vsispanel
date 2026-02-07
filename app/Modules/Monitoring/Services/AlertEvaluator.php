<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services;

use App\Modules\Monitoring\Models\AlertHistory;
use App\Modules\Monitoring\Models\AlertRule;
use App\Modules\Monitoring\Services\Evaluators\AlertEvaluatorInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertEvaluator
{
    /** @var AlertEvaluatorInterface[] */
    private array $evaluators;

    /**
     * @param iterable<AlertEvaluatorInterface> $evaluators
     */
    public function __construct(iterable $evaluators)
    {
        $this->evaluators = $evaluators instanceof \Traversable
            ? iterator_to_array($evaluators)
            : (array) $evaluators;
    }

    private const CACHE_KEY_RULES = 'vsispanel:alert_rules';

    /**
     * Evaluate all active alert rules against current metrics.
     */
    public function evaluate(): void
    {
        $rules = $this->getActiveRules();

        if ($rules->isEmpty()) {
            return;
        }

        foreach ($rules as $rule) {
            $this->evaluateRule($rule);
        }
    }

    /**
     * Get active alert rules with Redis fallback when MySQL is unavailable.
     */
    private function getActiveRules(): \Illuminate\Database\Eloquent\Collection
    {
        try {
            $rules = AlertRule::where('is_active', true)->get();
            // Store raw DB attributes so hydrate() works correctly with JSON casts
            Cache::store('redis')->put(self::CACHE_KEY_RULES, json_encode(
                $rules->map(fn ($rule) => $rule->getAttributes())->toArray()
            ));

            return $rules;
        } catch (QueryException $e) {
            Log::warning('MySQL unavailable for alert rules, using Redis cache', [
                'error' => $e->getMessage(),
            ]);

            $cached = Cache::store('redis')->get(self::CACHE_KEY_RULES);
            if (! $cached) {
                Log::error('No cached alert rules available in Redis');

                return new \Illuminate\Database\Eloquent\Collection();
            }

            return AlertRule::hydrate(json_decode($cached, true));
        }
    }

    /**
     * Evaluate a single alert rule using the appropriate evaluator.
     */
    private function evaluateRule(AlertRule $rule): void
    {
        $evaluator = $this->getEvaluatorForMetric($rule->metric);

        if (! $evaluator) {
            Log::warning("No evaluator found for metric: {$rule->metric}");
            return;
        }

        try {
            $result = $evaluator->evaluate($rule);
        } catch (\Exception $e) {
            Log::error("Evaluator error for rule '{$rule->name}': " . $e->getMessage());
            return;
        }

        if (! $result['triggered']) {
            return;
        }

        // Check cooldown
        if ($rule->last_triggered_at &&
            $rule->last_triggered_at->addMinutes($rule->cooldown_minutes)->isFuture()) {
            return;
        }

        $this->triggerAlert($rule, $result['currentValue'] ?? 0, $result['message']);
    }

    /**
     * Find the evaluator that supports a given metric.
     */
    private function getEvaluatorForMetric(string $metric): ?AlertEvaluatorInterface
    {
        foreach ($this->evaluators as $evaluator) {
            if (in_array($metric, $evaluator->supportedMetrics(), true)) {
                return $evaluator;
            }
        }

        return null;
    }

    /**
     * Trigger an alert and send notifications.
     */
    private function triggerAlert(AlertRule $rule, float $currentValue, ?string $message = null): void
    {
        $severityLabel = strtoupper($rule->severity ?? 'WARNING');
        $alertMessage = $message ?? sprintf(
            '[%s] %s - %s is %s %.1f (threshold: %s %.1f)',
            $severityLabel,
            $rule->name,
            $rule->metric,
            $rule->condition,
            $currentValue,
            $rule->condition,
            $rule->threshold,
        );

        Log::warning('Monitoring alert triggered', [
            'rule' => $rule->name,
            'metric' => $rule->metric,
            'severity' => $rule->severity,
            'category' => $rule->category,
            'value' => $currentValue,
            'threshold' => $rule->threshold,
        ]);

        $channels = $rule->notification_channels ?? ['email'];
        $notificationSent = false;

        foreach ($channels as $channel) {
            try {
                $this->sendNotification($channel, $rule, $currentValue, "[{$severityLabel}] {$alertMessage}");
                $notificationSent = true;
            } catch (\Exception $e) {
                Log::error("Failed to send alert via {$channel}: " . $e->getMessage());
            }
        }

        try {
            AlertHistory::create([
                'alert_rule_id' => $rule->id,
                'current_value' => $currentValue,
                'notification_sent' => $notificationSent,
                'notification_channel' => implode(',', $channels),
                'message' => $alertMessage,
                'severity' => $rule->severity ?? 'warning',
                'category' => $rule->category ?? 'resource',
                'status' => 'triggered',
                'triggered_at' => now(),
            ]);

            $rule->update(['last_triggered_at' => now()]);
        } catch (QueryException $e) {
            Log::warning('MySQL unavailable, skipping alert history/timestamp update', [
                'rule' => $rule->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification through a specific channel.
     */
    private function sendNotification(string $channel, AlertRule $rule, float $value, string $message): void
    {
        match ($channel) {
            'email' => $this->sendEmail($rule, $value, $message),
            'telegram' => $this->sendTelegram($message),
            'slack' => $this->sendSlack($message),
            'discord' => $this->sendDiscord($message),
            default => Log::warning("Unknown notification channel: {$channel}"),
        };
    }

    private function sendEmail(AlertRule $rule, float $value, string $message): void
    {
        $to = config('monitoring.alert_email', config('mail.from.address'));

        if (! $to) {
            return;
        }

        $severity = strtoupper($rule->severity ?? 'WARNING');

        Mail::raw($message, function ($mail) use ($to, $rule, $severity) {
            $mail->to($to)->subject("[VSISPanel {$severity}] {$rule->name}");
        });
    }

    private function sendTelegram(string $message): void
    {
        $botToken = config('monitoring.telegram_bot_token');
        $chatId = config('monitoring.telegram_chat_id');

        if (! $botToken || ! $chatId) {
            return;
        }

        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
    }

    private function sendSlack(string $message): void
    {
        $webhookUrl = config('monitoring.slack_webhook_url');

        if (! $webhookUrl) {
            return;
        }

        Http::post($webhookUrl, ['text' => $message]);
    }

    private function sendDiscord(string $message): void
    {
        $webhookUrl = config('monitoring.discord_webhook_url');

        if (! $webhookUrl) {
            return;
        }

        Http::post($webhookUrl, ['content' => $message]);
    }

    /**
     * Send a test notification to verify channel configuration.
     */
    public function sendTestNotification(string $channel): bool
    {
        $message = '[VSISPanel] Test alert notification - ' . now()->format('Y-m-d H:i:s');

        try {
            $this->sendNotification($channel, new AlertRule([
                'name' => 'Test Alert',
                'metric' => 'cpu',
                'condition' => 'above',
                'threshold' => 0,
                'severity' => 'info',
            ]), 0, $message);

            return true;
        } catch (\Exception $e) {
            Log::error("Test notification failed: " . $e->getMessage());
            return false;
        }
    }
}
