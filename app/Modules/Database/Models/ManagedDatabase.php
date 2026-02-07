<?php

declare(strict_types=1);

namespace App\Modules\Database\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ManagedDatabase extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'domain_id',
        'name',
        'original_name',
        'size_bytes',
        'charset',
        'collation',
        'status',
        'notes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function databaseUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            DatabaseUser::class,
            'database_database_user',
            'managed_database_id',
            'database_user_id'
        )->withPivot('privileges')
         ->withTimestamps()
         ->using(DatabaseDatabaseUserPivot::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForDomain(Builder $query, Domain $domain): Builder
    {
        return $query->where('domain_id', $domain->id);
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function getSizeFormattedAttribute(): string
    {
        return $this->formatBytes($this->size_bytes);
    }

    // =========================================================================
    // Methods
    // =========================================================================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    // =========================================================================
    // Factory
    // =========================================================================

    protected static function newFactory()
    {
        return \App\Modules\Database\Database\Factories\ManagedDatabaseFactory::new();
    }
}
