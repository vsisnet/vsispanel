<?php

declare(strict_types=1);

namespace App\Modules\Backup\Policies;

use App\Modules\Auth\Models\User;
use App\Modules\Base\Policies\ModulePolicy;
use Illuminate\Database\Eloquent\Model;

class BackupPolicy extends ModulePolicy
{
    /**
     * The permission prefix for backups
     */
    protected string $permissionPrefix = 'backup';

    /**
     * The field name used for ownership check
     */
    protected string $ownerField = 'user_id';

    /**
     * Determine whether the user can restore a backup.
     */
    public function restore(User $user, Model $model): bool
    {
        if (!$user->hasPermissionTo('backup.restore', 'sanctum')) {
            return false;
        }

        return $this->checkOwnership($user, $model);
    }
}
