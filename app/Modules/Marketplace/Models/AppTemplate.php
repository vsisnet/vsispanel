<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppTemplate extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'icon',
        'category',
        'type',
        'requirements',
        'install_script',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function installations()
    {
        return $this->hasMany(AppInstallation::class, 'app_template_id');
    }
}
