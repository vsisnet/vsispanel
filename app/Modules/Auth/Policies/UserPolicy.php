<?php

declare(strict_types=1);

namespace App\Modules\Auth\Policies;

use App\Modules\Auth\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Admin can do everything.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * List users: admin or reseller only.
     */
    public function viewAny(User $user): bool
    {
        return $user->isReseller();
    }

    /**
     * View a specific user.
     */
    public function view(User $user, User $target): bool
    {
        if ($user->isReseller()) {
            return $target->parent_id === $user->id;
        }

        return $user->id === $target->id;
    }

    /**
     * Create a new user.
     */
    public function create(User $user): bool
    {
        return $user->isReseller();
    }

    /**
     * Update a user.
     */
    public function update(User $user, User $target): bool
    {
        if ($user->isReseller()) {
            return $target->parent_id === $user->id;
        }

        return $user->id === $target->id;
    }

    /**
     * Delete a user.
     */
    public function delete(User $user, User $target): bool
    {
        if ($user->isReseller()) {
            return $target->parent_id === $user->id;
        }

        return false;
    }

    /**
     * Suspend/unsuspend a user.
     */
    public function suspend(User $user, User $target): bool
    {
        if ($user->isReseller()) {
            return $target->parent_id === $user->id;
        }

        return false;
    }

    /**
     * Impersonate a user.
     */
    public function impersonate(User $user, User $target): bool
    {
        return $user->canManage($target);
    }
}
