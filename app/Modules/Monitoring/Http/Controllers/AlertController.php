<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Models\AlertHistory;
use App\Modules\Monitoring\Models\AlertRule;
use App\Modules\Monitoring\Models\AlertTemplate;
use App\Modules\Monitoring\Services\AlertEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AlertController extends Controller
{
    private array $validationRules = [
        'name' => 'required|string|max:255',
        'category' => 'required|string|in:resource,service,security,backup,ssl',
        'severity' => 'required|string|in:info,warning,critical',
        'metric' => 'required|string|in:cpu,memory,disk,network,service_down,ssl_expiry,backup_failed,ssh_brute_force,panel_intrusion',
        'condition' => 'required|string|in:above,below,equals',
        'threshold' => 'required|numeric|min:0',
        'duration_seconds' => 'integer|min:0|max:3600',
        'notification_channels' => 'array',
        'notification_channels.*' => 'string|in:email,telegram,slack,discord',
        'config' => 'nullable|array',
        'config.service_name' => 'required_if:metric,service_down|nullable|string',
        'config.days_before' => 'nullable|integer|min:1|max:365',
        'is_active' => 'boolean',
        'cooldown_minutes' => 'integer|min:1|max:1440',
    ];

    /**
     * GET /api/v1/monitoring/alerts - List alert rules.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AlertRule::withCount('history')->orderBy('created_at', 'desc');

        if ($request->has('category')) {
            $query->byCategory($request->category);
        }
        if ($request->has('severity')) {
            $query->bySeverity($request->severity);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * GET /api/v1/monitoring/alerts/summary - Dashboard summary stats.
     */
    public function summary(): JsonResponse
    {
        $totalRules = AlertRule::count();
        $activeRules = AlertRule::where('is_active', true)->count();
        $unresolvedAlerts = AlertHistory::unresolved()->count();
        $criticalUnresolved = AlertHistory::unresolved()->where('severity', 'critical')->count();
        $last24h = AlertHistory::where('triggered_at', '>=', now()->subDay())->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_rules' => $totalRules,
                'active_rules' => $activeRules,
                'unresolved_alerts' => $unresolvedAlerts,
                'critical_unresolved' => $criticalUnresolved,
                'last_24h' => $last24h,
            ],
        ]);
    }

    /**
     * GET /api/v1/monitoring/alerts/templates - List alert templates.
     */
    public function templates(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AlertTemplate::orderBy('category')->orderBy('severity')->get(),
        ]);
    }

    /**
     * POST /api/v1/monitoring/alerts/from-template/{template} - Create rule from template.
     */
    public function createFromTemplate(Request $request, AlertTemplate $template): JsonResponse
    {
        $rule = AlertRule::create([
            'name' => $request->input('name', $template->name),
            'category' => $template->category,
            'severity' => $template->severity,
            'metric' => $template->metric,
            'condition' => $template->condition,
            'threshold' => $template->threshold,
            'config' => $template->config,
            'cooldown_minutes' => $template->cooldown_minutes,
            'notification_channels' => $request->input('notification_channels', ['email']),
            'is_active' => true,
        ]);

        $this->refreshAlertRulesCache();

        return response()->json([
            'success' => true,
            'data' => $rule,
            'message' => 'Alert rule created from template',
        ], 201);
    }

    /**
     * POST /api/v1/monitoring/alerts - Create alert rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->validationRules);

        $rule = AlertRule::create($validated);

        $this->refreshAlertRulesCache();

        return response()->json([
            'success' => true,
            'data' => $rule,
            'message' => 'Alert rule created',
        ], 201);
    }

    /**
     * GET /api/v1/monitoring/alerts/{rule} - Show alert rule.
     */
    public function show(AlertRule $alert): JsonResponse
    {
        $alert->load('history');

        return response()->json([
            'success' => true,
            'data' => $alert,
        ]);
    }

    /**
     * PUT /api/v1/monitoring/alerts/{rule} - Update alert rule.
     */
    public function update(Request $request, AlertRule $alert): JsonResponse
    {
        $rules = collect($this->validationRules)
            ->mapWithKeys(fn ($rule, $key) => [
                $key => preg_replace('/\brequired\b(?!_)/', 'sometimes', $rule),
            ])
            ->toArray();

        $validated = $request->validate($rules);

        $alert->update($validated);

        $this->refreshAlertRulesCache();

        return response()->json([
            'success' => true,
            'data' => $alert->fresh(),
            'message' => 'Alert rule updated',
        ]);
    }

    /**
     * DELETE /api/v1/monitoring/alerts/{rule} - Delete alert rule.
     */
    public function destroy(AlertRule $alert): JsonResponse
    {
        $alert->delete();

        $this->refreshAlertRulesCache();

        return response()->json([
            'success' => true,
            'message' => 'Alert rule deleted',
        ]);
    }

    /**
     * POST /api/v1/monitoring/alerts/{rule}/toggle - Toggle alert active state.
     */
    public function toggle(AlertRule $alert): JsonResponse
    {
        $alert->update(['is_active' => ! $alert->is_active]);

        $this->refreshAlertRulesCache();

        return response()->json([
            'success' => true,
            'data' => $alert->fresh(),
            'message' => $alert->is_active ? 'Alert enabled' : 'Alert disabled',
        ]);
    }

    /**
     * GET /api/v1/monitoring/alerts/history - Alert history.
     */
    public function history(Request $request): JsonResponse
    {
        $query = AlertHistory::with('rule')->orderBy('triggered_at', 'desc');

        if ($request->has('category')) {
            $query->byCategory($request->category);
        }
        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->limit(100)->get(),
        ]);
    }

    /**
     * POST /api/v1/monitoring/alerts/history/{history}/acknowledge
     */
    public function acknowledge(AlertHistory $history): JsonResponse
    {
        $history->update(['status' => 'acknowledged']);

        return response()->json([
            'success' => true,
            'data' => $history->fresh(),
            'message' => 'Alert acknowledged',
        ]);
    }

    /**
     * POST /api/v1/monitoring/alerts/history/{history}/resolve
     */
    public function resolve(AlertHistory $history): JsonResponse
    {
        $history->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $history->fresh(),
            'message' => 'Alert resolved',
        ]);
    }

    /**
     * POST /api/v1/monitoring/alerts/test - Test notification channel.
     */
    public function test(Request $request, AlertEvaluator $evaluator): JsonResponse
    {
        $request->validate([
            'channel' => 'required|string|in:email,telegram,slack,discord',
        ]);

        $success = $evaluator->sendTestNotification($request->channel);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Test notification sent' : 'Failed to send test notification',
        ]);
    }

    /**
     * Refresh alert rules cache in Redis for MySQL fallback.
     */
    private function refreshAlertRulesCache(): void
    {
        try {
            $rules = AlertRule::where('is_active', true)->get();
            Cache::store('redis')->put('vsispanel:alert_rules', json_encode(
                $rules->map(fn ($rule) => $rule->getAttributes())->toArray()
            ));
        } catch (\Exception $e) {
            Log::warning('Failed to refresh alert rules cache', ['error' => $e->getMessage()]);
        }
    }
}
