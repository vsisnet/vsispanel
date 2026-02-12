<?php

declare(strict_types=1);

namespace App\Modules\Migration\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'status',
        'source_type',
        'source_host',
        'source_port',
        'source_credentials',
        'items',
        'discovered_data',
        'progress',
        'log',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'items' => 'array',
        'discovered_data' => 'array',
        'progress' => 'integer',
        'source_port' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $hidden = [
        'source_credentials',
    ];

    // =========================================================================
    // Accessors / Mutators
    // =========================================================================

    public function setSourceCredentialsAttribute($value): void
    {
        $this->attributes['source_credentials'] = is_array($value)
            ? encrypt(json_encode($value))
            : encrypt($value);
    }

    public function getSourceCredentialsAttribute($value): ?array
    {
        if (!$value) return null;
        try {
            return json_decode(decrypt($value), true);
        } catch (\Exception) {
            return null;
        }
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function appendLog(string $message): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $this->log = ($this->log ?? '') . "[{$timestamp}] {$message}\n";
        $this->saveQuietly();
    }

    public function updateProgress(int $progress, ?string $message = null): void
    {
        $this->progress = min(100, max(0, $progress));
        if ($message) {
            $this->appendLog($message);
        } else {
            $this->saveQuietly();
        }
    }

    public function markRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
            'progress' => 0,
        ]);
        $this->appendLog('Migration started');
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress' => 100,
        ]);
        $this->appendLog('Migration completed successfully');
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
        $this->appendLog("Migration failed: {$reason}");
    }

    public function markCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
        $this->appendLog('Migration cancelled by user');
    }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isRunning(): bool { return $this->status === 'running'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isFailed(): bool { return $this->status === 'failed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
}
