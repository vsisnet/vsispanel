<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'started_at',
        'expires_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
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

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // =========================================================================
    // Factory
    // =========================================================================

    protected static function newFactory()
    {
        return \App\Modules\Hosting\Database\Factories\SubscriptionFactory::new();
    }
}
