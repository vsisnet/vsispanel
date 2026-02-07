<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Firewall\Services\Fail2BanService;
use App\Modules\Security\Http\Resources\AuditLogResource;
use App\Modules\Security\Models\AuditLog;
use App\Modules\Security\Services\AuditLogService;
use App\Modules\Security\Services\SecurityScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function __construct(
        private readonly SecurityScoreService $scoreService,
        private readonly AuditLogService $auditService,
        private readonly Fail2BanService $fail2BanService
    ) {}

    /**
     * Get security dashboard overview
     */
    public function overview(): JsonResponse
    {
        $score = $this->scoreService->calculateScore();
        $stats = $this->auditService->getActivityStats(30);
        $recentEvents = $this->auditService->getSecurityEvents(10);

        return response()->json([
            'success' => true,
            'data' => [
                'score' => $score,
                'activity_stats' => $stats,
                'recent_events' => AuditLogResource::collection($recentEvents),
            ],
        ]);
    }

    /**
     * Get detailed security score
     */
    public function score(): JsonResponse
    {
        $score = $this->scoreService->calculateScore();

        return response()->json([
            'success' => true,
            'data' => $score,
        ]);
    }

    /**
     * Recalculate security score
     */
    public function recalculateScore(): JsonResponse
    {
        $score = $this->scoreService->recalculateScore();

        return response()->json([
            'success' => true,
            'data' => $score,
            'message' => __('security.score_recalculated'),
        ]);
    }

    /**
     * Get audit logs
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with('user')
            ->orderBy('created_at', 'desc');

        // Filter by module
        if ($request->has('module')) {
            $query->where('module', $request->input('module'));
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by date range
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        // Security events only
        if ($request->boolean('security_only')) {
            $query->securityEvents();
        }

        $logs = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get activity statistics
     */
    public function activityStats(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        $stats = $this->auditService->getActivityStats((int) $days);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get failed login attempts
     */
    public function failedLogins(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);
        $attempts = $this->auditService->getFailedLoginAttempts((int) $days);

        return response()->json([
            'success' => true,
            'data' => AuditLogResource::collection($attempts),
        ]);
    }

    /**
     * Get available modules for filtering
     */
    public function getModules(): JsonResponse
    {
        $modules = AuditLog::distinct()
            ->pluck('module')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }

    /**
     * Get available actions for filtering
     */
    public function getActions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AuditLog::getActions(),
        ]);
    }

    // ==================== Fail2Ban Methods ====================

    /**
     * Get Fail2Ban status
     */
    public function fail2banStatus(): JsonResponse
    {
        $isInstalled = $this->fail2BanService->isInstalled();

        if (!$isInstalled) {
            return response()->json([
                'success' => true,
                'data' => [
                    'installed' => false,
                    'running' => false,
                ],
            ]);
        }

        $status = $this->fail2BanService->getStatus();

        return response()->json([
            'success' => true,
            'data' => [
                'installed' => true,
                'running' => $status['running'] ?? false,
                'jail_count' => $status['jail_count'] ?? 0,
                'jails' => $status['jails'] ?? [],
            ],
        ]);
    }

    /**
     * Install Fail2Ban
     */
    public function installFail2ban(): JsonResponse
    {
        if ($this->fail2BanService->isInstalled()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ALREADY_INSTALLED',
                    'message' => __('security.fail2ban_already_installed'),
                ],
            ], 400);
        }

        $result = $this->fail2BanService->install();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSTALL_FAILED',
                    'message' => $result['error'] ?? __('security.fail2ban_install_failed'),
                ],
            ], 500);
        }

        $this->auditService->log(
            AuditLog::ACTION_CREATE,
            'security',
            'fail2ban',
            null,
            'Fail2Ban installed'
        );

        return response()->json([
            'success' => true,
            'message' => __('security.fail2ban_installed'),
        ]);
    }

    /**
     * Get Fail2Ban jails
     */
    public function fail2banJails(): JsonResponse
    {
        if (!$this->fail2BanService->isInstalled()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_INSTALLED',
                    'message' => __('security.fail2ban_not_installed'),
                ],
            ], 400);
        }

        $jails = $this->fail2BanService->getJails();

        return response()->json([
            'success' => true,
            'data' => $jails,
        ]);
    }

    /**
     * Get specific jail status
     */
    public function fail2banJailStatus(string $jail): JsonResponse
    {
        $status = $this->fail2BanService->getJailStatus($jail);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Update jail configuration
     */
    public function updateJailConfig(Request $request, string $jail): JsonResponse
    {
        $validated = $request->validate([
            'bantime' => 'sometimes|integer|min:60|max:604800',
            'findtime' => 'sometimes|integer|min:60|max:604800',
            'maxretry' => 'sometimes|integer|min:1|max:100',
        ]);

        $this->fail2BanService->setJailConfig($jail, $validated);

        $this->auditService->log(
            AuditLog::ACTION_UPDATE,
            'security',
            'fail2ban_jail',
            $jail,
            "Updated jail configuration: {$jail}",
            null,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => __('security.jail_config_updated'),
        ]);
    }

    /**
     * Get banned IPs with pagination
     */
    public function fail2banBannedIps(Request $request): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $result = $this->fail2BanService->getBannedIpsPaginated($page, $perPage);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * Ban an IP
     */
    public function fail2banBanIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|ip',
            'jail' => 'required|string',
        ]);

        $result = $this->fail2BanService->banIp($validated['ip'], $validated['jail']);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BAN_FAILED',
                    'message' => $result['error'] ?? __('security.ban_failed'),
                ],
            ], 500);
        }

        $this->auditService->logBanIp($validated['ip'], $validated['jail']);

        return response()->json([
            'success' => true,
            'message' => __('security.ip_banned'),
        ]);
    }

    /**
     * Unban an IP
     */
    public function fail2banUnbanIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|ip',
            'jail' => 'nullable|string',
        ]);

        $result = $this->fail2BanService->unbanIp($validated['ip'], $validated['jail'] ?? null);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNBAN_FAILED',
                    'message' => $result['error'] ?? __('security.unban_failed'),
                ],
            ], 500);
        }

        $this->auditService->logUnbanIp($validated['ip'], $validated['jail'] ?? null);

        return response()->json([
            'success' => true,
            'message' => __('security.ip_unbanned'),
        ]);
    }

    /**
     * Enable a jail
     */
    public function fail2banEnableJail(string $jail): JsonResponse
    {
        $result = $this->fail2BanService->enableJail($jail);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ENABLE_FAILED',
                    'message' => $result['error'] ?? __('security.jail_enable_failed'),
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('security.jail_enabled'),
        ]);
    }

    /**
     * Disable a jail
     */
    public function fail2banDisableJail(string $jail): JsonResponse
    {
        $result = $this->fail2BanService->disableJail($jail);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DISABLE_FAILED',
                    'message' => $result['error'] ?? __('security.jail_disable_failed'),
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('security.jail_disabled'),
        ]);
    }

    /**
     * Restart Fail2Ban
     */
    public function fail2banRestart(): JsonResponse
    {
        $result = $this->fail2BanService->restart();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RESTART_FAILED',
                    'message' => $result['error'] ?? __('security.fail2ban_restart_failed'),
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('security.fail2ban_restarted'),
        ]);
    }

    /**
     * Start Fail2Ban
     */
    public function fail2banStart(): JsonResponse
    {
        $result = $this->fail2BanService->start();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? __('security.fail2ban_started') : ($result['error'] ?? __('security.fail2ban_start_failed')),
        ]);
    }

    /**
     * Stop Fail2Ban
     */
    public function fail2banStop(): JsonResponse
    {
        $result = $this->fail2BanService->stop();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] ? __('security.fail2ban_stopped') : ($result['error'] ?? __('security.fail2ban_stop_failed')),
        ]);
    }

    /**
     * Get whitelisted IPs
     */
    public function fail2banWhitelist(): JsonResponse
    {
        $whitelist = $this->fail2BanService->getWhitelist();

        return response()->json([
            'success' => true,
            'data' => $whitelist,
        ]);
    }

    /**
     * Add IP to whitelist
     */
    public function fail2banAddToWhitelist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|string',
        ]);

        // Validate IP or CIDR
        $ip = $validated['ip'];
        if (!filter_var($ip, FILTER_VALIDATE_IP) && !preg_match('/^[\d.]+\/\d+$/', $ip) && !preg_match('/^[a-f\d:]+\/\d+$/i', $ip)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_IP',
                    'message' => __('security.invalid_ip_format'),
                ],
            ], 422);
        }

        $result = $this->fail2BanService->addToWhitelist($ip);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WHITELIST_FAILED',
                    'message' => $result['error'] ?? __('security.whitelist_add_failed'),
                ],
            ], 500);
        }

        $this->auditService->log(
            AuditLog::ACTION_CREATE,
            'security',
            'fail2ban_whitelist',
            $ip,
            "Added IP to whitelist: {$ip}"
        );

        return response()->json([
            'success' => true,
            'message' => __('security.ip_whitelisted'),
        ]);
    }

    /**
     * Remove IP from whitelist
     */
    public function fail2banRemoveFromWhitelist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|string',
        ]);

        $result = $this->fail2BanService->removeFromWhitelist($validated['ip']);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WHITELIST_REMOVE_FAILED',
                    'message' => $result['error'] ?? __('security.whitelist_remove_failed'),
                ],
            ], 500);
        }

        $this->auditService->log(
            AuditLog::ACTION_DELETE,
            'security',
            'fail2ban_whitelist',
            $validated['ip'],
            "Removed IP from whitelist: {$validated['ip']}"
        );

        return response()->json([
            'success' => true,
            'message' => __('security.ip_removed_from_whitelist'),
        ]);
    }

    /**
     * Get available jails (including disabled)
     */
    public function fail2banAvailableJails(): JsonResponse
    {
        $availableJails = $this->fail2BanService->getAvailableJails();
        $activeJails = $this->fail2BanService->getJails();
        $activeJailNames = array_column($activeJails, 'name');

        // Merge available with active status
        foreach ($availableJails as &$jail) {
            $jail['enabled'] = in_array($jail['name'], $activeJailNames);
        }

        return response()->json([
            'success' => true,
            'data' => $availableJails,
        ]);
    }

    /**
     * Create a new custom jail
     */
    public function fail2banCreateJail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/',
            'port' => 'required|string',
            'filter' => 'nullable|string',
            'logpath' => 'required|string',
            'maxretry' => 'sometimes|integer|min:1|max:100',
            'findtime' => 'sometimes|integer|min:60|max:604800',
            'bantime' => 'sometimes|integer|min:60|max:604800',
            'enabled' => 'sometimes|boolean',
        ]);

        $result = $this->fail2BanService->createCustomJail($validated['name'], $validated);

        if (!$result) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CREATE_JAIL_FAILED',
                    'message' => __('security.jail_create_failed'),
                ],
            ], 500);
        }

        $this->auditService->log(
            AuditLog::ACTION_CREATE,
            'security',
            'fail2ban_jail',
            $validated['name'],
            "Created custom jail: {$validated['name']}",
            null,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => __('security.jail_created'),
        ]);
    }

    /**
     * Delete a custom jail
     */
    public function fail2banDeleteJail(string $jail): JsonResponse
    {
        $result = $this->fail2BanService->deleteJail($jail);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETE_JAIL_FAILED',
                    'message' => $result['error'] ?? __('security.jail_delete_failed'),
                ],
            ], 500);
        }

        $this->auditService->log(
            AuditLog::ACTION_DELETE,
            'security',
            'fail2ban_jail',
            $jail,
            "Deleted jail: {$jail}"
        );

        return response()->json([
            'success' => true,
            'message' => __('security.jail_deleted'),
        ]);
    }
}
