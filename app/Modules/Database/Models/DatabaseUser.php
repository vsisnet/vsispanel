<?php

declare(strict_types=1);

namespace App\Modules\Database\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class DatabaseUser extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'username',
        'original_username',
        'host',
        'password_encrypted',
        'privileges',
        'notes',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    protected $casts = [
        'privileges' => 'array',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function databases(): BelongsToMany
    {
        return $this->belongsToMany(
            ManagedDatabase::class,
            'database_database_user',
            'database_user_id',
            'managed_database_id'
        )->withPivot('privileges')
         ->withTimestamps()
         ->using(DatabaseDatabaseUserPivot::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForHost(Builder $query, string $host): Builder
    {
        return $query->where('host', $host);
    }

    // =========================================================================
    // Methods
    // =========================================================================

    public function getFullUsernameAttribute(): string
    {
        return "{$this->username}@{$this->host}";
    }

    public function hasAccessTo(ManagedDatabase $database): bool
    {
        return $this->databases()->where('managed_database_id', $database->id)->exists();
    }

    /**
     * Get the decrypted password.
     */
    public function getDecryptedPassword(): ?string
    {
        if (empty($this->password_encrypted)) {
            return null;
        }

        try {
            return decrypt($this->password_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set the encrypted password.
     */
    public function setPassword(string $password): void
    {
        $this->password_encrypted = encrypt($password);
        $this->save();
    }

    // =========================================================================
    // Factory
    // =========================================================================

    protected static function newFactory()
    {
        return \App\Modules\Database\Database\Factories\DatabaseUserFactory::new();
    }
}
