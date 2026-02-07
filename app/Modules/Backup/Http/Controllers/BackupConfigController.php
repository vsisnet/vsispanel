<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Backup\Http\Requests\StoreBackupConfigRequest;
use App\Modules\Backup\Http\Requests\UpdateBackupConfigRequest;
use App\Modules\Backup\Http\Resources\BackupConfigResource;
use App\Modules\Backup\Models\BackupConfig;
use App\Modules\Backup\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupConfigController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService
    ) {}

    /**
     * List all backup configurations
     */
    public function index(Request $request): JsonResponse
    {
        $configs = BackupConfig::query()
            ->where('user_id', $request->user()->id)
            ->withCount('backups')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => BackupConfigResource::collection($configs),
        ]);
    }

    /**
     * Create a new backup configuration
     */
    public function store(StoreBackupConfigRequest $request): JsonResponse
    {
        $config = BackupConfig::create([
            'user_id' => $request->user()->id,
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'backup_items' => $request->input('backup_items'),
            'destination_type' => $request->input('destination_type'),
            'destinations' => $request->input('destinations'),
            'storage_remote_id' => $request->input('storage_remote_id'),
            'destination_config' => $request->input('destination_config'),
            'schedule' => $request->input('schedule'),
            'schedule_time' => $request->input('schedule_time'),
            'schedule_day' => $request->input('schedule_day'),
            'schedule_cron' => $request->input('schedule_cron'),
            'retention_policy' => $request->input('retention_policy'),
            'include_paths' => $request->input('include_paths'),
            'exclude_patterns' => $request->input('exclude_patterns'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        // Calculate next run time if schedule is set
        if ($config->schedule) {
            $config->updateNextRunAt();
        }

        // Initialize repository
        $initResult = $this->backupService->initRepository($config);

        return response()->json([
            'success' => true,
            'data' => new BackupConfigResource($config),
            'message' => __('backup.config_created'),
            'repository_initialized' => $initResult['success'],
        ], 201);
    }

    /**
     * Show a backup configuration
     */
    public function show(BackupConfig $backupConfig): JsonResponse
    {
        $backupConfig->loadCount('backups');

        return response()->json([
            'success' => true,
            'data' => new BackupConfigResource($backupConfig),
        ]);
    }

    /**
     * Update a backup configuration
     */
    public function update(UpdateBackupConfigRequest $request, BackupConfig $backupConfig): JsonResponse
    {
        $data = $request->validated();

        // Handle destination config merge
        if (isset($data['destination_config'])) {
            $existingConfig = $backupConfig->destination_config;
            $data['destination_config'] = array_merge($existingConfig, $data['destination_config']);
        }

        $backupConfig->update($data);

        // Update next run time if schedule changed
        if ($request->has('schedule')) {
            $backupConfig->updateNextRunAt();
        }

        return response()->json([
            'success' => true,
            'data' => new BackupConfigResource($backupConfig),
            'message' => __('backup.config_updated'),
        ]);
    }

    /**
     * Delete a backup configuration
     */
    public function destroy(BackupConfig $backupConfig): JsonResponse
    {
        $backupConfig->delete();

        return response()->json([
            'success' => true,
            'message' => __('backup.config_deleted'),
        ]);
    }

    /**
     * Test connection to backup destination
     */
    public function testConnection(BackupConfig $backupConfig): JsonResponse
    {
        $result = $this->backupService->checkRepository($backupConfig);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success']
                ? __('backup.connection_success')
                : __('backup.connection_failed'),
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * Initialize repository
     */
    public function initRepository(BackupConfig $backupConfig): JsonResponse
    {
        $result = $this->backupService->initRepository($backupConfig);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success']
                ? __('backup.repository_initialized')
                : __('backup.repository_init_failed'),
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * Get repository statistics
     */
    public function stats(BackupConfig $backupConfig): JsonResponse
    {
        $result = $this->backupService->getStats($backupConfig);

        return response()->json([
            'success' => $result['success'],
            'data' => $result['stats'] ?? null,
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * List snapshots in repository
     */
    public function snapshots(BackupConfig $backupConfig): JsonResponse
    {
        $result = $this->backupService->listSnapshots($backupConfig);

        return response()->json([
            'success' => $result['success'],
            'data' => $result['snapshots'] ?? [],
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * Toggle active status
     */
    public function toggle(BackupConfig $backupConfig): JsonResponse
    {
        $backupConfig->update([
            'is_active' => !$backupConfig->is_active,
        ]);

        return response()->json([
            'success' => true,
            'data' => new BackupConfigResource($backupConfig),
            'message' => $backupConfig->is_active
                ? __('backup.config_enabled')
                : __('backup.config_disabled'),
        ]);
    }
}
