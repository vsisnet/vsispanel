<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Backup\Http\Requests\CreateBackupRequest;
use App\Modules\Backup\Http\Requests\RestoreBackupRequest;
use App\Modules\Backup\Http\Resources\BackupResource;
use App\Modules\Backup\Http\Resources\RestoreOperationResource;
use App\Modules\Backup\Jobs\BackupJob;
use App\Modules\Backup\Jobs\RestoreJob;
use App\Modules\Backup\Models\Backup;
use App\Modules\Backup\Models\BackupConfig;
use App\Modules\Backup\Models\RestoreOperation;
use App\Modules\Backup\Services\BackupService;
use App\Modules\Backup\Services\RcloneService;
use App\Modules\Backup\Models\StorageRemote;
use App\Modules\Database\Models\ManagedDatabase;
use App\Modules\Domain\Models\Domain;
use App\Modules\Mail\Models\MailAccount;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly TaskService $taskService,
        private readonly RcloneService $rcloneService
    ) {}

    /**
     * List all backups
     */
    public function index(Request $request): JsonResponse
    {
        $query = Backup::query()
            ->where('user_id', $request->user()->id)
            ->with(['backupConfig', 'storageRemote'])
            ->orderBy('created_at', 'desc');

        // Filter by config
        if ($request->has('config_id')) {
            $query->where('backup_config_id', $request->input('config_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by storage remote
        if ($request->has('storage_remote_id')) {
            $storageRemoteId = $request->input('storage_remote_id');
            if ($storageRemoteId === 'local' || empty($storageRemoteId)) {
                // Only show local backups (no remote or null)
                $query->whereNull('storage_remote_id');
            } else {
                // Show backups for specific remote
                $query->where('storage_remote_id', $storageRemoteId);
            }
        }

        $backups = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => BackupResource::collection($backups),
            'meta' => [
                'current_page' => $backups->currentPage(),
                'last_page' => $backups->lastPage(),
                'per_page' => $backups->perPage(),
                'total' => $backups->total(),
            ],
        ]);
    }

    /**
     * Create a new backup (manual trigger)
     */
    public function store(CreateBackupRequest $request): JsonResponse
    {
        $config = BackupConfig::findOrFail($request->input('backup_config_id'));

        // Check if there's already a running backup
        $hasRunning = Backup::query()
            ->where('backup_config_id', $config->id)
            ->where('status', Backup::STATUS_RUNNING)
            ->exists();

        if ($hasRunning) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BACKUP_ALREADY_RUNNING',
                    'message' => __('backup.already_running'),
                ],
            ], 409);
        }

        // Create backup record
        $backup = Backup::create([
            'user_id' => $request->user()->id,
            'backup_config_id' => $config->id,
            'type' => $request->input('type', $config->type),
            'status' => Backup::STATUS_PENDING,
        ]);

        // Create task for tracking
        $destinations = $config->destinations ?? ['local'];
        $destinationCount = count($destinations);
        $destinationLabel = $destinationCount > 1
            ? "{$destinationCount} destinations"
            : ($destinations[0] === 'local' ? 'Local Storage' : 'Remote Storage');

        $task = $this->taskService->create(
            type: Task::TYPE_BACKUP_CREATE,
            name: "Backup: {$config->name}",
            description: "Creating {$config->type} backup to {$destinationLabel}",
            inputData: [
                'backup_id' => $backup->id,
                'config_id' => $config->id,
                'type' => $config->type,
                'destinations' => $destinations,
            ],
            relatedType: Backup::class,
            relatedId: $backup->id
        );

        // Dispatch backup job with task ID
        BackupJob::dispatch($backup, $task->id);

        return response()->json([
            'success' => true,
            'data' => new BackupResource($backup),
            'task_id' => $task->id,
            'message' => __('backup.backup_started'),
        ], 201);
    }

    /**
     * Show a backup (includes soft-deleted backups for remote restore)
     */
    public function show(Request $request, string $backupId): JsonResponse
    {
        // Include soft-deleted backups since they may still exist on remote storage
        $backup = Backup::withTrashed()
            ->with('backupConfig', 'storageRemote')
            ->find($backupId);

        if (!$backup) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BACKUP_NOT_FOUND',
                    'message' => __('backup.not_found'),
                ],
            ], 404);
        }

        // Verify user owns this backup
        if ($backup->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => __('common.unauthorized'),
                ],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new BackupResource($backup),
        ]);
    }

    /**
     * Delete a backup
     */
    public function destroy(Backup $backup): JsonResponse
    {
        // Collect cleanup info before soft-deleting
        $snapshotId = $backup->snapshot_id;
        $configId = $backup->backup_config_id;
        $metadata = $backup->metadata ?? [];
        $localArchivePath = $metadata['local_archive']['path'] ?? null;
        $archiveName = $metadata['archive']['name'] ?? null;
        $syncedRemotes = $backup->synced_remotes ?? [];
        $backupId = $backup->id;
        $isCompleted = $backup->isCompleted();

        // Soft-delete the record first so UI updates instantly
        $backup->delete();

        // Dispatch cleanup to queue (restic forget, local archive, remote archives)
        dispatch(function () use ($snapshotId, $configId, $localArchivePath, $archiveName, $syncedRemotes, $backupId, $isCompleted) {
            if ($isCompleted && $snapshotId && $configId) {
                $config = BackupConfig::find($configId);
                if ($config) {
                    app(BackupService::class)->deleteSnapshot($config, $snapshotId);
                }
            }

            if ($localArchivePath && file_exists($localArchivePath)) {
                @unlink($localArchivePath);
            }

            if ($archiveName && !empty($syncedRemotes)) {
                $rcloneService = app(RcloneService::class);
                foreach ($syncedRemotes as $remoteId) {
                    try {
                        $remote = StorageRemote::find($remoteId);
                        if ($remote) {
                            $rcloneName = $remote->getRcloneRemoteName();
                            $basePath = trim($remote->config['path'] ?? '/backups', '/');
                            $rcloneService->deleteOnRemote("{$rcloneName}:{$basePath}/{$archiveName}");
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Failed to delete remote archive', [
                            'backup_id' => $backupId,
                            'remote_id' => $remoteId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        })->onQueue('default');

        return response()->json([
            'success' => true,
            'message' => __('backup.backup_deleted'),
        ]);
    }

    /**
     * Restore from a backup
     */
    public function restore(RestoreBackupRequest $request, string $backupId): JsonResponse
    {
        // Include soft-deleted backups since the snapshot may still exist
        $backup = Backup::withTrashed()->find($backupId);

        if (!$backup) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BACKUP_NOT_FOUND',
                    'message' => __('backup.not_found'),
                ],
            ], 404);
        }

        // Verify user owns this backup
        if ($backup->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => __('common.unauthorized'),
                ],
            ], 403);
        }

        if (!$backup->isCompleted()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BACKUP_NOT_COMPLETED',
                    'message' => __('backup.not_completed'),
                ],
            ], 400);
        }

        if (!$backup->snapshot_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_SNAPSHOT_ID',
                    'message' => __('backup.no_snapshot'),
                ],
            ], 400);
        }

        $targetPath = $request->input('target_path');
        $includePaths = $request->input('include_paths', []);

        \Log::info('Restore request received', [
            'backup_id' => $backup->id,
            'target_path' => $targetPath,
            'include_paths' => $includePaths,
            'include_paths_count' => count($includePaths),
        ]);

        // Create restore operation record
        $restoreOperation = RestoreOperation::create([
            'user_id' => $request->user()->id,
            'backup_id' => $backup->id,
            'status' => RestoreOperation::STATUS_PENDING,
            'target_path' => $targetPath,
            'include_paths' => $includePaths,
        ]);

        // Create task for tracking
        $pathCount = count($includePaths);
        $pathLabel = $pathCount > 0 ? "{$pathCount} paths" : 'full backup';

        $task = $this->taskService->create(
            type: Task::TYPE_BACKUP_RESTORE,
            name: "Restore: " . ($backup->backupConfig?->name ?? 'Backup'),
            description: "Restoring {$pathLabel} to {$targetPath}",
            inputData: [
                'restore_operation_id' => $restoreOperation->id,
                'backup_id' => $backup->id,
                'target_path' => $targetPath,
                'include_paths' => $includePaths,
            ],
            relatedType: RestoreOperation::class,
            relatedId: $restoreOperation->id
        );

        // Dispatch restore job with task ID
        RestoreJob::dispatch($restoreOperation, $task->id);

        return response()->json([
            'success' => true,
            'message' => __('backup.restore_started'),
            'task_id' => $task->id,
            'data' => [
                'restore_operation_id' => $restoreOperation->id,
            ],
        ]);
    }

    /**
     * Browse files in a backup snapshot (includes soft-deleted backups)
     */
    public function browse(Request $request, string $backupId): JsonResponse
    {
        $backup = Backup::withTrashed()->find($backupId);

        if (!$backup) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BACKUP_NOT_FOUND',
                    'message' => __('backup.not_found'),
                ],
            ], 404);
        }

        // Verify user owns this backup
        if ($backup->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => __('common.unauthorized'),
                ],
            ], 403);
        }

        if (!$backup->isCompleted() || !$backup->snapshot_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_BROWSE',
                    'message' => __('backup.cannot_browse'),
                ],
            ], 400);
        }

        $path = $request->input('path', '/');
        $result = $this->backupService->browseSnapshot($backup, $path);

        return response()->json([
            'success' => $result['success'],
            'data' => $result['files'] ?? [],
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * Get backup statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $stats = [
            'total_backups' => Backup::where('user_id', $userId)->count(),
            'completed_backups' => Backup::where('user_id', $userId)
                ->where('status', Backup::STATUS_COMPLETED)
                ->count(),
            'failed_backups' => Backup::where('user_id', $userId)
                ->where('status', Backup::STATUS_FAILED)
                ->count(),
            'running_backups' => Backup::where('user_id', $userId)
                ->where('status', Backup::STATUS_RUNNING)
                ->count(),
            'total_size' => Backup::where('user_id', $userId)
                ->where('status', Backup::STATUS_COMPLETED)
                ->sum('size_bytes'),
            'configs_count' => BackupConfig::where('user_id', $userId)->count(),
            'active_configs' => BackupConfig::where('user_id', $userId)
                ->where('is_active', true)
                ->count(),
        ];

        // Format total size
        $bytes = $stats['total_size'];
        if ($bytes > 0) {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $factor = floor((strlen((string)$bytes) - 1) / 3);
            $stats['total_size_formatted'] = sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
        } else {
            $stats['total_size_formatted'] = '0 B';
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get recent backups
     */
    public function recent(Request $request): JsonResponse
    {
        $backups = Backup::query()
            ->where('user_id', $request->user()->id)
            ->with('backupConfig')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => BackupResource::collection($backups),
        ]);
    }

    /**
     * Get objects (websites, databases, emails, config) from a backup (includes soft-deleted backups)
     */
    public function objects(Request $request, string $backupId): JsonResponse
    {
        $backup = Backup::withTrashed()->find($backupId);

        if (!$backup) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BACKUP_NOT_FOUND',
                    'message' => __('backup.not_found'),
                ],
            ], 404);
        }

        // Verify user owns this backup
        if ($backup->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => __('common.unauthorized'),
                ],
            ], 403);
        }

        if (!$backup->isCompleted() || !$backup->snapshot_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_LIST_OBJECTS',
                    'message' => __('backup.cannot_browse'),
                ],
            ], 400);
        }

        $type = $request->input('type', 'websites');
        $userId = $backup->user_id;
        $objects = [];

        switch ($type) {
            case 'websites':
                // Get domains from database with their document roots
                $domains = Domain::where('user_id', $userId)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($domains as $domain) {
                    $documentRoot = $domain->document_root ?: "/home/{$domain->name}/public_html";
                    $objects[] = [
                        'id' => $domain->id,
                        'path' => $documentRoot,
                        'name' => $domain->name,
                        'type' => 'website',
                        'description' => "Document root: {$documentRoot}",
                        'status' => $domain->status,
                    ];
                }

                // If no domains found, fallback to scanning directories
                if (empty($objects)) {
                    $objects = $this->scanBackupForWebsites($backup);
                }
                break;

            case 'databases':
                // Get databases from database
                $databases = ManagedDatabase::where('user_id', $userId)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($databases as $db) {
                    $objects[] = [
                        'id' => $db->id,
                        'path' => "/var/lib/mysql/{$db->name}",
                        'name' => $db->name,
                        'type' => 'database',
                        'description' => $db->charset ? "Charset: {$db->charset}" : null,
                        'size' => $db->size_bytes,
                    ];
                }

                // If no databases found, fallback to scanning
                if (empty($objects)) {
                    $objects = $this->scanBackupForDatabases($backup);
                }
                break;

            case 'emails':
                // Get email accounts from database
                $emailAccounts = MailAccount::where('user_id', $userId)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($emailAccounts as $email) {
                    // Email storage path varies, common paths are /var/vmail/domain/user or /var/mail/domain/user
                    $emailParts = explode('@', $email->email);
                    $localPart = $emailParts[0] ?? '';
                    $domainPart = $emailParts[1] ?? '';
                    $mailPath = "/var/vmail/{$domainPart}/{$localPart}";

                    $objects[] = [
                        'id' => $email->id,
                        'path' => $mailPath,
                        'name' => $email->email,
                        'type' => 'email',
                        'description' => "Quota: {$email->quota_mb} MB",
                        'size' => $email->quota_used_bytes,
                    ];
                }

                // If no emails found, fallback to scanning
                if (empty($objects)) {
                    $objects = $this->scanBackupForEmails($backup);
                }
                break;

            case 'config':
                // Configuration files - always show standard paths
                $configPaths = [
                    ['path' => '/etc/nginx', 'name' => 'Nginx Configuration', 'description' => 'Web server configuration files'],
                    ['path' => '/etc/apache2', 'name' => 'Apache Configuration', 'description' => 'Apache web server configuration'],
                    ['path' => '/etc/postfix', 'name' => 'Postfix Configuration', 'description' => 'Mail server (SMTP) configuration'],
                    ['path' => '/etc/dovecot', 'name' => 'Dovecot Configuration', 'description' => 'IMAP/POP3 server configuration'],
                    ['path' => '/etc/vsispanel', 'name' => 'Panel Configuration', 'description' => 'VSISPanel configuration files'],
                    ['path' => '/etc/mysql', 'name' => 'MySQL Configuration', 'description' => 'Database server configuration'],
                    ['path' => '/etc/php', 'name' => 'PHP Configuration', 'description' => 'PHP configuration files'],
                ];

                foreach ($configPaths as $config) {
                    $objects[] = [
                        'path' => $config['path'],
                        'name' => $config['name'],
                        'type' => 'config',
                        'description' => $config['description'],
                    ];
                }
                break;
        }

        return response()->json([
            'success' => true,
            'data' => $objects,
        ]);
    }

    /**
     * Scan backup for website directories (fallback)
     */
    private function scanBackupForWebsites(Backup $backup): array
    {
        $objects = [];
        $homeDirs = $this->backupService->browseSnapshot($backup, '/home');

        if ($homeDirs['success'] && !empty($homeDirs['files'])) {
            foreach ($homeDirs['files'] as $file) {
                if (($file['type'] ?? '') === 'dir') {
                    $skipDirs = ['lost+found', 'ubuntu', 'root'];
                    if (!in_array($file['name'], $skipDirs)) {
                        $objects[] = [
                            'path' => '/home/' . $file['name'],
                            'name' => $file['name'] . ' (User Directory)',
                            'type' => 'website',
                            'description' => 'User home directory',
                        ];
                    }
                }
            }
        }

        // Also check /var/www
        $wwwDirs = $this->backupService->browseSnapshot($backup, '/var/www');
        if ($wwwDirs['success'] && !empty($wwwDirs['files'])) {
            foreach ($wwwDirs['files'] as $file) {
                if (($file['type'] ?? '') === 'dir') {
                    $skipDirs = ['html', 'cgi-bin', 'error', 'icons'];
                    if (!in_array($file['name'], $skipDirs)) {
                        $objects[] = [
                            'path' => '/var/www/' . $file['name'],
                            'name' => $file['name'],
                            'type' => 'website',
                            'description' => 'Web directory',
                        ];
                    }
                }
            }
        }

        return $objects;
    }

    /**
     * Scan backup for database directories (fallback)
     */
    private function scanBackupForDatabases(Backup $backup): array
    {
        $objects = [];
        $result = $this->backupService->browseSnapshot($backup, '/var/lib/mysql');

        if ($result['success'] && !empty($result['files'])) {
            $skipDbs = ['mysql', 'performance_schema', 'information_schema', 'sys', '#innodb_redo', '#innodb_temp', 'lost+found'];
            foreach ($result['files'] as $file) {
                if (($file['type'] ?? '') === 'dir' && !in_array($file['name'], $skipDbs)) {
                    $objects[] = [
                        'path' => '/var/lib/mysql/' . $file['name'],
                        'name' => $file['name'],
                        'type' => 'database',
                        'description' => 'MySQL database',
                    ];
                }
            }
        }

        return $objects;
    }

    /**
     * Scan backup for email directories (fallback)
     */
    private function scanBackupForEmails(Backup $backup): array
    {
        $objects = [];

        // Check /var/vmail
        $vmailDirs = $this->backupService->browseSnapshot($backup, '/var/vmail');
        if ($vmailDirs['success'] && !empty($vmailDirs['files'])) {
            foreach ($vmailDirs['files'] as $domain) {
                if (($domain['type'] ?? '') === 'dir') {
                    $domainPath = '/var/vmail/' . $domain['name'];
                    // Get users under this domain
                    $users = $this->backupService->browseSnapshot($backup, $domainPath);
                    if ($users['success'] && !empty($users['files'])) {
                        foreach ($users['files'] as $user) {
                            if (($user['type'] ?? '') === 'dir') {
                                $objects[] = [
                                    'path' => $domainPath . '/' . $user['name'],
                                    'name' => $user['name'] . '@' . $domain['name'],
                                    'type' => 'email',
                                    'description' => 'Email mailbox',
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $objects;
    }

    /**
     * Get restore operation status
     */
    public function restoreStatus(Request $request, string $restoreOperationId): JsonResponse
    {
        $restoreOperation = RestoreOperation::where('user_id', $request->user()->id)
            ->where('id', $restoreOperationId)
            ->with('backup')
            ->first();

        if (!$restoreOperation) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => __('backup.restore_not_found'),
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new RestoreOperationResource($restoreOperation),
        ]);
    }

    /**
     * Get recent restore operations
     */
    public function recentRestores(Request $request): JsonResponse
    {
        $restoreOperations = RestoreOperation::query()
            ->where('user_id', $request->user()->id)
            ->with('backup')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => RestoreOperationResource::collection($restoreOperations),
        ]);
    }

    /**
     * List local backup archives
     * Shows individual backup files (compressed archives) stored locally
     */
    public function listLocalArchives(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $archiveDir = '/var/backups/vsispanel-archives';

        $archives = [];

        if (!is_dir($archiveDir)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'storage' => [
                    'type' => 'local',
                    'path' => $archiveDir,
                ],
            ]);
        }

        // Scan directory for .tar.gz files
        $files = scandir($archiveDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = "{$archiveDir}/{$file}";
            if (!is_file($filePath) || !str_ends_with($file, '.tar.gz')) {
                continue;
            }

            // Parse filename: {config_name}_{date}_{time}.tar.gz
            if (!preg_match('/^(.+)_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})\.tar\.gz$/', $file, $matches)) {
                continue;
            }

            $configName = $matches[1];
            $date = $matches[2];
            $time = str_replace('-', ':', $matches[3]);
            $datetime = "{$date} {$time}";

            $fileSize = filesize($filePath);
            $modifiedAt = date('c', filemtime($filePath));

            // Try to find matching backup in database
            $localBackup = Backup::withTrashed()
                ->where('user_id', $userId)
                ->where('status', Backup::STATUS_COMPLETED)
                ->whereJsonContains('metadata->archive->name', $file)
                ->first();

            // If not found by archive name, try to find by config name and date
            if (!$localBackup) {
                $config = BackupConfig::where('user_id', $userId)
                    ->whereRaw("REPLACE(REPLACE(name, ' ', '_'), '-', '_') = ?", [$configName])
                    ->first();

                if ($config) {
                    // Find backup closest to the archive date
                    $archiveDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', "{$date} " . str_replace('-', ':', $matches[3]));
                    $localBackup = Backup::withTrashed()
                        ->where('backup_config_id', $config->id)
                        ->where('user_id', $userId)
                        ->where('status', Backup::STATUS_COMPLETED)
                        ->whereBetween('created_at', [
                            $archiveDate->copy()->subMinutes(5),
                            $archiveDate->copy()->addMinutes(5),
                        ])
                        ->first();
                }
            } else {
                $config = $localBackup->backupConfig;
            }

            // Create display name with date/time (remove .tar.gz extension)
            $displayName = preg_replace('/\.tar\.gz$/', '', $file);

            $archives[] = [
                'id' => $localBackup?->id ?? $file,
                'archive_name' => $file,
                'local_path' => $filePath,
                'display_name' => $displayName,
                'backup_date' => $date,
                'backup_time' => $time,
                'datetime' => $datetime,
                'type' => $config?->type ?? 'full',
                'status' => 'local',
                'format' => 'archive',
                'size_bytes' => $fileSize,
                'size_formatted' => $this->formatBytes($fileSize),
                'modified_at' => $modifiedAt,
                'has_local_record' => $localBackup !== null,
                'local_backup_id' => $localBackup?->id,
                'local_backup_deleted' => $localBackup?->trashed() ?? false,
                'backup_config' => $config ? [
                    'id' => $config->id,
                    'name' => $config->name,
                    'type' => $config->type,
                ] : null,
                'storage' => [
                    'type' => 'local',
                    'name' => 'Local Storage',
                ],
            ];
        }

        // Sort by date descending
        usort($archives, fn($a, $b) => strcmp($b['datetime'], $a['datetime']));

        return response()->json([
            'success' => true,
            'data' => $archives,
            'storage' => [
                'type' => 'local',
                'path' => $archiveDir,
            ],
        ]);
    }

    /**
     * List backup archives on a remote storage
     * Shows individual backup files (compressed archives) on remote
     */
    public function listRemoteBackups(Request $request, string $remoteId): JsonResponse
    {
        $remote = StorageRemote::find($remoteId);

        if (!$remote) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REMOTE_NOT_FOUND',
                    'message' => __('backup.remote_not_found'),
                ],
            ], 404);
        }

        // Get remote path from config
        $basePath = trim($remote->config['path'] ?? '/backups', '/');
        $rcloneName = $remote->getRcloneRemoteName();
        $userId = $request->user()->id;

        // List backup archives on remote (new format: .tar.gz files)
        $remoteArchives = $this->rcloneService->listBackupArchives($rcloneName, $basePath);

        // Also list old-format folders for backward compatibility
        $remoteFolders = $this->rcloneService->listBackupFolders($rcloneName, $basePath);

        $backups = [];

        // Process archive files (new format)
        foreach ($remoteArchives as $archive) {
            // Try to find matching backup in database by archive name in metadata
            $localBackup = Backup::withTrashed()
                ->where('user_id', $userId)
                ->where('status', Backup::STATUS_COMPLETED)
                ->whereJsonContains('metadata->remote_sync->archive_name', $archive['name'])
                ->first();

            // If not found by archive name, try to find by config name
            if (!$localBackup) {
                $config = BackupConfig::where('user_id', $userId)
                    ->whereRaw("REPLACE(REPLACE(name, ' ', '_'), '-', '_') LIKE ?", [$archive['config_name'] . '%'])
                    ->first();

                if ($config) {
                    $localBackup = Backup::withTrashed()
                        ->where('backup_config_id', $config->id)
                        ->where('user_id', $userId)
                        ->where('status', Backup::STATUS_COMPLETED)
                        ->orderBy('created_at', 'desc')
                        ->first();
                }
            } else {
                $config = $localBackup->backupConfig;
            }

            // Create display name with date/time (remove .tar.gz extension)
            $displayName = preg_replace('/\.tar\.gz$/', '', $archive['name']);

            $backups[] = [
                'id' => $localBackup?->id ?? $archive['name'],
                'archive_name' => $archive['name'],
                'remote_path' => $archive['path'],
                'display_name' => $displayName,
                'backup_date' => $archive['date'],
                'backup_time' => $archive['time'],
                'datetime' => $archive['datetime'],
                'type' => $config?->type ?? 'full',
                'status' => 'remote',
                'format' => 'archive',
                'size_bytes' => $archive['size_bytes'],
                'size_formatted' => $this->formatBytes($archive['size_bytes']),
                'modified_at' => $archive['modified_at'],
                'has_local_record' => $localBackup !== null,
                'local_backup_id' => $localBackup?->id,
                'local_backup_deleted' => $localBackup?->trashed() ?? false,
                'backup_config' => $config ? [
                    'id' => $config->id,
                    'name' => $config->name,
                    'type' => $config->type,
                ] : null,
                'storage_remote' => [
                    'id' => $remote->id,
                    'name' => $remote->name,
                    'display_name' => $remote->display_name,
                    'type' => $remote->type,
                ],
            ];
        }

        // Process old-format folders for backward compatibility
        foreach ($remoteFolders as $folder) {
            // Skip if we already have archives for this config
            $hasArchives = collect($backups)->contains(fn($b) =>
                isset($b['backup_config']['id']) && $b['backup_config']['id'] === $folder['config_id']
            );
            if ($hasArchives) {
                continue;
            }

            $config = BackupConfig::where('id', $folder['config_id'])
                ->where('user_id', $userId)
                ->first();

            $localBackup = Backup::withTrashed()
                ->where('backup_config_id', $folder['config_id'])
                ->where('user_id', $userId)
                ->where('status', Backup::STATUS_COMPLETED)
                ->orderBy('created_at', 'desc')
                ->first();

            $backups[] = [
                'id' => $folder['config_id'],
                'archive_name' => null,
                'remote_folder' => $folder['name'],
                'remote_path' => $folder['path'],
                'display_name' => $config?->name ?? $folder['name'],
                'type' => $config?->type ?? 'full',
                'status' => 'remote',
                'format' => 'folder',
                'size_bytes' => $folder['size_bytes'],
                'size_formatted' => $this->formatBytes($folder['size_bytes']),
                'file_count' => $folder['file_count'],
                'modified_at' => $folder['modified_at'],
                'has_local_record' => $localBackup !== null,
                'local_backup_id' => $localBackup?->id,
                'local_backup_deleted' => $localBackup?->trashed() ?? false,
                'backup_config' => $config ? [
                    'id' => $config->id,
                    'name' => $config->name,
                    'type' => $config->type,
                ] : null,
                'storage_remote' => [
                    'id' => $remote->id,
                    'name' => $remote->name,
                    'display_name' => $remote->display_name,
                    'type' => $remote->type,
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $backups,
            'remote' => [
                'id' => $remote->id,
                'name' => $remote->display_name,
                'type' => $remote->type,
                'path' => $basePath,
            ],
        ]);
    }

    /**
     * Batch delete multiple backups
     */
    public function batchDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string',
        ]);

        $userId = $request->user()->id;
        $ids = $validated['ids'];

        $deleted = 0;
        $failed = 0;
        $errors = [];
        $cleanupItems = [];

        // Phase 1: Soft-delete all records immediately
        foreach ($ids as $id) {
            try {
                $backup = Backup::where('id', $id)
                    ->where('user_id', $userId)
                    ->first();

                if (!$backup) {
                    $failed++;
                    $errors[] = "Backup {$id} not found";
                    continue;
                }

                // Collect cleanup info
                $metadata = $backup->metadata ?? [];
                $cleanupItems[] = [
                    'snapshot_id' => $backup->snapshot_id,
                    'config_id' => $backup->backup_config_id,
                    'is_completed' => $backup->isCompleted(),
                    'local_archive' => $metadata['local_archive']['path'] ?? null,
                    'archive_name' => $metadata['archive']['name'] ?? null,
                    'synced_remotes' => $backup->synced_remotes ?? [],
                    'backup_id' => $id,
                ];

                $backup->delete();
                $deleted++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Failed to delete backup {$id}: " . $e->getMessage();
            }
        }

        // Phase 2: Dispatch cleanup to queue (restic snapshots, archives, remotes)
        if (!empty($cleanupItems)) {
            dispatch(function () use ($cleanupItems) {
                $backupService = app(BackupService::class);
                $rcloneService = app(RcloneService::class);

                foreach ($cleanupItems as $item) {
                    try {
                        if ($item['is_completed'] && $item['snapshot_id'] && $item['config_id']) {
                            $config = BackupConfig::find($item['config_id']);
                            if ($config) {
                                $backupService->deleteSnapshot($config, $item['snapshot_id']);
                            }
                        }

                        if ($item['local_archive'] && file_exists($item['local_archive'])) {
                            @unlink($item['local_archive']);
                        }

                        if ($item['archive_name'] && !empty($item['synced_remotes'])) {
                            foreach ($item['synced_remotes'] as $remoteId) {
                                try {
                                    $remote = StorageRemote::find($remoteId);
                                    if ($remote) {
                                        $rcloneName = $remote->getRcloneRemoteName();
                                        $basePath = trim($remote->config['path'] ?? '/backups', '/');
                                        $rcloneService->deleteOnRemote("{$rcloneName}:{$basePath}/{$item['archive_name']}");
                                    }
                                } catch (\Exception $e) {
                                    \Log::warning('Failed to delete remote archive in batch cleanup', [
                                        'backup_id' => $item['backup_id'],
                                        'remote_id' => $remoteId,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Batch cleanup failed for backup', [
                            'backup_id' => $item['backup_id'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            })->onQueue('default');
        }

        return response()->json([
            'success' => true,
            'message' => __('backup.batchDeleted', ['count' => $deleted]),
            'deleted' => $deleted,
            'failed' => $failed,
            'errors' => $errors,
        ]);
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }
}
