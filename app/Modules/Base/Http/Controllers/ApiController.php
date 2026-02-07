<?php

declare(strict_types=1);

namespace App\Modules\Base\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;

/**
 * Base API Controller
 *
 * All module API controllers should extend this class.
 * Provides standard API response methods and authorization helpers.
 */
abstract class ApiController extends Controller
{
    use ApiResponseTrait;
    use AuthorizesRequests;

    /**
     * Authorize a given action for the current user.
     *
     * @throws AuthorizationException
     */
    protected function authorizeAction(string $ability, mixed $arguments = []): void
    {
        if (!Gate::allows($ability, $arguments)) {
            throw new AuthorizationException('You are not authorized to perform this action.');
        }
    }

    /**
     * Check if the current user can perform an action.
     */
    protected function can(string $ability, mixed $arguments = []): bool
    {
        return Gate::allows($ability, $arguments);
    }

    /**
     * Check if the current user cannot perform an action.
     */
    protected function cannot(string $ability, mixed $arguments = []): bool
    {
        return Gate::denies($ability, $arguments);
    }

    /**
     * Get the currently authenticated user.
     */
    protected function user(): ?\App\Modules\Auth\Models\User
    {
        return auth()->user();
    }

    /**
     * Check if the current user is an admin.
     */
    protected function isAdmin(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Check if the current user is a reseller.
     */
    protected function isReseller(): bool
    {
        return $this->user()?->isReseller() ?? false;
    }

    /**
     * Check if the current user has a specific permission.
     */
    protected function hasPermission(string $permission): bool
    {
        return $this->user()?->hasPermissionTo($permission) ?? false;
    }

    /**
     * Get validated request data with optional defaults.
     */
    protected function validatedData(array $defaults = []): array
    {
        return array_merge($defaults, request()->validated());
    }
}
