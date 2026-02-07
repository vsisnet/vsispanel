<?php

declare(strict_types=1);

namespace App\Modules\Domain\Policies;

use App\Modules\Auth\Models\User;
use App\Modules\Base\Policies\ModulePolicy;
use App\Modules\Domain\Models\Domain;

class DomainPolicy extends ModulePolicy
{
    /**
     * The permission prefix for domains
     */
    protected string $permissionPrefix = 'domains';

    /**
     * The field name used for ownership check
     */
    protected string $ownerField = 'user_id';

    /**
     * Determine whether the user can suspend the domain.
     */
    public function suspend(User $user, Domain $domain): bool
    {
        // Only admin and reseller can suspend
        if ($user->isUser()) {
            return false;
        }

        // Reseller can only suspend customer domains
        if ($user->isReseller()) {
            return $domain->user->parent_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can unsuspend the domain.
     */
    public function unsuspend(User $user, Domain $domain): bool
    {
        return $this->suspend($user, $domain);
    }
}
