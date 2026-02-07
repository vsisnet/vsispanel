<?php

declare(strict_types=1);

namespace App\Modules\Security\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'resource_type',
        'resource_id',
        'description',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // Action types
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LOGIN_FAILED = 'login_failed';
    public const ACTION_PASSWORD_RESET = 'password_reset';
    public const ACTION_2FA_ENABLED = '2fa_enabled';
    public const ACTION_2FA_DISABLED = '2fa_disabled';
    public const ACTION_PERMISSION_CHANGE = 'permission_change';
    public const ACTION_SETTINGS_CHANGE = 'settings_change';
    public const ACTION_BACKUP_CREATED = 'backup_created';
    public const ACTION_BACKUP_RESTORED = 'backup_restored';
    public const ACTION_SERVICE_START = 'service_start';
    public const ACTION_SERVICE_STOP = 'service_stop';
    public const ACTION_SERVICE_RESTART = 'service_restart';
    public const ACTION_FIREWALL_CHANGE = 'firewall_change';
    public const ACTION_BAN_IP = 'ban_ip';
    public const ACTION_UNBAN_IP = 'unban_ip';

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for recent logs
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for specific module
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope for specific action
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for specific user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for security-related events
     */
    public function scopeSecurityEvents($query)
    {
        return $query->whereIn('action', [
            self::ACTION_LOGIN,
            self::ACTION_LOGOUT,
            self::ACTION_LOGIN_FAILED,
            self::ACTION_PASSWORD_RESET,
            self::ACTION_2FA_ENABLED,
            self::ACTION_2FA_DISABLED,
            self::ACTION_PERMISSION_CHANGE,
            self::ACTION_FIREWALL_CHANGE,
            self::ACTION_BAN_IP,
            self::ACTION_UNBAN_IP,
        ]);
    }

    /**
     * Get available actions
     */
    public static function getActions(): array
    {
        return [
            self::ACTION_CREATE,
            self::ACTION_UPDATE,
            self::ACTION_DELETE,
            self::ACTION_LOGIN,
            self::ACTION_LOGOUT,
            self::ACTION_LOGIN_FAILED,
            self::ACTION_PASSWORD_RESET,
            self::ACTION_2FA_ENABLED,
            self::ACTION_2FA_DISABLED,
            self::ACTION_PERMISSION_CHANGE,
            self::ACTION_SETTINGS_CHANGE,
            self::ACTION_BACKUP_CREATED,
            self::ACTION_BACKUP_RESTORED,
            self::ACTION_SERVICE_START,
            self::ACTION_SERVICE_STOP,
            self::ACTION_SERVICE_RESTART,
            self::ACTION_FIREWALL_CHANGE,
            self::ACTION_BAN_IP,
            self::ACTION_UNBAN_IP,
        ];
    }

    /**
     * Get action severity
     */
    public function getSeverityAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_LOGIN_FAILED,
            self::ACTION_BAN_IP,
            self::ACTION_FIREWALL_CHANGE,
            self::ACTION_PERMISSION_CHANGE => 'warning',
            self::ACTION_DELETE,
            self::ACTION_2FA_DISABLED => 'danger',
            self::ACTION_LOGIN,
            self::ACTION_LOGOUT,
            self::ACTION_2FA_ENABLED,
            self::ACTION_BACKUP_CREATED => 'success',
            default => 'info',
        };
    }
}
