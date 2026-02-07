<?php

declare(strict_types=1);

namespace App\Modules\Mail\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAlias extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'mail_domain_id',
        'source_address',
        'destination_address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the mail domain this alias belongs to.
     */
    public function mailDomain(): BelongsTo
    {
        return $this->belongsTo(MailDomain::class);
    }

    /**
     * Scope to get active aliases.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by domain.
     */
    public function scopeForDomain($query, string $domainId)
    {
        return $query->where('mail_domain_id', $domainId);
    }
}
