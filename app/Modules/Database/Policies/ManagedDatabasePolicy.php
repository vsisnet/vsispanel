<?php

declare(strict_types=1);

namespace App\Modules\Database\Policies;

use App\Modules\Auth\Models\User;
use App\Modules\Base\Policies\ModulePolicy;
use App\Modules\Database\Models\ManagedDatabase;
use Illuminate\Database\Eloquent\Model;

class ManagedDatabasePolicy extends ModulePolicy
{
    protected string $permissionPrefix = 'databases';

    public function view(User $user, Model $model): bool
    {
        /** @var ManagedDatabase $model */
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->checkOwnership($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        /** @var ManagedDatabase $model */
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->checkOwnership($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        /** @var ManagedDatabase $model */
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->checkOwnership($user, $model);
    }

    /**
     * Determine if the user can perform admin actions (e.g., change root password).
     */
    public function admin(User $user): bool
    {
        return $this->isAdmin($user);
    }

    protected function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
