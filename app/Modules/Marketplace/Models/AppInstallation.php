<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AppInstallation extends Model
{
    use HasUuids;

    protected $fillable = [
        'domain_id',
        'app_template_id',
        'installed_by',
        'app_version',
        'status',
        'progress',
        'current_step',
        'logs',
        'options',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function template()
    {
        return $this->belongsTo(AppTemplate::class, 'app_template_id');
    }

    public function installer()
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function appendLog(string $message): void
    {
        $timestamp = now()->format('H:i:s');
        $current = $this->logs ?? '';
        $this->update([
            'logs' => $current . "[{$timestamp}] {$message}\n",
        ]);
    }
}
