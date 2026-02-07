<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Policies;

use App\Modules\Auth\Models\User;
use App\Modules\Base\Policies\ModulePolicy;
use App\Modules\Hosting\Models\Subscription;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPolicy extends ModulePolicy
{
    protected string $permissionPrefix = 'subscriptions';

    /**
     * Determine whether the user can view any subscriptions (admin list).
     * Only admins should be able to list all subscriptions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo("{$this->permissionPrefix}.manage-all");
    }

    /**
     * Determine whether the user can view the subscription.
     */
    public function view(User $user, Model $model): bool
    {
        /** @var Subscription $model */
        if (!$user->hasPermissionTo("{$this->permissionPrefix}.view")) {
            return false;
        }

        return $this->checkOwnership($user, $model);
    }

    /**
     * Determine whether the user can update the subscription.
     * Only admins can update subscriptions.
     */
    public function update(User $user, Model $model): bool
    {
        return $user->hasPermissionTo("{$this->permissionPrefix}.edit");
    }

    /**
     * Determine whether the user can delete the subscription.
     * Only admins can delete subscriptions.
     */
    public function delete(User $user, Model $model): bool
    {
        return $user->hasPermissionTo("{$this->permissionPrefix}.delete");
    }

    /**
     * Determine whether the user can suspend the subscription.
     */
    public function suspend(User $user, Subscription $subscription): bool
    {
        return $user->hasPermissionTo("{$this->permissionPrefix}.suspend");
    }

    /**
     * Determine whether the user can renew the subscription.
     */
    public function renew(User $user, Subscription $subscription): bool
    {
        return $user->hasPermissionTo("{$this->permissionPrefix}.renew");
    }
}
