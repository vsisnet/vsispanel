<?php

declare(strict_types=1);

namespace App\Modules\Backup\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class BackupConfig extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'backup_items',
        'schedule',
        'schedule_time',
        'schedule_day',
        'schedule_cron',
        'retention_policy',
        'destination_type',
        'destinations',
        'destination_config',
        'storage_remote_id',
        'include_paths',
        'exclude_patterns',
        'is_active',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'retention_policy' => 'array',
        'destinations' => 'array',
        'backup_items' => 'array',
        'include_paths' => 'array',
        'exclude_patterns' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'type' => 'full',
        'destination_type' => 'local',
    ];

    // Destination types
    public const DESTINATION_LOCAL = 'local';
    public const DESTINATION_S3 = 's3';
    public const DESTINATION_FTP = 'ftp';
    public const DESTINATION_B2 = 'b2';
    public const DESTINATION_RCLONE = 'rclone';

    // Backup types
    public const TYPE_FULL = 'full';
    public const TYPE_FILES = 'files';
    public const TYPE_DATABASES = 'databases';
    public const TYPE_EMAILS = 'emails';
    public const TYPE_CONFIG = 'config';
    public const TYPE_CUSTOM = 'custom';

    /**
     * Get available destination types
     */
    public static function getDestinationTypes(): array
    {
        return [
            self::DESTINATION_LOCAL,
            self::DESTINATION_S3,
            self::DESTINATION_FTP,
            self::DESTINATION_B2,
            self::DESTINATION_RCLONE,
        ];
    }

    /**
     * Get available backup types
     */
    public static function getBackupTypes(): array
    {
        return [
            self::TYPE_FULL,
            self::TYPE_FILES,
            self::TYPE_DATABASES,
            self::TYPE_EMAILS,
            self::TYPE_CONFIG,
            self::TYPE_CUSTOM,
        ];
    }

    /**
     * Get the user that owns this config
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get backups for this config
     */
    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    /**
     * Get the storage remote for this config
     */
    public function storageRemote(): BelongsTo
    {
        return $this->belongsTo(StorageRemote::class);
    }

    /**
     * Get destination config (decrypted)
     */
    public function getDestinationConfigAttribute($value): array
    {
        if (!$value) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($value), true) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Set destination config (encrypted)
     */
    public function setDestinationConfigAttribute($value): void
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $this->attributes['destination_config'] = Crypt::encryptString($value);
    }

    /**
     * Scope for active configs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for due backups
     */
    public function scopeDue($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            });
    }

    /**
     * Calculate next run time based on schedule
     */
    public function calculateNextRun(): ?\DateTime
    {
        if (!$this->schedule) {
            return null;
        }

        $now = new \DateTime();
        $time = $this->schedule_time ?? '02:00';
        $day = $this->schedule_day ?? '1';

        switch ($this->schedule) {
            case 'daily':
                // Run daily at specified time
                $next = new \DateTime("today {$time}");
                if ($next <= $now) {
                    $next = new \DateTime("tomorrow {$time}");
                }
                return $next;

            case 'n_days':
                // Run every N days at specified time
                $interval = max(1, (int) $day);
                $next = new \DateTime("today {$time}");
                if ($next <= $now) {
                    $next->modify("+{$interval} days");
                }
                return $next;

            case 'hourly':
                // Run every hour at specified minute
                $minute = 0;
                if (preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
                    $minute = (int) $matches[2];
                }
                $next = clone $now;
                $next->setTime((int) $now->format('H'), $minute, 0);
                if ($next <= $now) {
                    $next->modify('+1 hour');
                }
                return $next;

            case 'n_hours':
                // Run every N hours at specified minute
                $interval = max(1, (int) $day);
                $minute = 0;
                if (preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
                    $minute = (int) $matches[2];
                }
                $next = clone $now;
                $next->setTime((int) $now->format('H'), $minute, 0);
                if ($next <= $now) {
                    $next->modify("+{$interval} hours");
                }
                return $next;

            case 'n_minutes':
                // Run every N minutes from now
                $interval = max(1, (int) $day);
                $next = clone $now;
                $next->modify("+{$interval} minutes");
                // Reset seconds to 0
                $next->setTime((int) $next->format('H'), (int) $next->format('i'), 0);
                return $next;

            case 'weekly':
                // Run weekly on specified day at specified time
                $dayOfWeek = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                $dayName = $dayOfWeek[(int) $day] ?? 'sunday';
                $next = new \DateTime("this {$dayName} {$time}");
                if ($next <= $now) {
                    $next = new \DateTime("next {$dayName} {$time}");
                }
                return $next;

            case 'monthly':
                // Run monthly on specified day at specified time
                if ($day === 'last') {
                    $next = new \DateTime("last day of this month {$time}");
                    if ($next <= $now) {
                        $next = new \DateTime("last day of next month {$time}");
                    }
                } else {
                    $dayNum = min(28, max(1, (int) $day));
                    $next = new \DateTime("{$now->format('Y-m')}-{$dayNum} {$time}");
                    if ($next <= $now) {
                        $next->modify('+1 month');
                    }
                }
                return $next;

            case 'custom':
                // Parse cron expression - simplified for now
                if ($this->schedule_cron) {
                    // TODO: Implement proper cron parsing
                    return new \DateTime('tomorrow 02:00');
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Check if this is a server-wide config
     */
    public function isServerWide(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Update next run time based on schedule
     */
    public function updateNextRunAt(): void
    {
        $nextRun = $this->calculateNextRun();
        if ($nextRun) {
            $this->update(['next_run_at' => $nextRun]);
        }
    }

    /**
     * Get the latest backup
     */
    public function latestBackup(): ?Backup
    {
        return $this->backups()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get the latest successful backup
     */
    public function latestSuccessfulBackup(): ?Backup
    {
        return $this->backups()
            ->where('status', Backup::STATUS_COMPLETED)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get retention policy with defaults
     */
    public function getRetentionPolicyWithDefaults(): array
    {
        $defaults = [
            'keep_last' => 5,
            'keep_daily' => 7,
            'keep_weekly' => 4,
            'keep_monthly' => 3,
        ];

        return array_merge($defaults, $this->retention_policy ?? []);
    }
}
