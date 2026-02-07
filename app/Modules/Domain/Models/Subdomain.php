<?php

declare(strict_types=1);

namespace App\Modules\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Subdomain extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'domain_id',
        'name',
        'document_root',
        'php_version',
        'status',
        'ssl_enabled',
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function getFullNameAttribute(): string
    {
        return "{$this->name}.{$this->domain->name}";
    }

    public function getDocumentRootPathAttribute(): string
    {
        if ($this->document_root) {
            return $this->document_root;
        }

        $username = $this->domain->user->username ?? $this->domain->user->name;
        return "/home/{$username}/domains/{$this->domain->name}/subdomains/{$this->name}";
    }

    // =========================================================================
    // Methods
    // =========================================================================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasSsl(): bool
    {
        return $this->ssl_enabled;
    }
}
