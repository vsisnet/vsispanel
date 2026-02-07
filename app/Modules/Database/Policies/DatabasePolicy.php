<?php

declare(strict_types=1);

namespace App\Modules\Database\Policies;

use App\Modules\Base\Policies\ModulePolicy;

class DatabasePolicy extends ModulePolicy
{
    /**
     * The permission prefix for databases
     */
    protected string $permissionPrefix = 'databases';

    /**
     * The field name used for ownership check
     */
    protected string $ownerField = 'user_id';
}
