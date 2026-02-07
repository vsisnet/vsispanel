<?php

declare(strict_types=1);

namespace App\Modules\Mail\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MailAccount extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'mail_domain_id',
        'user_id',
        'email',
        'username',
        'password_hash',
        'quota_mb',
        'quota_used_bytes',
        'status',
        'auto_responder_enabled',
        'auto_responder_subject',
        'auto_responder_message',
        'auto_responder_start_at',
        'auto_responder_end_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $casts = [
        'quota_mb' => 'integer',
        'quota_used_bytes' => 'integer',
        'auto_responder_enabled' => 'boolean',
        'auto_responder_start_at' => 'datetime',
        'auto_responder_end_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the mail domain this account belongs to.
     */
    public function mailDomain(): BelongsTo
    {
        return $this->belongsTo(MailDomain::class);
    }

    /**
     * Get the user who owns this mail account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the forwards for this account.
     */
    public function forwards(): HasMany
    {
        return $this->hasMany(MailForward::class);
    }

    /**
     * Get quota usage percentage.
     */
    public function getQuotaUsagePercentAttribute(): float
    {
        $quotaBytes = $this->quota_mb * 1024 * 1024;
        if ($quotaBytes <= 0) {
            return 0;
        }
        return round(($this->quota_used_bytes / $quotaBytes) * 100, 2);
    }

    /**
     * Get quota used in MB.
     */
    public function getQuotaUsedMbAttribute(): float
    {
        return round($this->quota_used_bytes / (1024 * 1024), 2);
    }

    /**
     * Get quota limit in bytes.
     */
    public function getQuotaLimitBytesAttribute(): int
    {
        return $this->quota_mb * 1024 * 1024;
    }

    /**
     * Check if account is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if account is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if quota is exceeded.
     */
    public function isQuotaExceeded(): bool
    {
        return $this->quota_used_bytes >= $this->quota_limit_bytes;
    }

    /**
     * Check if auto-responder is active now.
     */
    public function isAutoResponderActive(): bool
    {
        if (!$this->auto_responder_enabled) {
            return false;
        }

        $now = now();

        if ($this->auto_responder_start_at && $now < $this->auto_responder_start_at) {
            return false;
        }

        if ($this->auto_responder_end_at && $now > $this->auto_responder_end_at) {
            return false;
        }

        return true;
    }

    /**
     * Update quota used.
     */
    public function updateQuotaUsed(int $bytes): void
    {
        $this->update(['quota_used_bytes' => $bytes]);
    }

    /**
     * Record login.
     */
    public function recordLogin(?string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    /**
     * Scope to get active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get suspended accounts.
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    /**
     * Scope to filter by domain.
     */
    public function scopeForDomain($query, string $domainId)
    {
        return $query->where('mail_domain_id', $domainId);
    }

    /**
     * Scope to search by email.
     */
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where('email', 'like', "%{$search}%");
    }
}
