<?php

declare(strict_types=1);

namespace App\Modules\Base\Policies;

use App\Modules\Auth\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

abstract class ModulePolicy
{
    use HandlesAuthorization;

    /**
     * The permission prefix for this module (e.g., 'domains', 'databases')
     */
    protected string $permissionPrefix = '';

    /**
     * The field name used for ownership check
     */
    protected string $ownerField = 'user_id';

    /**
     * Perform pre-authorization checks.
     * Admin bypasses all checks.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo("{$this->permissionPrefix}.view");
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Model $model): bool
    {
        if (!$user->hasPermissionTo("{$this->permissionPrefix}.view")) {
            return false;
        }

        return $this->checkOwnership($user, $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo("{$this->permissionPrefix}.create");
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $model): bool
    {
        if (!$user->hasPermissionTo("{$this->permissionPrefix}.edit")) {
            return false;
        }

        return $this->checkOwnership($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $model): bool
    {
        if (!$user->hasPermissionTo("{$this->permissionPrefix}.delete")) {
            return false;
        }

        return $this->checkOwnership($user, $model);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Model $model): bool
    {
        return $this->delete($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Model $model): bool
    {
        return $this->delete($user, $model);
    }

    /**
     * Determine whether the user can manage all records (admin-level access)
     */
    public function manageAll(User $user): bool
    {
        return $user->hasPermissionTo("{$this->permissionPrefix}.manage-all");
    }

    /**
     * Check ownership of the model.
     * - Admin: can access everything (handled by before())
     * - Reseller: can access own + customers' resources
     * - User: can only access own resources
     */
    protected function checkOwnership(User $user, Model $model): bool
    {
        // If user has manage-all permission, skip ownership check
        if ($user->hasPermissionTo("{$this->permissionPrefix}.manage-all")) {
            return true;
        }

        $ownerId = $model->{$this->ownerField};

        // Direct ownership - cast to string for UUID comparison
        if ((string) $ownerId === (string) $user->id) {
            return true;
        }

        // Reseller can access their customers' resources
        if ($user->isReseller()) {
            $customer = User::find($ownerId);
            if ($customer && $customer->parent_id === $user->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the owner field name
     */
    public function getOwnerField(): string
    {
        return $this->ownerField;
    }

    /**
     * Get the permission prefix
     */
    public function getPermissionPrefix(): string
    {
        return $this->permissionPrefix;
    }
}
