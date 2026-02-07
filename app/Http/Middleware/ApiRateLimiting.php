<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimiting
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  string  $type  The rate limit type: default, auth, or heavy
     */
    public function handle(Request $request, Closure $next, string $type = 'default'): Response
    {
        $key = $this->resolveRequestSignature($request, $type);
        $maxAttempts = $this->getMaxAttempts($type);
        $decayMinutes = 1;

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildTooManyAttemptsResponse($key, $maxAttempts);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Resolve the request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request, string $type): string
    {
        $userId = $request->user()?->id ?? 'guest';
        $ip = $request->ip();

        return sha1("{$type}|{$userId}|{$ip}");
    }

    /**
     * Get max attempts based on rate limit type
     */
    protected function getMaxAttempts(string $type): int
    {
        return match ($type) {
            'auth' => config('vsispanel.rate_limits.auth', 10),
            'heavy' => config('vsispanel.rate_limits.heavy', 10),
            default => config('vsispanel.rate_limits.default', 60),
        };
    }

    /**
     * Calculate remaining attempts
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return $this->limiter->remaining($key, $maxAttempts);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remaining): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
        ]);

        return $response;
    }

    /**
     * Build too many attempts response
     */
    protected function buildTooManyAttemptsResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TOO_MANY_REQUESTS',
                'message' => 'Too many requests. Please slow down.',
                'retry_after' => $retryAfter,
            ],
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->getTimestamp(),
        ]);
    }
}
