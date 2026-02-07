<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cpu_usage',
        'memory_used',
        'memory_total',
        'disk_usage',
        'network_in',
        'network_out',
        'load_1m',
        'load_5m',
        'load_15m',
        'processes_total',
        'processes_running',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'disk_usage' => 'array',
            'cpu_usage' => 'float',
            'load_1m' => 'float',
            'load_5m' => 'float',
            'load_15m' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Safely decode JSON, handling values that are already arrays.
     */
    public function fromJson($value, $asObject = false)
    {
        if (is_array($value)) {
            return $value;
        }

        return parent::fromJson($value, $asObject);
    }

    public function scopePeriod($query, string $period)
    {
        $from = match ($period) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };

        return $query->where('recorded_at', '>=', $from);
    }
}
