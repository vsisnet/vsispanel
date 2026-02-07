<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Models\ServerMetric;
use App\Modules\Monitoring\Services\MetricsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class MonitoringController extends Controller
{
    public function __construct(
        private MetricsCollector $collector,
    ) {}

    /**
     * GET /api/v1/monitoring/current - Current metrics snapshot.
     */
    public function current(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->collector->collectAll(),
        ]);
    }

    /**
     * GET /api/v1/monitoring/history - Historical metrics data.
     */
    public function history(Request $request): JsonResponse
    {
        $period = $request->get('period', '24h');

        $query = ServerMetric::query()->period($period)->orderBy('recorded_at');

        // Aggregate data for longer periods
        $metrics = match ($period) {
            '7d', '30d' => $this->aggregateMetrics($query, $period),
            default => $query->get(),
        };

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'metrics' => $metrics,
            ],
        ]);
    }

    /**
     * GET /api/v1/monitoring/services - All services status.
     */
    public function services(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->collector->collectServiceStatuses(),
        ]);
    }

    /**
     * POST /api/v1/monitoring/services/{service}/restart - Restart a service.
     */
    public function restartService(string $service): JsonResponse
    {
        $allowed = config('monitoring.services', []);

        if (!isset($allowed[$service])) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_SERVICE', 'message' => 'Service not found'],
            ], 404);
        }

        $systemName = $allowed[$service];
        $result = Process::timeout(30)->run("systemctl restart {$systemName}");

        if ($result->successful()) {
            return response()->json([
                'success' => true,
                'message' => "Service {$service} restarted successfully",
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'RESTART_FAILED',
                'message' => "Failed to restart {$service}: " . $result->errorOutput(),
            ],
        ], 500);
    }

    /**
     * POST /api/v1/monitoring/services/{service}/stop - Stop a service.
     */
    public function stopService(string $service): JsonResponse
    {
        $allowed = config('monitoring.services', []);

        if (!isset($allowed[$service])) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_SERVICE', 'message' => 'Service not found'],
            ], 404);
        }

        $systemName = $allowed[$service];
        $result = Process::timeout(30)->run("systemctl stop {$systemName}");

        if ($result->successful()) {
            return response()->json([
                'success' => true,
                'message' => "Service {$service} stopped",
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => ['code' => 'STOP_FAILED', 'message' => $result->errorOutput()],
        ], 500);
    }

    /**
     * POST /api/v1/monitoring/services/{service}/start - Start a service.
     */
    public function startService(string $service): JsonResponse
    {
        $allowed = config('monitoring.services', []);

        if (!isset($allowed[$service])) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_SERVICE', 'message' => 'Service not found'],
            ], 404);
        }

        $systemName = $allowed[$service];
        $result = Process::timeout(30)->run("systemctl start {$systemName}");

        if ($result->successful()) {
            return response()->json([
                'success' => true,
                'message' => "Service {$service} started",
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => ['code' => 'START_FAILED', 'message' => $result->errorOutput()],
        ], 500);
    }

    /**
     * GET /api/v1/monitoring/processes - Top processes.
     */
    public function processes(Request $request): JsonResponse
    {
        $sortBy = $request->get('sort', 'cpu');
        $limit = min((int) $request->get('limit', 20), 50);

        return response()->json([
            'success' => true,
            'data' => $this->collector->collectTopProcesses($sortBy, $limit),
        ]);
    }

    /**
     * POST /api/v1/monitoring/processes/{pid}/kill - Kill a process.
     */
    public function killProcess(int $pid): JsonResponse
    {
        if ($pid <= 1) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_PID', 'message' => 'Cannot kill system processes'],
            ], 400);
        }

        $result = Process::timeout(5)->run("kill -9 {$pid}");

        return response()->json([
            'success' => $result->successful(),
            'message' => $result->successful() ? "Process {$pid} killed" : "Failed to kill process {$pid}",
        ]);
    }

    /**
     * GET /api/v1/monitoring/services/{service}/logs - Get service logs.
     */
    public function serviceLogs(Request $request, string $service): JsonResponse
    {
        $allowed = config('monitoring.services', []);

        if (!isset($allowed[$service])) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_SERVICE', 'message' => 'Service not found'],
            ], 404);
        }

        $lines = min((int) $request->get('lines', 50), 200);
        $systemName = $allowed[$service];
        $output = shell_exec("journalctl -u {$systemName} --no-pager -n {$lines} 2>/dev/null") ?? '';

        return response()->json([
            'success' => true,
            'data' => [
                'service' => $service,
                'logs' => $output,
            ],
        ]);
    }

    /**
     * Aggregate metrics for longer periods.
     */
    private function aggregateMetrics($query, string $period): \Illuminate\Support\Collection
    {
        $interval = $period === '30d' ? 3600 : 900; // 1 hour for 30d, 15 min for 7d

        return $query->get()->groupBy(function ($item) use ($interval) {
            return (int) floor($item->recorded_at->timestamp / $interval) * $interval;
        })->map(function ($group) {
            return [
                'recorded_at' => $group->first()->recorded_at->format('Y-m-d H:i'),
                'cpu_usage' => round($group->avg('cpu_usage'), 1),
                'memory_used' => (int) $group->avg('memory_used'),
                'memory_total' => $group->first()->memory_total,
                'network_in' => (int) $group->avg('network_in'),
                'network_out' => (int) $group->avg('network_out'),
                'load_1m' => round($group->avg('load_1m'), 2),
            ];
        })->values();
    }
}
