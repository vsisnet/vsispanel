<?php

declare(strict_types=1);

namespace App\Modules\Database\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Database\Http\Requests\CreateDatabaseRequest;
use App\Modules\Database\Http\Requests\ImportDatabaseRequest;
use App\Modules\Database\Http\Resources\ManagedDatabaseResource;
use App\Modules\Database\Http\Resources\ManagedDatabaseCollection;
use App\Modules\Database\Models\ManagedDatabase;
use App\Modules\Database\Services\DatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Databases', description: 'Database management endpoints')]
class DatabaseController extends Controller
{
    public function __construct(
        protected DatabaseService $databaseService
    ) {}

    /**
     * List all databases.
     */
    #[OA\Get(
        path: '/api/v1/databases',
        operationId: 'listDatabases',
        summary: 'List all databases for the user',
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'suspended']))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Database list'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function index(Request $request): ManagedDatabaseCollection
    {
        $user = $request->user();
        $query = ManagedDatabase::with(['user', 'domain', 'databaseUsers'])
            ->forUser($user);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('domain_id')) {
            $query->where('domain_id', $request->domain_id);
        }

        $databases = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return new ManagedDatabaseCollection($databases);
    }

    /**
     * Create a new database.
     */
    #[OA\Post(
        path: '/api/v1/databases',
        operationId: 'createDatabase',
        summary: 'Create a new database',
        tags: ['Databases'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'mydb'),
                    new OA\Property(property: 'domain_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'charset', type: 'string', example: 'utf8mb4'),
                    new OA\Property(property: 'collation', type: 'string', example: 'utf8mb4_unicode_ci')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Database created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(CreateDatabaseRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $domain = null;
            if (!empty($validated['domain_id'])) {
                $domain = $user->domains()->findOrFail($validated['domain_id']);
            }

            $database = $this->databaseService->createDatabase(
                $user,
                $validated['name'],
                $domain,
                [
                    'charset' => $validated['charset'] ?? 'utf8mb4',
                    'collation' => $validated['collation'] ?? 'utf8mb4_unicode_ci',
                ]
            );

            // Create database user if requested
            $dbUser = null;
            if (!empty($validated['create_user']) && !empty($validated['username']) && !empty($validated['password'])) {
                $dbUser = $this->databaseService->createDatabaseUser(
                    $user,
                    $validated['username'],
                    $validated['password']
                );

                // Grant access to the newly created database
                $this->databaseService->grantAccess($dbUser, $database);
            }

            // Reload database with users
            $database->load('databaseUsers');

            $message = 'Database created successfully.';
            if ($dbUser) {
                $message = 'Database and user created successfully.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new ManagedDatabaseResource($database),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DATABASE_CREATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Get a specific database.
     */
    #[OA\Get(
        path: '/api/v1/databases/{database}',
        operationId: 'getDatabase',
        summary: 'Get database details',
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(name: 'database', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Database details'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function show(ManagedDatabase $database): JsonResponse
    {
        $this->authorize('view', $database);

        $database->load(['user', 'domain', 'databaseUsers']);

        // Update size
        $this->databaseService->getDatabaseSize($database);
        $database->refresh();

        return response()->json([
            'success' => true,
            'data' => new ManagedDatabaseResource($database),
        ]);
    }

    /**
     * Delete a database.
     */
    #[OA\Delete(
        path: '/api/v1/databases/{database}',
        operationId: 'deleteDatabase',
        summary: 'Delete a database',
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(name: 'database', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Database deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function destroy(ManagedDatabase $database): JsonResponse
    {
        $this->authorize('delete', $database);

        try {
            $this->databaseService->deleteDatabase($database);

            return response()->json([
                'success' => true,
                'message' => 'Database deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DATABASE_DELETE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get database size.
     */
    #[OA\Get(
        path: '/api/v1/databases/{database}/size',
        operationId: 'getDatabaseSize',
        summary: 'Get database size',
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(name: 'database', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Database size'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function size(ManagedDatabase $database): JsonResponse
    {
        $this->authorize('view', $database);

        $sizeBytes = $this->databaseService->getDatabaseSize($database);

        return response()->json([
            'success' => true,
            'data' => [
                'size_bytes' => $sizeBytes,
                'size_formatted' => $database->size_formatted,
            ],
        ]);
    }

    /**
     * Get database tables.
     */
    #[OA\Get(
        path: '/api/v1/databases/{database}/tables',
        operationId: 'getDatabaseTables',
        summary: 'Get database tables',
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(name: 'database', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Database tables'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function tables(ManagedDatabase $database): JsonResponse
    {
        $this->authorize('view', $database);

        $tables = $this->databaseService->getDatabaseTables($database);

        return response()->json([
            'success' => true,
            'data' => $tables,
        ]);
    }

    /**
     * Backup a database.
     */
    #[OA\Post(
        path: '/api/v1/databases/{database}/backup',
        operationId: 'backupDatabase',
        summary: 'Create database backup',
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(name: 'database', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Backup created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function backup(ManagedDatabase $database): JsonResponse
    {
        $this->authorize('update', $database);

        try {
            $backupPath = $this->databaseService->backupDatabase($database);

            return response()->json([
                'success' => true,
                'message' => 'Database backup created successfully.',
                'data' => [
                    'backup_path' => $backupPath,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BACKUP_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Import SQL to a database.
     */
    #[OA\Post(
        path: '/api/v1/databases/{database}/import',
        operationId: 'importDatabase',
        summary: 'Import SQL file to database',
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(name: 'database', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Import successful'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function import(ImportDatabaseRequest $request, ManagedDatabase $database): JsonResponse
    {
        $this->authorize('update', $database);

        try {
            $file = $request->file('file');

            // Preserve original extension when storing
            $extension = strtolower($file->getClientOriginalExtension());
            $filename = uniqid('import_') . '.' . $extension;
            $tempPath = $file->storeAs('temp/database-imports', $filename);

            // Get full path using Storage facade to respect disk configuration
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($tempPath);

            $this->databaseService->importSql($database, $fullPath);

            // Clean up temp file
            \Illuminate\Support\Facades\Storage::disk('local')->delete($tempPath);

            return response()->json([
                'success' => true,
                'message' => 'SQL import completed successfully.',
            ]);
        } catch (\Exception $e) {
            // Clean up temp file on error
            if (isset($tempPath)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($tempPath);
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'IMPORT_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Change MySQL root password.
     */
    #[OA\Post(
        path: '/api/v1/databases/root-password',
        operationId: 'changeRootPassword',
        summary: 'Change MySQL root password',
        tags: ['Databases'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', example: 'newSecurePassword123')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password changed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function changeRootPassword(Request $request): JsonResponse
    {
        // Only admins can change root password
        $this->authorize('admin', ManagedDatabase::class);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            $this->databaseService->changeRootPassword($validated['password']);

            return response()->json([
                'success' => true,
                'message' => 'Root password changed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PASSWORD_CHANGE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get databases from MySQL server (for sync).
     */
    #[OA\Get(
        path: '/api/v1/databases/server-databases',
        operationId: 'getServerDatabases',
        summary: 'Get databases from MySQL server',
        tags: ['Databases'],
        responses: [
            new OA\Response(response: 200, description: 'Server databases list'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden')
        ]
    )]
    public function serverDatabases(): JsonResponse
    {
        try {
            $databases = $this->databaseService->getServerDatabases();

            return response()->json([
                'success' => true,
                'data' => $databases,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FETCH_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Sync databases from MySQL server to panel.
     */
    #[OA\Post(
        path: '/api/v1/databases/sync-from-server',
        operationId: 'syncFromServer',
        summary: 'Sync databases from MySQL server to panel',
        tags: ['Databases'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['databases'],
                properties: [
                    new OA\Property(property: 'databases', type: 'array', items: new OA\Items(type: 'string'))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Sync completed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function syncFromServer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'databases' => ['required', 'array'],
            'databases.*' => ['required', 'string'],
        ]);

        $user = $request->user();

        try {
            $result = $this->databaseService->syncDatabasesFromServer($user, $validated['databases']);

            return response()->json([
                'success' => true,
                'message' => count($result['synced']) . ' database(s) synced successfully.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SYNC_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get phpMyAdmin URL.
     */
    #[OA\Get(
        path: '/api/v1/databases/phpmyadmin-url',
        operationId: 'getPhpMyAdminUrl',
        summary: 'Get phpMyAdmin URL',
        tags: ['Databases'],
        responses: [
            new OA\Response(response: 200, description: 'phpMyAdmin URL'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function phpMyAdminUrl(): JsonResponse
    {
        $url = $this->databaseService->getPhpMyAdminUrl();

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
            ],
        ]);
    }

    /**
     * Get phpMyAdmin auto-login URL for a database.
     */
    #[OA\Get(
        path: '/api/v1/databases/{database}/phpmyadmin-sso',
        operationId: 'getPhpMyAdminSso',
        summary: 'Get phpMyAdmin auto-login URL for a database',
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(name: 'database', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'phpMyAdmin SSO URL'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'No database user with stored password')
        ]
    )]
    public function phpMyAdminSso(ManagedDatabase $database): JsonResponse
    {
        $this->authorize('view', $database);

        // Load database users
        $database->load('databaseUsers');

        // Find a database user with stored password
        $dbUser = null;
        foreach ($database->databaseUsers as $user) {
            if ($user->getDecryptedPassword()) {
                $dbUser = $user;
                break;
            }
        }

        if (!$dbUser) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_STORED_PASSWORD',
                    'message' => 'No database user with stored credentials found. Auto-login is only available for users created through the panel.',
                ],
            ], 422);
        }

        try {
            $ssoUrl = $this->databaseService->getPhpMyAdminSsoUrl($dbUser, $database);

            return response()->json([
                'success' => true,
                'data' => [
                    'sso_url' => $ssoUrl,
                    'username' => $dbUser->username,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SSO_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
