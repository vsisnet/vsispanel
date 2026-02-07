<?php

declare(strict_types=1);

namespace App\Modules\Backup\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestoreOperation extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'backup_id',
        'status',
        'target_path',
        'include_paths',
        'files_restored',
        'bytes_restored',
        'output',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'include_paths' => 'array',
        'files_restored' => 'integer',
        'bytes_restored' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(int $filesRestored, int $bytesRestored, ?string $output = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'files_restored' => $filesRestored,
            'bytes_restored' => $bytesRestored,
            'output' => $output,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getBytesRestoredFormattedAttribute(): string
    {
        $bytes = $this->bytes_restored;
        if ($bytes > 0) {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $factor = floor((strlen((string) $bytes) - 1) / 3);
            return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
        }
        return '0 B';
    }
}
