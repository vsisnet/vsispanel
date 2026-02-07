<?php

declare(strict_types=1);

namespace App\Modules\Mail\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailForward extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'mail_account_id',
        'forward_to',
        'keep_copy',
        'is_active',
    ];

    protected $casts = [
        'keep_copy' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the mail account this forward belongs to.
     */
    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }

    /**
     * Scope to get active forwards.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
