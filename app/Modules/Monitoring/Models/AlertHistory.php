<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertHistory extends Model
{
    public $timestamps = false;

    use HasUuids;

    protected $table = 'alert_history';

    protected $fillable = [
        'alert_rule_id',
        'current_value',
        'notification_sent',
        'notification_channel',
        'message',
        'severity',
        'category',
        'status',
        'triggered_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'notification_sent' => 'boolean',
            'current_value' => 'float',
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('status', '!=', 'resolved');
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
