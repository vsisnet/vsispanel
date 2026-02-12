<?php

declare(strict_types=1);

namespace App\Modules\Migration\Http\Controllers;

use App\Modules\Base\Http\Controllers\ApiController;
use App\Modules\Migration\Models\MigrationJob;
use App\Modules\Migration\Services\MigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MigrationController extends ApiController
{
    public function __construct(
        protected MigrationService $migrationService
    ) {}

    /**
     * List migration jobs.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $jobs = MigrationJob::query()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse($jobs);
    }

    /**
     * Get a specific migration job.
     */
    public function show(string $id): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $job = MigrationJob::with('user:id,name,email')->findOrFail($id);

        return $this->successResponse([
            'id' => $job->id,
            'status' => $job->status,
            'source_type' => $job->source_type,
            'source_host' => $job->source_host,
            'source_port' => $job->source_port,
            'items' => $job->items,
            'discovered_data' => $job->discovered_data,
            'progress' => $job->progress,
            'log' => $job->log,
            'user' => $job->user,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
            'created_at' => $job->created_at,
        ]);
    }

    /**
     * Test connection to source server.
     */
    public function testConnection(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $request->validate([
            'source_type' => 'required|in:plesk,cpanel,aapanel,directadmin,ssh',
            'host' => 'required|string',
            'port' => 'nullable|integer|min:1|max:65535',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'private_key' => 'nullable|string',
            'api_key' => 'nullable|string',
        ]);

        $credentials = [
            'host' => $request->input('host'),
            'port' => $request->integer('port', 22),
            'username' => $request->input('username', 'root'),
            'password' => $request->input('password'),
            'private_key' => $request->input('private_key'),
            'api_key' => $request->input('api_key'),
        ];

        $result = $this->migrationService->testConnection(
            $request->input('source_type'),
            $credentials
        );

        return $this->successResponse($result);
    }

    /**
     * Discover accounts/domains on source server.
     */
    public function discover(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $request->validate([
            'source_type' => 'required|in:plesk,cpanel,aapanel,directadmin,ssh',
            'host' => 'required|string',
            'port' => 'nullable|integer|min:1|max:65535',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'private_key' => 'nullable|string',
            'api_key' => 'nullable|string',
        ]);

        $credentials = [
            'host' => $request->input('host'),
            'port' => $request->integer('port', 22),
            'username' => $request->input('username', 'root'),
            'password' => $request->input('password'),
            'private_key' => $request->input('private_key'),
            'api_key' => $request->input('api_key'),
        ];

        $result = $this->migrationService->discover(
            $request->input('source_type'),
            $credentials
        );

        return $this->successResponse($result);
    }

    /**
     * Start a migration job.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $request->validate([
            'source_type' => 'required|in:plesk,cpanel,aapanel,directadmin,ssh',
            'source_host' => 'required|string',
            'source_port' => 'nullable|integer|min:1|max:65535',
            'credentials' => 'required|array',
            'items' => 'required|array',
            'discovered_data' => 'nullable|array',
        ]);

        $job = $this->migrationService->createJob([
            'user_id' => $this->user()->id,
            'source_type' => $request->input('source_type'),
            'source_host' => $request->input('source_host'),
            'source_port' => $request->integer('source_port', 22),
            'credentials' => $request->input('credentials'),
            'items' => $request->input('items'),
            'discovered_data' => $request->input('discovered_data'),
        ]);

        return $this->successResponse([
            'id' => $job->id,
            'status' => $job->status,
        ], 'Migration job created', 201);
    }

    /**
     * Cancel a migration job.
     */
    public function cancel(string $id): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $job = MigrationJob::findOrFail($id);
        $this->migrationService->cancelJob($job);

        return $this->successResponse(['status' => $job->fresh()->status], 'Migration job cancelled');
    }

    /**
     * Delete a migration job.
     */
    public function destroy(string $id): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $job = MigrationJob::findOrFail($id);

        if ($job->isRunning()) {
            return $this->errorResponse('Cannot delete a running migration job', 422);
        }

        $job->delete();

        return $this->successResponse(null, 'Migration job deleted');
    }
}
