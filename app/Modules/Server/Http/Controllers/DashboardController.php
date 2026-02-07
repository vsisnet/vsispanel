<?php

declare(strict_types=1);

namespace App\Modules\Server\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Server\Services\SystemInfoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function __construct(
        protected SystemInfoService $systemInfoService
    ) {}

    /**
     * Get dashboard statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get counts based on user role
        $stats = [
            'websites' => [
                'count' => 0, // Will be populated when Domain module is ready
                'label' => 'Websites',
            ],
            'databases' => [
                'count' => 0, // Will be populated when Database module is ready
                'label' => 'Databases',
            ],
            'email_accounts' => [
                'count' => 0, // Will be populated when Mail module is ready
                'label' => 'Email Accounts',
            ],
            'domains' => [
                'count' => 0, // Will be populated when Domain module is ready
                'label' => 'Domains',
            ],
        ];

        // Admin sees all, others see their own
        if ($user->isAdmin()) {
            $stats['users'] = [
                'count' => \App\Modules\Auth\Models\User::count(),
                'label' => 'Users',
            ];
        }

        // Disk usage
        $diskUsage = $this->systemInfoService->getDiskUsage();
        $stats['disk'] = [
            'used' => $diskUsage[0]['used'] ?? '0 GB',
            'total' => $diskUsage[0]['total'] ?? '0 GB',
            'percentage' => $diskUsage[0]['percentage'] ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get system metrics for charts
     */
    public function metrics(Request $request): JsonResponse
    {
        $cpu = $this->systemInfoService->getCpuUsage();
        $memory = $this->systemInfoService->getMemoryUsage();
        $disk = $this->systemInfoService->getDiskUsage();

        return response()->json([
            'success' => true,
            'data' => [
                'cpu' => $cpu,
                'memory' => $memory,
                'disk' => $disk,
            ],
        ]);
    }

    /**
     * Get recent activity
     */
    public function activity(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Activity::query()
            ->with('causer:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit(10);

        // Non-admin users only see their own activity
        if (!$user->isAdmin()) {
            $query->where('causer_id', $user->id);
        }

        $activities = $query->get()->map(function ($activity) {
            return [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject_type' => class_basename($activity->subject_type ?? ''),
                'causer' => $activity->causer ? [
                    'id' => $activity->causer->id,
                    'name' => $activity->causer->name,
                ] : null,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at->toIso8601String(),
                'time_ago' => $activity->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get system information
     */
    public function systemInfo(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only admin can see full system info
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'php_version' => PHP_VERSION,
                    'server_time' => now()->format('Y-m-d H:i:s'),
                ],
            ]);
        }

        $data = Cache::remember('vsispanel:system_info', 30, function () {
            return [
                'system' => $this->systemInfoService->getSystemInfo(),
                'services' => $this->systemInfoService->getServicesStatus(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get real-time metrics (for polling/websocket)
     */
    public function realtime(Request $request): JsonResponse
    {
        $data = Cache::remember('vsispanel:metrics:realtime', 15, function () {
            $cpu = $this->systemInfoService->getCpuUsage();
            $memory = $this->systemInfoService->getMemoryUsage();

            return [
                'cpu_percentage' => $cpu['percentage'],
                'cpu_load' => $cpu['load_1min'],
                'memory_percentage' => $memory['percentage'],
                'memory_used' => $memory['used'],
                'timestamp' => now()->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
