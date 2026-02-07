<?php

declare(strict_types=1);

namespace App\Modules\WebServer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Domain\Models\Domain;
use App\Modules\WebServer\Http\Requests\UpdatePhpVersionRequest;
use App\Modules\WebServer\Http\Requests\UpdatePhpSettingsRequest;
use App\Modules\WebServer\Services\PhpFpmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'PHP', description: 'PHP-FPM management endpoints')]
class PhpController extends Controller
{
    public function __construct(
        protected PhpFpmService $phpFpmService
    ) {}

    /**
     * Get installed PHP versions.
     */
    #[OA\Get(
        path: '/api/v1/php/versions',
        operationId: 'getPhpVersions',
        summary: 'Get installed PHP versions',
        tags: ['PHP'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of installed PHP versions'
            ),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function versions(): JsonResponse
    {
        $versions = $this->phpFpmService->getInstalledVersions();

        return response()->json([
            'success' => true,
            'data' => [
                'versions' => array_values($versions),
            ],
        ]);
    }

    /**
     * Get PHP info for a specific version.
     */
    #[OA\Get(
        path: '/api/v1/php/{version}/info',
        operationId: 'getPhpInfo',
        summary: 'Get PHP info for a version',
        tags: ['PHP'],
        parameters: [
            new OA\Parameter(
                name: 'version',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: '8.3')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'PHP version information'
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'PHP version not found')
        ]
    )]
    public function info(string $version): JsonResponse
    {
        $installedVersions = $this->phpFpmService->getInstalledVersions();

        if (!isset($installedVersions[$version]) || !$installedVersions[$version]['installed']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PHP_VERSION_NOT_FOUND',
                    'message' => "PHP version {$version} is not installed.",
                ],
            ], 404);
        }

        $info = $this->phpFpmService->getPhpInfo($version);

        return response()->json([
            'success' => true,
            'data' => $info,
        ]);
    }

    /**
     * Switch PHP version for a domain.
     */
    #[OA\Put(
        path: '/api/v1/domains/{domain}/php-version',
        operationId: 'switchPhpVersion',
        summary: 'Switch PHP version for a domain',
        tags: ['PHP'],
        parameters: [
            new OA\Parameter(
                name: 'domain',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['php_version'],
                properties: [
                    new OA\Property(property: 'php_version', type: 'string', example: '8.3')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'PHP version updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Domain not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function switchVersion(UpdatePhpVersionRequest $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $validated = $request->validated();
        $oldVersion = $domain->php_version;
        $newVersion = $validated['php_version'];

        if ($oldVersion === $newVersion) {
            return response()->json([
                'success' => true,
                'message' => 'PHP version is already set to ' . $newVersion,
                'data' => [
                    'domain' => $domain->name,
                    'php_version' => $newVersion,
                ],
            ]);
        }

        try {
            // Use domain-based version switch to preserve per-domain settings
            $this->phpFpmService->switchDomainVersion($domain, $oldVersion, $newVersion);

            return response()->json([
                'success' => true,
                'message' => "PHP version switched from {$oldVersion} to {$newVersion}",
                'data' => [
                    'domain' => $domain->name,
                    'php_version' => $newVersion,
                    'previous_version' => $oldVersion,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PHP_SWITCH_FAILED',
                    'message' => 'Failed to switch PHP version: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get PHP settings for a domain.
     */
    #[OA\Get(
        path: '/api/v1/domains/{domain}/php-settings',
        operationId: 'getPhpSettings',
        summary: 'Get PHP settings for a domain',
        tags: ['PHP'],
        parameters: [
            new OA\Parameter(
                name: 'domain',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'PHP settings'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Domain not found')
        ]
    )]
    public function getSettings(Domain $domain): JsonResponse
    {
        $this->authorize('view', $domain);

        // Get domain-specific PHP settings from the domain's pool configuration
        $settings = $this->phpFpmService->getDomainPhpSettings($domain);

        // Get default settings for comparison
        $defaults = [
            'memory_limit' => '256M',
            'upload_max_filesize' => '64M',
            'post_max_size' => '64M',
            'max_execution_time' => '30',
            'max_input_time' => '60',
            'display_errors' => 'off',
            'max_input_vars' => '1000',
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'domain' => $domain->name,
                'php_version' => $domain->php_version,
                'settings' => $settings,
                'defaults' => $defaults,
            ],
        ]);
    }

    /**
     * Update PHP settings for a domain.
     */
    #[OA\Put(
        path: '/api/v1/domains/{domain}/php-settings',
        operationId: 'updatePhpSettings',
        summary: 'Update PHP settings for a domain',
        tags: ['PHP'],
        parameters: [
            new OA\Parameter(
                name: 'domain',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'memory_limit', type: 'string', example: '512M'),
                    new OA\Property(property: 'upload_max_filesize', type: 'string', example: '128M'),
                    new OA\Property(property: 'post_max_size', type: 'string', example: '128M'),
                    new OA\Property(property: 'max_execution_time', type: 'integer', example: 60),
                    new OA\Property(property: 'max_input_time', type: 'integer', example: 120),
                    new OA\Property(property: 'display_errors', type: 'string', example: 'off'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'PHP settings updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Domain not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function updateSettings(UpdatePhpSettingsRequest $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $validated = $request->validated();

        try {
            // Use domain-specific PHP settings update instead of user-based
            $this->phpFpmService->updateDomainPhpSettings($domain, $validated);

            return response()->json([
                'success' => true,
                'message' => 'PHP settings updated successfully',
                'data' => [
                    'domain' => $domain->name,
                    'php_version' => $domain->php_version,
                    'settings' => $validated,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PHP_SETTINGS_UPDATE_FAILED',
                    'message' => 'Failed to update PHP settings: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get PHP-FPM service status.
     */
    #[OA\Get(
        path: '/api/v1/php/{version}/status',
        operationId: 'getPhpFpmStatus',
        summary: 'Get PHP-FPM service status',
        tags: ['PHP'],
        parameters: [
            new OA\Parameter(
                name: 'version',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: '8.3')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'PHP-FPM status'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function status(string $version): JsonResponse
    {
        $status = $this->phpFpmService->getServiceStatus($version);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }
}
