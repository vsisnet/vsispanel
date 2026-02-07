<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLastLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->last_login_at?->isToday()) {
            $user->updateQuietly([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
        }

        return $next($request);
    }
}
