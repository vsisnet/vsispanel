<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Policies;

use App\Modules\Auth\Models\User;
use App\Modules\Hosting\Models\Plan;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Plans are managed by admins only.
 * Regular users can only view available plans.
 */
class PlanPolicy
{
    use HandlesAuthorization;

    /**
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
     * Determine whether the user can view any plans.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('plans.view');
    }

    /**
     * Determine whether the user can view the plan.
     */
    public function view(User $user, Plan $plan): bool
    {
        // Users can view active plans
        return $plan->is_active || $user->hasPermissionTo('plans.view');
    }

    /**
     * Determine whether the user can create plans.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('plans.create');
    }

    /**
     * Determine whether the user can update the plan.
     */
    public function update(User $user, Plan $plan): bool
    {
        return $user->hasPermissionTo('plans.edit');
    }

    /**
     * Determine whether the user can delete the plan.
     */
    public function delete(User $user, Plan $plan): bool
    {
        return $user->hasPermissionTo('plans.delete');
    }
}
