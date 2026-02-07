<?php

declare(strict_types=1);

namespace App\Modules\Reseller\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerBranding extends Model
{
    use HasUuids;

    protected $fillable = [
        'reseller_id',
        'company_name',
        'logo_path',
        'favicon_path',
        'primary_color',
        'custom_css',
        'support_email',
        'support_url',
        'nameservers',
    ];

    protected function casts(): array
    {
        return [
            'nameservers' => 'array',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }
}
