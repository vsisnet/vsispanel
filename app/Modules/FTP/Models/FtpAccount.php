<?php

declare(strict_types=1);

namespace App\Modules\FTP\Models;

use App\Modules\Domain\Models\Domain;
use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class FtpAccount extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'domain_id',
        'user_id',
        'username',
        'password',
        'home_directory',
        'status',
        'quota_mb',
        'bandwidth_mb',
        'upload_bandwidth_kbps',
        'download_bandwidth_kbps',
        'max_connections',
        'max_connections_per_ip',
        'allowed_ips',
        'denied_ips',
        'allow_upload',
        'allow_download',
        'allow_mkdir',
        'allow_delete',
        'allow_rename',
        'last_login_at',
        'last_login_ip',
        'total_uploaded_bytes',
        'total_downloaded_bytes',
        'description',
        'expires_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'quota_mb' => 'integer',
        'bandwidth_mb' => 'integer',
        'upload_bandwidth_kbps' => 'integer',
        'download_bandwidth_kbps' => 'integer',
        'max_connections' => 'integer',
        'max_connections_per_ip' => 'integer',
        'allowed_ips' => 'array',
        'denied_ips' => 'array',
        'allow_upload' => 'boolean',
        'allow_download' => 'boolean',
        'allow_mkdir' => 'boolean',
        'allow_delete' => 'boolean',
        'allow_rename' => 'boolean',
        'last_login_at' => 'datetime',
        'total_uploaded_bytes' => 'integer',
        'total_downloaded_bytes' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_DISABLED = 'disabled';

    /**
     * Get available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_SUSPENDED,
            self::STATUS_DISABLED,
        ];
    }

    /**
     * Domain relationship
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Set the password attribute (auto-hash)
     */
    public function setPasswordAttribute(string $value): void
    {
        // Only hash if not already hashed
        if (!str_starts_with($value, '$2y$') && !str_starts_with($value, '$argon2')) {
            $value = Hash::make($value);
        }
        $this->attributes['password'] = $value;
    }

    /**
     * Check if account is active
     */
    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if account is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if account has quota exceeded
     */
    public function isQuotaExceeded(): bool
    {
        if (!$this->quota_mb) {
            return false;
        }

        $usedMb = $this->getUsedQuotaMb();
        return $usedMb >= $this->quota_mb;
    }

    /**
     * Get used quota in MB
     */
    public function getUsedQuotaMb(): float
    {
        // This would be calculated from the actual directory size
        // For now, return 0
        return 0;
    }

    /**
     * Get formatted quota usage
     */
    public function getQuotaUsageAttribute(): array
    {
        $usedMb = $this->getUsedQuotaMb();
        $limitMb = $this->quota_mb ?? 0;

        return [
            'used_mb' => $usedMb,
            'limit_mb' => $limitMb,
            'percent' => $limitMb > 0 ? round(($usedMb / $limitMb) * 100, 2) : 0,
            'unlimited' => $limitMb === 0,
        ];
    }

    /**
     * Get formatted total uploaded bytes
     */
    public function getFormattedUploadedAttribute(): string
    {
        return $this->formatBytes($this->total_uploaded_bytes ?? 0);
    }

    /**
     * Get formatted total downloaded bytes
     */
    public function getFormattedDownloadedAttribute(): string
    {
        return $this->formatBytes($this->total_downloaded_bytes ?? 0);
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(?int $bytes): string
    {
        $bytes = $bytes ?? 0;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Check if IP is allowed to connect
     */
    public function isIpAllowed(string $ip): bool
    {
        // Check denied IPs first
        if ($this->denied_ips && in_array($ip, $this->denied_ips)) {
            return false;
        }

        // If allowed IPs is set, check if IP is in the list
        if ($this->allowed_ips && count($this->allowed_ips) > 0) {
            return in_array($ip, $this->allowed_ips);
        }

        return true;
    }

    /**
     * Record login activity
     */
    public function recordLogin(string $ip): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    /**
     * Record transfer activity
     */
    public function recordTransfer(int $uploadedBytes = 0, int $downloadedBytes = 0): void
    {
        $this->increment('total_uploaded_bytes', $uploadedBytes);
        $this->increment('total_downloaded_bytes', $downloadedBytes);
    }

    /**
     * Suspend account
     */
    public function suspend(): bool
    {
        return $this->update(['status' => self::STATUS_SUSPENDED]);
    }

    /**
     * Activate account
     */
    public function activate(): bool
    {
        return $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Disable account
     */
    public function disable(): bool
    {
        return $this->update(['status' => self::STATUS_DISABLED]);
    }

    /**
     * Scope: Active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope: By domain
     */
    public function scopeForDomain($query, string $domainId)
    {
        return $query->where('domain_id', $domainId);
    }

    /**
     * Scope: By user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Generate ProFTPD config entry for this account
     */
    public function toProftpdConfig(): string
    {
        $config = [];
        $config[] = "# FTP Account: {$this->username}";
        $config[] = "<Directory {$this->home_directory}>";
        $config[] = "  <Limit ALL>";
        $config[] = "    AllowUser {$this->username}";
        $config[] = "  </Limit>";

        if (!$this->allow_upload) {
            $config[] = "  <Limit WRITE>";
            $config[] = "    DenyAll";
            $config[] = "  </Limit>";
        }

        if (!$this->allow_download) {
            $config[] = "  <Limit READ>";
            $config[] = "    DenyAll";
            $config[] = "  </Limit>";
        }

        if (!$this->allow_mkdir) {
            $config[] = "  <Limit MKD XMKD>";
            $config[] = "    DenyAll";
            $config[] = "  </Limit>";
        }

        if (!$this->allow_delete) {
            $config[] = "  <Limit DELE RMD XRMD>";
            $config[] = "    DenyAll";
            $config[] = "  </Limit>";
        }

        if (!$this->allow_rename) {
            $config[] = "  <Limit RNFR RNTO>";
            $config[] = "    DenyAll";
            $config[] = "  </Limit>";
        }

        $config[] = "</Directory>";

        return implode("\n", $config);
    }

    /**
     * Generate Pure-FTPd virtual user entry
     */
    public function toPureFtpdUser(): array
    {
        return [
            'username' => $this->username,
            'home' => $this->home_directory,
            'uid' => 33, // www-data
            'gid' => 33, // www-data
            'gecos' => $this->description ?? "FTP account for {$this->username}",
            'upload_ratio' => $this->upload_bandwidth_kbps ?? 0,
            'download_ratio' => $this->download_bandwidth_kbps ?? 0,
            'max_files' => 0,
            'quota_files' => 0,
            'quota_mb' => $this->quota_mb ?? 0,
            'bandwidth' => $this->bandwidth_mb ?? 0,
        ];
    }
}
