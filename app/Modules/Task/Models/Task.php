<?php

declare(strict_types=1);

namespace App\Modules\Task\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'description',
        'status',
        'progress',
        'related_type',
        'related_id',
        'input_data',
        'output',
        'error_message',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'progress' => 'integer',
        'input_data' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Task types
    public const TYPE_BACKUP_CREATE = 'backup.create';
    public const TYPE_BACKUP_RESTORE = 'backup.restore';
    public const TYPE_SERVICE_START = 'service.start';
    public const TYPE_SERVICE_STOP = 'service.stop';
    public const TYPE_SERVICE_RESTART = 'service.restart';
    public const TYPE_SERVICE_INSTALL = 'service.install';
    public const TYPE_SERVICE_UNINSTALL = 'service.uninstall';
    public const TYPE_SSL_ISSUE = 'ssl.issue';
    public const TYPE_SSL_RENEW = 'ssl.renew';
    public const TYPE_DNS_SYNC = 'dns.sync';
    public const TYPE_SYSTEM_UPDATE = 'system.update';
    public const TYPE_DATABASE_IMPORT = 'database.import';
    public const TYPE_DATABASE_EXPORT = 'database.export';
    public const TYPE_FILE_UPLOAD = 'file.upload';
    public const TYPE_FILE_EXTRACT = 'file.extract';
    public const TYPE_CUSTOM = 'custom';

    // Task statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get all task types with labels
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_BACKUP_CREATE => 'Create Backup',
            self::TYPE_BACKUP_RESTORE => 'Restore Backup',
            self::TYPE_SERVICE_START => 'Start Service',
            self::TYPE_SERVICE_STOP => 'Stop Service',
            self::TYPE_SERVICE_RESTART => 'Restart Service',
            self::TYPE_SERVICE_INSTALL => 'Install Service',
            self::TYPE_SERVICE_UNINSTALL => 'Uninstall Service',
            self::TYPE_SSL_ISSUE => 'Issue SSL Certificate',
            self::TYPE_SSL_RENEW => 'Renew SSL Certificate',
            self::TYPE_DNS_SYNC => 'Sync DNS Records',
            self::TYPE_SYSTEM_UPDATE => 'System Update',
            self::TYPE_DATABASE_IMPORT => 'Import Database',
            self::TYPE_DATABASE_EXPORT => 'Export Database',
            self::TYPE_FILE_UPLOAD => 'Upload File',
            self::TYPE_FILE_EXTRACT => 'Extract Archive',
            self::TYPE_CUSTOM => 'Custom Task',
        ];
    }

    /**
     * Get all statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_RUNNING => 'Running',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Scope for pending tasks
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for running tasks
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Scope for completed tasks
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed tasks
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for active tasks (pending or running)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING]);
    }

    /**
     * Mark task as running
     */
    public function markAsRunning(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Update progress
     */
    public function updateProgress(int $progress, ?string $output = null): void
    {
        $data = ['progress' => min(100, max(0, $progress))];

        if ($output !== null) {
            $data['output'] = $this->output . $output;
        }

        $this->update($data);
    }

    /**
     * Append output
     */
    public function appendOutput(string $output): void
    {
        $this->update([
            'output' => ($this->output ?? '') . $output,
        ]);
    }

    /**
     * Mark task as completed
     */
    public function markAsCompleted(?string $output = null): void
    {
        $data = [
            'status' => self::STATUS_COMPLETED,
            'progress' => 100,
            'completed_at' => now(),
        ];

        if ($output !== null) {
            $data['output'] = ($this->output ?? '') . $output;
        }

        $this->update($data);
    }

    /**
     * Mark task as failed
     */
    public function markAsFailed(string $errorMessage, ?string $output = null): void
    {
        $data = [
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ];

        if ($output !== null) {
            $data['output'] = ($this->output ?? '') . $output;
        }

        $this->update($data);
    }

    /**
     * Mark task as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if task is active
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING]);
    }

    /**
     * Check if task can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING]);
    }

    /**
     * Get duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return (int) $this->started_at->diffInSeconds($endTime);
    }

    /**
     * Get formatted duration
     */
    public function getDurationFormattedAttribute(): string
    {
        $duration = $this->duration;

        if ($duration === null) {
            return '-';
        }

        if ($duration < 60) {
            return $duration . 's';
        }

        if ($duration < 3600) {
            $minutes = floor($duration / 60);
            $seconds = $duration % 60;
            return "{$minutes}m {$seconds}s";
        }

        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        return "{$hours}h {$minutes}m";
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * Get related model
     */
    public function related()
    {
        if (!$this->related_type || !$this->related_id) {
            return null;
        }

        return $this->morphTo('related', 'related_type', 'related_id');
    }

    /**
     * Get user
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
