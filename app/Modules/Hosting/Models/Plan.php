<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'disk_limit',
        'bandwidth_limit',
        'domains_limit',
        'subdomains_limit',
        'databases_limit',
        'email_accounts_limit',
        'ftp_accounts_limit',
        'php_version_default',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'disk_limit' => 'integer',
        'bandwidth_limit' => 'integer',
        'domains_limit' => 'integer',
        'subdomains_limit' => 'integer',
        'databases_limit' => 'integer',
        'email_accounts_limit' => 'integer',
        'ftp_accounts_limit' => 'integer',
        'is_active' => 'boolean',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // =========================================================================
    // Factory
    // =========================================================================

    protected static function newFactory()
    {
        return \App\Modules\Hosting\Database\Factories\PlanFactory::new();
    }
}
