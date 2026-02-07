<?php

declare(strict_types=1);

namespace App\Modules\Cron\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CronJob extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'command',
        'schedule',
        'description',
        'is_active',
        'run_as_user',
        'output_handling',
        'output_email',
        'log_path',
        'last_run_at',
        'last_run_status',
        'last_run_output',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
