<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlertRule extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'severity',
        'metric',
        'condition',
        'threshold',
        'duration_seconds',
        'notification_channels',
        'config',
        'is_active',
        'cooldown_minutes',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'notification_channels' => 'array',
            'config' => 'array',
            'threshold' => 'float',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function history(): HasMany
    {
        return $this->hasMany(AlertHistory::class);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }
}
