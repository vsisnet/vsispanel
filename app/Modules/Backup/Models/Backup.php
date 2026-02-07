<?php

declare(strict_types=1);

namespace App\Modules\Backup\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Backup extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'backup_config_id',
        'type',
        'status',
        'size_bytes',
        'snapshot_id',
        'started_at',
        'completed_at',
        'error_message',
        'metadata',
        'storage_remote_id',
        'remote_path',
        'synced_remotes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
        'synced_remotes' => 'array',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    // Type constants (inherited from BackupConfig)
    public const TYPE_FULL = 'full';
    public const TYPE_FILES = 'files';
    public const TYPE_DATABASES = 'databases';
    public const TYPE_EMAILS = 'emails';
    public const TYPE_CONFIG = 'config';
    public const TYPE_CUSTOM = 'custom';

    /**
     * Get available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RUNNING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
        ];
    }

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the backup config
     */
    public function backupConfig(): BelongsTo
    {
        return $this->belongsTo(BackupConfig::class);
    }

    /**
     * Get the storage remote
     */
    public function storageRemote(): BelongsTo
    {
        return $this->belongsTo(StorageRemote::class);
    }

    /**
     * Scope for completed backups
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed backups
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for running backups
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Check if backup is complete
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if backup failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if backup is running
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Get display name (config name + datetime)
     */
    public function getDisplayNameAttribute(): string
    {
        $configName = $this->backupConfig?->name ?? 'Backup';
        $datetime = $this->created_at?->format('Y-m-d_H-i-s') ?? '';

        return "{$configName}_{$datetime}";
    }

    /**
     * Get formatted size
     */
    public function getSizeFormattedAttribute(): string
    {
        $bytes = $this->size_bytes ?? 0;

        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }

    /**
     * Get duration in human readable format
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        $diff = $this->completed_at->diff($this->started_at);

        if ($diff->h > 0) {
            return sprintf('%dh %dm %ds', $diff->h, $diff->i, $diff->s);
        }

        if ($diff->i > 0) {
            return sprintf('%dm %ds', $diff->i, $diff->s);
        }

        return sprintf('%ds', $diff->s);
    }

    /**
     * Mark backup as running
     */
    public function markAsRunning(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark backup as completed
     */
    public function markAsCompleted(string $snapshotId, int $sizeBytes, array $metadata = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'snapshot_id' => $snapshotId,
            'size_bytes' => $sizeBytes,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    /**
     * Mark backup as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }
}
