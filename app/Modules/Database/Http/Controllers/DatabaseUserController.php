<?php

declare(strict_types=1);

namespace App\Modules\Database\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Database\Http\Requests\CreateDatabaseUserRequest;
use App\Modules\Database\Http\Requests\GrantAccessRequest;
use App\Modules\Database\Http\Resources\DatabaseUserResource;
use App\Modules\Database\Http\Resources\DatabaseUserCollection;
use App\Modules\Database\Models\DatabaseUser;
use App\Modules\Database\Models\ManagedDatabase;
use App\Modules\Database\Services\DatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Database Users', description: 'Database user management endpoints')]
class DatabaseUserController extends Controller
{
    public function __construct(
        protected DatabaseService $databaseService
    ) {}

    /**
     * List all database users.
     */
    #[OA\Get(
        path: '/api/v1/database-users',
        operationId: 'listDatabaseUsers',
        summary: 'List all database users',
        tags: ['Database Users'],
        responses: [
            new OA\Response(response: 200, description: 'Database users list'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function index(Request $request): DatabaseUserCollection
    {
        $user = $request->user();
        $dbUsers = DatabaseUser::with(['databases'])
            ->forUser($user)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return new DatabaseUserCollection($dbUsers);
    }

    /**
     * Create a new database user.
     */
    #[OA\Post(
        path: '/api/v1/database-users',
        operationId: 'createDatabaseUser',
        summary: 'Create a new database user',
        tags: ['Database Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'dbuser'),
                    new OA\Property(property: 'password', type: 'string', example: 'securePassword123'),
                    new OA\Property(property: 'host', type: 'string', example: 'localhost')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Database user created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(CreateDatabaseUserRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $dbUser = $this->databaseService->createDatabaseUser(
                $user,
                $validated['username'],
                $validated['password'],
                $validated['host'] ?? 'localhost'
            );

            return response()->json([
                'success' => true,
                'message' => 'Database user created successfully.',
                'data' => new DatabaseUserResource($dbUser),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'USER_CREATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Get a specific database user.
     */
    #[OA\Get(
        path: '/api/v1/database-users/{databaseUser}',
        operationId: 'getDatabaseUser',
        summary: 'Get database user details',
        tags: ['Database Users'],
        parameters: [
            new OA\Parameter(name: 'databaseUser', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Database user details'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function show(DatabaseUser $databaseUser): JsonResponse
    {
        $this->authorize('view', $databaseUser);

        $databaseUser->load('databases');

        return response()->json([
            'success' => true,
            'data' => new DatabaseUserResource($databaseUser),
        ]);
    }

    /**
     * Delete a database user.
     */
    #[OA\Delete(
        path: '/api/v1/database-users/{databaseUser}',
        operationId: 'deleteDatabaseUser',
        summary: 'Delete a database user',
        tags: ['Database Users'],
        parameters: [
            new OA\Parameter(name: 'databaseUser', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Database user deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function destroy(DatabaseUser $databaseUser): JsonResponse
    {
        $this->authorize('delete', $databaseUser);

        try {
            $this->databaseService->deleteDatabaseUser($databaseUser);

            return response()->json([
                'success' => true,
                'message' => 'Database user deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'USER_DELETE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Change database user password.
     */
    #[OA\Put(
        path: '/api/v1/database-users/{databaseUser}/password',
        operationId: 'changeDatabaseUserPassword',
        summary: 'Change database user password',
        tags: ['Database Users'],
        parameters: [
            new OA\Parameter(name: 'databaseUser', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', example: 'newSecurePassword')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password changed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function changePassword(Request $request, DatabaseUser $databaseUser): JsonResponse
    {
        $this->authorize('update', $databaseUser);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            $this->databaseService->changeDatabaseUserPassword($databaseUser, $validated['password']);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
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
     * Grant access to a database.
     */
    #[OA\Post(
        path: '/api/v1/database-users/{databaseUser}/grant',
        operationId: 'grantDatabaseAccess',
        summary: 'Grant database access to user',
        tags: ['Database Users'],
        parameters: [
            new OA\Parameter(name: 'databaseUser', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['database_id'],
                properties: [
                    new OA\Property(property: 'database_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'privileges', type: 'array', items: new OA\Items(type: 'string'))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Access granted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function grant(GrantAccessRequest $request, DatabaseUser $databaseUser): JsonResponse
    {
        $this->authorize('update', $databaseUser);

        $validated = $request->validated();
        $database = ManagedDatabase::findOrFail($validated['database_id']);

        // Ensure user owns both the db user and the database
        if ($database->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not own this database.',
                ],
            ], 403);
        }

        try {
            $this->databaseService->grantAccess(
                $databaseUser,
                $database,
                $validated['privileges'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Access granted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'GRANT_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Revoke access from a database.
     */
    #[OA\Post(
        path: '/api/v1/database-users/{databaseUser}/revoke',
        operationId: 'revokeDatabaseAccess',
        summary: 'Revoke database access from user',
        tags: ['Database Users'],
        parameters: [
            new OA\Parameter(name: 'databaseUser', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['database_id'],
                properties: [
                    new OA\Property(property: 'database_id', type: 'string', format: 'uuid')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Access revoked'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function revoke(Request $request, DatabaseUser $databaseUser): JsonResponse
    {
        $this->authorize('update', $databaseUser);

        $validated = $request->validate([
            'database_id' => ['required', 'uuid', 'exists:managed_databases,id'],
        ]);

        $database = ManagedDatabase::findOrFail($validated['database_id']);

        try {
            $this->databaseService->revokeAccess($databaseUser, $database);

            return response()->json([
                'success' => true,
                'message' => 'Access revoked successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REVOKE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get available privileges.
     */
    #[OA\Get(
        path: '/api/v1/database-users/privileges',
        operationId: 'getDatabasePrivileges',
        summary: 'Get available database privileges',
        tags: ['Database Users'],
        responses: [
            new OA\Response(response: 200, description: 'Available privileges'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function privileges(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'privileges' => $this->databaseService->getAllPrivileges(),
                'defaults' => $this->databaseService->getDefaultPrivileges(),
            ],
        ]);
    }
}
