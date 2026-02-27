<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        // Admin has all permissions
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Check Spatie permission
        if (!$user->hasPermissionTo($permission, 'sanctum')) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
