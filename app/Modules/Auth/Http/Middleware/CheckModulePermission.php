<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModulePermission
{
    /**
     * Permission mapping by route prefix
     */
    protected array $routePermissionMap = [
        'api/domains' => 'domains.view',
        'api/hosting' => 'plans.view',
        'api/databases' => 'databases.view',
        'api/mail' => 'mail.view',
        'api/dns' => 'dns.view',
        'api/ssl' => 'ssl.view',
        'api/files' => 'files.view',
        'api/ftp' => 'ftp.view',
        'api/backup' => 'backup.view',
        'api/firewall' => 'firewall.view',
        'api/monitoring' => 'monitoring.view',
        'api/cron' => 'cron.view',
        'api/users' => 'users.view',
        'api/server' => 'server.view',
    ];

    /**
     * Method-specific permission suffixes
     */
    protected array $methodPermissionMap = [
        'GET' => 'view',
        'POST' => 'create',
        'PUT' => 'edit',
        'PATCH' => 'edit',
        'DELETE' => 'delete',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        // Admin bypasses all permission checks
        if ($user->isAdmin()) {
            return $next($request);
        }

        // If specific permission is provided, check it
        if ($permission) {
            if (!$user->hasPermissionTo($permission, 'sanctum')) {
                return $this->forbiddenResponse($permission);
            }
            return $next($request);
        }

        // Auto-detect permission from route
        $detectedPermission = $this->detectPermission($request);

        if ($detectedPermission && !$user->hasPermissionTo($detectedPermission, 'sanctum')) {
            return $this->forbiddenResponse($detectedPermission);
        }

        return $next($request);
    }

    /**
     * Detect required permission based on route and HTTP method
     */
    protected function detectPermission(Request $request): ?string
    {
        $path = $request->path();
        $method = $request->method();

        foreach ($this->routePermissionMap as $routePrefix => $basePermission) {
            if (str_starts_with($path, $routePrefix)) {
                // Extract module name from base permission (e.g., 'domains' from 'domains.view')
                $module = explode('.', $basePermission)[0];

                // Get action suffix based on HTTP method
                $action = $this->methodPermissionMap[$method] ?? 'view';

                return "{$module}.{$action}";
            }
        }

        return null;
    }

    /**
     * Return forbidden response
     */
    protected function forbiddenResponse(string $permission): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to perform this action.',
                'required_permission' => config('app.debug') ? $permission : null,
            ],
        ], 403);
    }
}
