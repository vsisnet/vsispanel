<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Unauthenticated.',
                ],
            ], 401);
        }

        if ($user->status === 'suspended') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_SUSPENDED',
                    'message' => 'Your account has been suspended. Please contact support.',
                ],
            ], 403);
        }

        if ($user->status === 'terminated') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_TERMINATED',
                    'message' => 'Your account has been terminated.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
