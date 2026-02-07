<?php

declare(strict_types=1);

namespace App\Modules\AppManager\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManagedApp extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'category',
        'type',
        'status',
        'installed_version',
        'installed_versions',
        'active_version',
        'service_name',
        'is_running',
        'is_enabled',
        'config_files',
        'metadata',
        'installed_at',
        'last_checked_at',
    ];

    protected $appends = ['is_critical'];

    protected function casts(): array
    {
        return [
            'installed_versions' => 'array',
            'config_files' => 'array',
            'metadata' => 'array',
            'is_running' => 'boolean',
            'is_enabled' => 'boolean',
            'installed_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function getIsCriticalAttribute(): bool
    {
        return $this->isCritical();
    }

    public function isMultiVersion(): bool
    {
        return $this->type === 'multi_version';
    }

    public function isSystem(): bool
    {
        $appConfig = config("appmanager.apps.{$this->slug}", []);

        return $appConfig['is_system'] ?? false;
    }

    public function isCritical(): bool
    {
        $appConfig = config("appmanager.apps.{$this->slug}", []);

        return ($appConfig['is_critical'] ?? false) || ($appConfig['is_system'] ?? false);
    }

    public function isActiveVersion(?string $version): bool
    {
        return $version && $this->active_version === $version;
    }
}
