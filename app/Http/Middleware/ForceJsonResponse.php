<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     *
     * Forces the Accept header to application/json for API routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force JSON response for API requests
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
