<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupNotComplete
{
    /**
     * Redirect to login if setup is already complete.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (file_exists(storage_path('installed'))) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'ALREADY_INSTALLED', 'message' => 'Panel is already installed'],
                ], 403);
            }
            return redirect('/login');
        }

        return $next($request);
    }
}
