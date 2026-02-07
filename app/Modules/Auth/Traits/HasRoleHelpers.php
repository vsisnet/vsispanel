<?php

declare(strict_types=1);

namespace App\Modules\Auth\Traits;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasRoleHelpers
{
    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is reseller
     */
    public function isReseller(): bool
    {
        return $this->hasRole('reseller');
    }

    /**
     * Check if user is regular user
     */
    public function isUser(): bool
    {
        return $this->hasRole('user');
    }

    /**
     * Check if current user can manage target user
     * - Admin can manage everyone
     * - Reseller can only manage their own customers
     * - User cannot manage anyone
     */
    public function canManage(User $target): bool
    {
        // Admin can manage everyone
        if ($this->isAdmin()) {
            return true;
        }

        // Reseller can manage their direct customers
        if ($this->isReseller()) {
            return $target->parent_id === $this->id;
        }

        // Regular users cannot manage anyone
        return false;
    }

    /**
     * Check if current user can view target user
     */
    public function canView(User $target): bool
    {
        // Admin can view everyone
        if ($this->isAdmin()) {
            return true;
        }

        // Reseller can view themselves and their customers
        if ($this->isReseller()) {
            return $target->id === $this->id || $target->parent_id === $this->id;
        }

        // User can only view themselves
        return $target->id === $this->id;
    }

    /**
     * Scope to filter users accessible by a manager
     * - Admin: all users
     * - Reseller: only their customers
     * - User: only themselves
     */
    public function scopeAccessibleBy(Builder $query, User $manager): Builder
    {
        if ($manager->isAdmin()) {
            return $query;
        }

        if ($manager->isReseller()) {
            return $query->where('parent_id', $manager->id);
        }

        return $query->where('id', $manager->id);
    }

    /**
     * Scope to get customers of a reseller or all users for admin
     */
    public function scopeCustomersOf(Builder $query, User $manager): Builder
    {
        if ($manager->isAdmin()) {
            return $query->where('role', 'user');
        }

        if ($manager->isReseller()) {
            return $query->where('parent_id', $manager->id)
                         ->where('role', 'user');
        }

        return $query->whereRaw('1 = 0'); // No results for regular users
    }

    /**
     * Get hierarchical children (direct customers)
     */
    public function customers()
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    /**
     * Get parent (reseller)
     */
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * Check if user has parent (belongs to a reseller)
     */
    public function hasParent(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Get the reseller that owns this user (for regular users)
     */
    public function getResellerAttribute(): ?User
    {
        if ($this->isUser() && $this->hasParent()) {
            return $this->parent;
        }

        return null;
    }
}
