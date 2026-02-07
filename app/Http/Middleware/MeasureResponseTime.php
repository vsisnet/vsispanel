<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MeasureResponseTime
{
    /**
     * Handle an incoming request.
     * Adds X-Response-Time header to measure API performance.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $start) * 1000;
        $response->headers->set('X-Response-Time', sprintf('%.2fms', $duration));

        return $response;
    }
}
