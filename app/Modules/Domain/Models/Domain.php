<?php

declare(strict_types=1);

namespace App\Modules\Domain\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Events\DomainCreated;
use App\Modules\Domain\Events\DomainDeleted;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Domain extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'name',
        'document_root',
        'php_version',
        'status',
        'ssl_enabled',
        'is_main',
        'web_server_type',
        'access_log',
        'error_log',
        'disk_used',
        'bandwidth_used',
        'ssl_expires_at',
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'is_main' => 'boolean',
        'disk_used' => 'integer',
        'bandwidth_used' => 'integer',
        'ssl_expires_at' => 'datetime',
    ];

    protected $dispatchesEvents = [
        'created' => DomainCreated::class,
        'deleted' => DomainDeleted::class,
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Hosting\Models\Subscription::class);
    }

    public function subdomains(): HasMany
    {
        return $this->hasMany(Subdomain::class);
    }

    public function sslCertificate(): HasOne
    {
        return $this->hasOne(\App\Modules\SSL\Models\SslCertificate::class);
    }

    public function dnsZone(): HasOne
    {
        return $this->hasOne(\App\Modules\DNS\Models\DnsZone::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(\App\Modules\Database\Models\ManagedDatabase::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 'suspended');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeWithSsl(Builder $query): Builder
    {
        return $query->where('ssl_enabled', true);
    }

    public function scopeWithoutSsl(Builder $query): Builder
    {
        return $query->where('ssl_enabled', false);
    }

    public function scopeMain(Builder $query): Builder
    {
        return $query->where('is_main', true);
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function getDocumentRootPathAttribute(): string
    {
        if ($this->document_root) {
            return $this->document_root;
        }

        $username = $this->user->username ?? $this->user->name;
        return "/home/{$username}/domains/{$this->name}/public_html";
    }

    public function getAccessLogPathAttribute(): string
    {
        if ($this->access_log) {
            return $this->access_log;
        }

        $username = $this->user->username ?? $this->user->name;
        return "/home/{$username}/domains/{$this->name}/logs/access.log";
    }

    public function getErrorLogPathAttribute(): string
    {
        if ($this->error_log) {
            return $this->error_log;
        }

        $username = $this->user->username ?? $this->user->name;
        return "/home/{$username}/domains/{$this->name}/logs/error.log";
    }

    public function getLogsPathAttribute(): string
    {
        $username = $this->user->username ?? $this->user->name;
        return "/home/{$username}/domains/{$this->name}/logs";
    }

    public function getDomainPathAttribute(): string
    {
        $username = $this->user->username ?? $this->user->name;
        return "/home/{$username}/domains/{$this->name}";
    }

    public function getDiskUsedFormattedAttribute(): string
    {
        return $this->formatBytes($this->disk_used);
    }

    public function getBandwidthUsedFormattedAttribute(): string
    {
        return $this->formatBytes($this->bandwidth_used);
    }

    public function getSslExpiresInDaysAttribute(): ?int
    {
        if (!$this->ssl_expires_at) {
            return null;
        }

        return (int) now()->diffInDays($this->ssl_expires_at, false);
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        $days = $this->ssl_expires_in_days;
        return $days !== null && $days <= 30 && $days > 0;
    }

    public function getIsSslExpiredAttribute(): bool
    {
        $days = $this->ssl_expires_in_days;
        return $days !== null && $days <= 0;
    }

    // =========================================================================
    // Methods
    // =========================================================================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function hasSsl(): bool
    {
        return $this->ssl_enabled;
    }

    public function suspend(): void
    {
        $this->update(['status' => 'suspended']);
    }

    public function unsuspend(): void
    {
        $this->update(['status' => 'active']);
    }

    public function enableSsl(): void
    {
        $this->update(['ssl_enabled' => true]);
    }

    public function disableSsl(): void
    {
        $this->update(['ssl_enabled' => false]);
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    // =========================================================================
    // Factory
    // =========================================================================

    protected static function newFactory()
    {
        return \App\Modules\Domain\Database\Factories\DomainFactory::new();
    }
}
