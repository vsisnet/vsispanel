<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Backup\Models\StorageRemote;
use App\Modules\Backup\Services\RcloneService;
use App\Modules\Security\Services\AuditLogService;
use App\Modules\Security\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StorageRemoteController extends Controller
{
    public function __construct(
        private readonly RcloneService $rcloneService,
        private readonly AuditLogService $auditService
    ) {}

    /**
     * Get rclone status
     */
    public function rcloneStatus(): JsonResponse
    {
        $isInstalled = $this->rcloneService->isInstalled();

        if (!$isInstalled) {
            return response()->json([
                'success' => true,
                'data' => [
                    'installed' => false,
                    'version' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'installed' => true,
                'version' => $this->rcloneService->getVersion(),
            ],
        ]);
    }

    /**
     * Install rclone
     */
    public function installRclone(): JsonResponse
    {
        if ($this->rcloneService->isInstalled()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ALREADY_INSTALLED',
                    'message' => __('backup.rclone_already_installed'),
                ],
            ], 400);
        }

        $result = $this->rcloneService->install();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSTALL_FAILED',
                    'message' => $result['error'] ?? __('backup.rclone_install_failed'),
                ],
            ], 500);
        }

        $this->auditService->log(
            AuditLog::ACTION_CREATE,
            'backup',
            'rclone',
            null,
            'Rclone installed'
        );

        return response()->json([
            'success' => true,
            'message' => __('backup.rclone_installed'),
        ]);
    }

    /**
     * Get supported remote types
     */
    public function getTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->rcloneService->getSupportedTypes(),
        ]);
    }

    /**
     * List all storage remotes
     */
    public function index(): JsonResponse
    {
        $remotes = StorageRemote::orderBy('display_name')->get();

        return response()->json([
            'success' => true,
            'data' => $remotes->map(fn($remote) => [
                'id' => $remote->id,
                'name' => $remote->name,
                'display_name' => $remote->display_name,
                'type' => $remote->type,
                'type_label' => $remote->type_label,
                'is_active' => $remote->is_active,
                'last_tested_at' => $remote->last_tested_at?->toISOString(),
                'last_test_result' => $remote->last_test_result,
                'created_at' => $remote->created_at->toISOString(),
            ]),
        ]);
    }

    /**
     * Create a new storage remote
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|regex:/^[a-zA-Z0-9_]+$/|unique:storage_remotes,name',
            'display_name' => 'required|string|max:100',
            'type' => 'required|string|in:ftp,sftp,drive,onedrive,dropbox,s3,b2,webdav',
            'config' => 'required|array',
            'config.host' => 'required_if:type,ftp,sftp,webdav|string|nullable',
            'config.user' => 'required_if:type,ftp,sftp,webdav|string|nullable',
            'config.pass' => 'required_if:type,ftp,sftp|string|nullable',
            'config.port' => 'nullable|integer|min:1|max:65535',
            'config.path' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        // Create remote in rclone
        $rcloneResult = $this->rcloneService->createRemote(
            'vsispanel_' . $request->input('name'),
            $request->input('type'),
            $request->input('config')
        );

        if (!$rcloneResult['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RCLONE_ERROR',
                    'message' => $rcloneResult['error'],
                ],
            ], 500);
        }

        // Store in database
        $remote = StorageRemote::create([
            'name' => $request->input('name'),
            'display_name' => $request->input('display_name'),
            'type' => $request->input('type'),
            'config' => $request->input('config'),
            'is_active' => true,
        ]);

        $this->auditService->log(
            AuditLog::ACTION_CREATE,
            'backup',
            'storage_remote',
            $remote->id,
            "Created storage remote: {$remote->display_name}"
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $remote->id,
                'name' => $remote->name,
                'display_name' => $remote->display_name,
                'type' => $remote->type,
                'type_label' => $remote->type_label,
                'is_active' => $remote->is_active,
            ],
            'message' => __('backup.remote_created'),
        ], 201);
    }

    /**
     * Get a specific storage remote
     */
    public function show(StorageRemote $storageRemote): JsonResponse
    {
        // Get config without sensitive fields
        $safeConfig = collect($storageRemote->config)->except(['pass', 'password', 'secret_access_key', 'key', 'token'])->all();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $storageRemote->id,
                'name' => $storageRemote->name,
                'display_name' => $storageRemote->display_name,
                'type' => $storageRemote->type,
                'type_label' => $storageRemote->type_label,
                'config' => $safeConfig,
                'is_active' => $storageRemote->is_active,
                'last_tested_at' => $storageRemote->last_tested_at?->toISOString(),
                'last_test_result' => $storageRemote->last_test_result,
                'created_at' => $storageRemote->created_at->toISOString(),
                'updated_at' => $storageRemote->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Update a storage remote
     */
    public function update(Request $request, StorageRemote $storageRemote): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'display_name' => 'sometimes|string|max:100',
            'config' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $validator->errors()->first(),
                ],
            ], 422);
        }

        // Update config if provided
        if ($request->has('config')) {
            $newConfig = $request->input('config');

            // For OAuth remotes (drive, onedrive, dropbox), the 'path' is only stored in our database
            // and is used when constructing the remote URL during sync operations.
            // We don't need to update rclone config for the path field.
            $isOAuthType = in_array($storageRemote->type, ['drive', 'onedrive', 'dropbox']);

            // Fields that should be updated in rclone config (not for OAuth remotes' path)
            $rcloneConfigFields = ['host', 'port', 'user', 'pass', 'key_file', 'access_key_id', 'secret_access_key', 'region', 'endpoint', 'bucket', 'account', 'key', 'url', 'vendor'];

            // Filter out fields that should go to rclone vs only database
            $rcloneUpdates = [];
            foreach ($newConfig as $key => $value) {
                if (in_array($key, $rcloneConfigFields) && !$isOAuthType) {
                    $rcloneUpdates[$key] = $value;
                }
            }

            // Only call rclone if there are actual rclone config updates
            if (!empty($rcloneUpdates)) {
                $rcloneResult = $this->rcloneService->updateRemote(
                    $storageRemote->getRcloneRemoteName(),
                    $rcloneUpdates
                );

                if (!$rcloneResult['success']) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'RCLONE_ERROR',
                            'message' => $rcloneResult['error'],
                        ],
                    ], 500);
                }
            }

            // Merge new config with existing (preserve existing values for empty fields)
            // This stores the 'path' and other settings in our database
            $filteredConfig = array_filter($newConfig, fn($v) => $v !== null && $v !== '');
            $storageRemote->config = array_merge($storageRemote->config ?? [], $filteredConfig);
        }

        if ($request->has('display_name')) {
            $storageRemote->display_name = $request->input('display_name');
        }

        if ($request->has('is_active')) {
            $storageRemote->is_active = $request->boolean('is_active');
        }

        $storageRemote->save();

        $this->auditService->log(
            AuditLog::ACTION_UPDATE,
            'backup',
            'storage_remote',
            $storageRemote->id,
            "Updated storage remote: {$storageRemote->display_name}"
        );

        return response()->json([
            'success' => true,
            'message' => __('backup.remote_updated'),
        ]);
    }

    /**
     * Delete a storage remote
     */
    public function destroy(StorageRemote $storageRemote): JsonResponse
    {
        // Check if remote is used by any backup configs
        if ($storageRemote->backupConfigs()->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'IN_USE',
                    'message' => __('backup.remote_in_use'),
                ],
            ], 400);
        }

        // Delete from rclone
        $this->rcloneService->deleteRemote($storageRemote->getRcloneRemoteName());

        $displayName = $storageRemote->display_name;
        $storageRemote->delete();

        $this->auditService->log(
            AuditLog::ACTION_DELETE,
            'backup',
            'storage_remote',
            null,
            "Deleted storage remote: {$displayName}"
        );

        return response()->json([
            'success' => true,
            'message' => __('backup.remote_deleted'),
        ]);
    }

    /**
     * Test storage remote connection
     */
    public function testConnection(StorageRemote $storageRemote): JsonResponse
    {
        $result = $this->rcloneService->testConnection($storageRemote->getRcloneRemoteName());

        $storageRemote->update([
            'last_tested_at' => now(),
            'last_test_result' => $result['success'],
        ]);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ]);
    }

    /**
     * Get remote space info
     */
    public function getSpace(StorageRemote $storageRemote): JsonResponse
    {
        $space = $this->rcloneService->getRemoteSpace($storageRemote->getRcloneRemoteName());

        return response()->json([
            'success' => true,
            'data' => $space,
        ]);
    }
}
