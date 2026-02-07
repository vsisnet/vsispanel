<?php

declare(strict_types=1);

namespace App\Modules\SSL\Models;

use App\Modules\Domain\Models\Domain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SslCertificate extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'domain_id',
        'type',
        'status',
        'certificate_path',
        'private_key_path',
        'ca_bundle_path',
        'issuer',
        'serial_number',
        'san',
        'issued_at',
        'expires_at',
        'auto_renew',
        'last_renewal_at',
        'renewal_attempts',
        'last_error',
    ];

    protected $casts = [
        'san' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_renewal_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    public function scopeAutoRenewable(Builder $query): Builder
    {
        return $query->where('auto_renew', true)
            ->where('type', 'lets_encrypt');
    }

    public function scopeLetsEncrypt(Builder $query): Builder
    {
        return $query->where('type', 'lets_encrypt');
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('type', 'custom');
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expires_at &&
               $this->expires_at->isFuture() &&
               $this->expires_at->diffInDays(now()) <= 30;
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return (int) now()->diffInDays($this->expires_at, false);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => $this->is_expiring_soon ? 'warning' : 'success',
            'pending' => 'info',
            'expired' => 'danger',
            'revoked' => 'danger',
            'failed' => 'danger',
            default => 'secondary',
        };
    }

    // =========================================================================
    // Methods
    // =========================================================================

    public function isLetsEncrypt(): bool
    {
        return $this->type === 'lets_encrypt';
    }

    public function isCustom(): bool
    {
        return $this->type === 'custom';
    }

    public function canRenew(): bool
    {
        return $this->isLetsEncrypt() &&
               $this->status === 'active' &&
               $this->auto_renew;
    }

    public function markAsActive(): void
    {
        $this->update([
            'status' => 'active',
            'last_error' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $error,
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function markAsRevoked(): void
    {
        $this->update(['status' => 'revoked']);
    }

    // =========================================================================
    // Factory
    // =========================================================================

    protected static function newFactory()
    {
        return \App\Modules\SSL\Database\Factories\SslCertificateFactory::new();
    }
}
