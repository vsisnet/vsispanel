<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AlertTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'category',
        'metric',
        'condition',
        'threshold',
        'severity',
        'config',
        'cooldown_minutes',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'float',
            'config' => 'array',
            'cooldown_minutes' => 'integer',
        ];
    }
}
