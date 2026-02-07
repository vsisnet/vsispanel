<?php

declare(strict_types=1);

namespace App\Modules\Database\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DatabaseDatabaseUserPivot extends Pivot
{
    use HasUuids;

    protected $table = 'database_database_user';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'privileges' => 'array',
    ];
}
