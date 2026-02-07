<?php

declare(strict_types=1);

namespace App\Modules\Mail\Models;

use App\Modules\Domain\Models\Domain;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MailDomain extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'domain_id',
        'is_active',
        'catch_all_address',
        'max_accounts',
        'default_quota_mb',
        'dkim_enabled',
        'dkim_selector',
        'dkim_private_key',
        'dkim_public_key',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_accounts' => 'integer',
        'default_quota_mb' => 'integer',
        'dkim_enabled' => 'boolean',
    ];

    protected $hidden = [
        'dkim_private_key',
    ];

    /**
     * Get the domain this mail domain belongs to.
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the mail accounts for this domain.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(MailAccount::class);
    }

    /**
     * Get the mail aliases for this domain.
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(MailAlias::class);
    }

    /**
     * Get active accounts count.
     */
    public function getActiveAccountsCountAttribute(): int
    {
        return $this->accounts()->where('status', 'active')->count();
    }

    /**
     * Get total accounts count.
     */
    public function getTotalAccountsCountAttribute(): int
    {
        return $this->accounts()->count();
    }

    /**
     * Check if can create more accounts.
     */
    public function canCreateAccount(): bool
    {
        return $this->total_accounts_count < $this->max_accounts;
    }

    /**
     * Get domain name via relationship.
     */
    public function getDomainNameAttribute(): string
    {
        return $this->domain->name ?? '';
    }

    /**
     * Scope to get active mail domains.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get mail domains with DKIM enabled.
     */
    public function scopeWithDkim($query)
    {
        return $query->where('dkim_enabled', true);
    }
}
