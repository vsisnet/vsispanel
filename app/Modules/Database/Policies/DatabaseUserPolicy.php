<?php

declare(strict_types=1);

namespace App\Modules\Database\Policies;

use App\Modules\Auth\Models\User;
use App\Modules\Base\Policies\ModulePolicy;
use App\Modules\Database\Models\DatabaseUser;
use Illuminate\Database\Eloquent\Model;

class DatabaseUserPolicy extends ModulePolicy
{
    protected string $permissionPrefix = 'database_users';

    public function view(User $user, Model $model): bool
    {
        /** @var DatabaseUser $model */
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->checkOwnership($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        /** @var DatabaseUser $model */
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->checkOwnership($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        /** @var DatabaseUser $model */
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->checkOwnership($user, $model);
    }

    protected function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
