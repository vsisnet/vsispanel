<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Prepend ForceJsonResponse for API routes
        $middleware->prependToGroup('api', [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'account.active' => \App\Modules\Auth\Http\Middleware\EnsureAccountActive::class,
            'track.login' => \App\Modules\Auth\Http\Middleware\TrackLastLogin::class,
            'permission' => \App\Modules\Auth\Http\Middleware\CheckModulePermission::class,
            'api.rate' => \App\Http\Middleware\ApiRateLimiting::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle API exceptions with JSON responses
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Resource not found',
                    ],
                ], 404);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => $e->getMessage() ?: 'Access denied',
                    ],
                ], 403);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => 'Unauthenticated',
                    ],
                ], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => $e->getMessage(),
                        'errors' => $e->errors(),
                    ],
                ], 422);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'METHOD_NOT_ALLOWED',
                        'message' => 'Method not allowed',
                    ],
                ], 405);
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TOO_MANY_REQUESTS',
                        'message' => 'Too many requests',
                        'retry_after' => $retryAfter,
                    ],
                ], 429)->withHeaders(['Retry-After' => $retryAfter]);
            }
        });

        // Handle generic exceptions (500 errors)
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                // Log the error
                Log::error('API Error', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => $request->user()?->id,
                ]);

                // Hide details in production
                $message = config('app.debug')
                    ? $e->getMessage()
                    : 'An unexpected error occurred';

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SERVER_ERROR',
                        'message' => $message,
                    ],
                ], 500);
            }
        });
    })->create();
