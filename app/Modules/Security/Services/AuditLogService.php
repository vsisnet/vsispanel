<?php

declare(strict_types=1);

namespace App\Modules\Security\Services;

use App\Modules\Security\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log an action
     */
    public function log(
        string $action,
        string $module,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'module' => $module,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Log a create action
     */
    public function logCreate(string $module, string $resourceType, string $resourceId, ?string $description = null, ?array $newValues = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_CREATE,
            $module,
            $resourceType,
            $resourceId,
            $description ?? "Created {$resourceType}",
            null,
            $newValues
        );
    }

    /**
     * Log an update action
     */
    public function logUpdate(string $module, string $resourceType, string $resourceId, ?string $description = null, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_UPDATE,
            $module,
            $resourceType,
            $resourceId,
            $description ?? "Updated {$resourceType}",
            $oldValues,
            $newValues
        );
    }

    /**
     * Log a delete action
     */
    public function logDelete(string $module, string $resourceType, string $resourceId, ?string $description = null, ?array $oldValues = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_DELETE,
            $module,
            $resourceType,
            $resourceId,
            $description ?? "Deleted {$resourceType}",
            $oldValues
        );
    }

    /**
     * Log a login
     */
    public function logLogin(?string $userId = null, bool $success = true): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $success ? AuditLog::ACTION_LOGIN : AuditLog::ACTION_LOGIN_FAILED,
            'module' => 'auth',
            'description' => $success ? 'User logged in' : 'Login attempt failed',
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Log a logout
     */
    public function logLogout(): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_LOGOUT,
            'auth',
            null,
            null,
            'User logged out'
        );
    }

    /**
     * Log a service action
     */
    public function logServiceAction(string $action, string $service, ?string $description = null): AuditLog
    {
        return $this->log(
            $action,
            'server',
            'service',
            $service,
            $description ?? "{$action} service: {$service}"
        );
    }

    /**
     * Log a firewall change
     */
    public function logFirewallChange(string $description, ?array $details = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_FIREWALL_CHANGE,
            'firewall',
            null,
            null,
            $description,
            null,
            null,
            $details
        );
    }

    /**
     * Log an IP ban
     */
    public function logBanIp(string $ip, ?string $jail = null, ?string $reason = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_BAN_IP,
            'security',
            'ip',
            $ip,
            "Banned IP: {$ip}",
            null,
            null,
            ['jail' => $jail, 'reason' => $reason]
        );
    }

    /**
     * Log an IP unban
     */
    public function logUnbanIp(string $ip, ?string $jail = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_UNBAN_IP,
            'security',
            'ip',
            $ip,
            "Unbanned IP: {$ip}",
            null,
            null,
            ['jail' => $jail]
        );
    }

    /**
     * Get recent logs
     */
    public function getRecentLogs(int $limit = 50, ?string $module = null, ?string $action = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = AuditLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($module) {
            $query->where('module', $module);
        }

        if ($action) {
            $query->where('action', $action);
        }

        return $query->get();
    }

    /**
     * Get logs for a specific user
     */
    public function getUserLogs(string $userId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get security events
     */
    public function getSecurityEvents(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::securityEvents()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get failed login attempts
     */
    public function getFailedLoginAttempts(int $days = 7): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('action', AuditLog::ACTION_LOGIN_FAILED)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get activity statistics
     */
    public function getActivityStats(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $totalLogs = AuditLog::where('created_at', '>=', $startDate)->count();
        $loginAttempts = AuditLog::where('created_at', '>=', $startDate)
            ->whereIn('action', [AuditLog::ACTION_LOGIN, AuditLog::ACTION_LOGIN_FAILED])
            ->count();
        $failedLogins = AuditLog::where('created_at', '>=', $startDate)
            ->where('action', AuditLog::ACTION_LOGIN_FAILED)
            ->count();
        $securityEvents = AuditLog::securityEvents()
            ->where('created_at', '>=', $startDate)
            ->count();

        return [
            'total_activities' => $totalLogs,
            'login_attempts' => $loginAttempts,
            'failed_logins' => $failedLogins,
            'security_events' => $securityEvents,
            'period_days' => $days,
        ];
    }

    /**
     * Cleanup old logs
     */
    public function cleanup(int $retainDays = 90): int
    {
        return AuditLog::where('created_at', '<', now()->subDays($retainDays))->delete();
    }
}
