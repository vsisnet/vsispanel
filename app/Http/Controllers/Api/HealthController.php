<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\ServerInfoCollector;
use App\Services\ServiceManager;
use App\Services\SystemCommandExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use OpenApi\Attributes as OA;

class HealthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected SystemCommandExecutor $executor,
        protected ServiceManager $serviceManager
    ) {}

    /**
     * Basic health check endpoint
     */
    #[OA\Get(
        path: '/api/health',
        operationId: 'healthCheck',
        tags: ['Health'],
        summary: 'Basic health check',
        description: 'Returns the basic health status of the API',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'status', type: 'string', example: 'healthy'),
                                new OA\Property(property: 'version', type: 'string', example: '1.0.0'),
                                new OA\Property(property: 'environment', type: 'string', example: 'production'),
                                new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        return $this->successResponse([
            'status' => 'healthy',
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Detailed health check with service status
     */
    #[OA\Get(
        path: '/api/health/detailed',
        operationId: 'healthCheckDetailed',
        tags: ['Health'],
        summary: 'Detailed health check',
        description: 'Returns detailed health status including database, redis, and storage checks',
        responses: [
            new OA\Response(
                response: 200,
                description: 'All services healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'status', type: 'string', example: 'healthy'),
                                new OA\Property(property: 'version', type: 'string', example: '1.0.0'),
                                new OA\Property(property: 'environment', type: 'string', example: 'production'),
                                new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'checks', type: 'object'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 503,
                description: 'Some services degraded or unhealthy'
            ),
        ]
    )]
    public function detailed(): JsonResponse
    {
        $checks = [
            'app' => $this->checkApp(),
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
        ];

        $isHealthy = collect($checks)->every(fn($check) => $check['status'] === 'healthy');

        $data = [
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ];

        return $this->successResponse($data, '', $isHealthy ? 200 : 503);
    }

    /**
     * Get system information
     */
    #[OA\Get(
        path: '/api/health/system',
        operationId: 'healthSystemInfo',
        tags: ['Health'],
        summary: 'System information',
        description: 'Returns system information including uptime, load, memory, and disk usage. Requires authentication.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uptime', type: 'object'),
                                new OA\Property(property: 'load', type: 'object'),
                                new OA\Property(property: 'memory', type: 'object'),
                                new OA\Property(property: 'disk', type: 'object'),
                                new OA\Property(property: 'php_version', type: 'string', example: '8.3.0'),
                                new OA\Property(property: 'laravel_version', type: 'string', example: '11.0.0'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
        ]
    )]
    public function system(): JsonResponse
    {
        $collector = new ServerInfoCollector($this->executor);

        return $this->successResponse([
            'uptime' => $collector->getUptime(),
            'load' => $collector->getLoadAverage(),
            'memory' => $collector->getMemoryInfo(),
            'disk' => $collector->getDiskInfo(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ]);
    }

    /**
     * Check app status
     */
    protected function checkApp(): array
    {
        return [
            'status' => 'healthy',
            'message' => 'Application is running',
            'details' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'debug_mode' => config('app.debug'),
            ],
        ];
    }

    /**
     * Check database connection
     */
    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'details' => [
                    'driver' => config('database.default'),
                    'latency_ms' => $latency,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'details' => [
                    'error' => config('app.debug') ? $e->getMessage() : 'Connection error',
                ],
            ];
        }
    }

    /**
     * Check Redis connection
     */
    protected function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'message' => 'Redis connection successful',
                'details' => [
                    'latency_ms' => $latency,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis connection failed',
                'details' => [
                    'error' => config('app.debug') ? $e->getMessage() : 'Connection error',
                ],
            ];
        }
    }

    /**
     * Check storage accessibility
     */
    protected function checkStorage(): array
    {
        $storagePath = storage_path('app');

        if (!is_dir($storagePath) || !is_writable($storagePath)) {
            return [
                'status' => 'unhealthy',
                'message' => 'Storage directory not writable',
                'details' => [
                    'path' => $storagePath,
                ],
            ];
        }

        $freeSpace = disk_free_space($storagePath);
        $totalSpace = disk_total_space($storagePath);
        $usedPercent = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 1);

        return [
            'status' => $usedPercent > 90 ? 'degraded' : 'healthy',
            'message' => $usedPercent > 90 ? 'Storage space running low' : 'Storage accessible',
            'details' => [
                'free_space' => $this->formatBytes($freeSpace),
                'total_space' => $this->formatBytes($totalSpace),
                'used_percent' => $usedPercent,
            ],
        ];
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
