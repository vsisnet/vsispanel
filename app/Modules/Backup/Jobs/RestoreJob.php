<?php

declare(strict_types=1);

namespace App\Modules\Backup\Jobs;

use App\Modules\Backup\Models\Backup;
use App\Modules\Backup\Models\RestoreOperation;
use App\Modules\Backup\Models\StorageRemote;
use App\Modules\Backup\Services\BackupService;
use App\Modules\Backup\Services\RcloneService;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Services\TaskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RestoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600; // 1 hour

    protected ?Task $task = null;
    protected ?TaskService $taskService = null;

    public function __construct(
        public readonly RestoreOperation $restoreOperation,
        public readonly ?string $taskId = null
    ) {
        $this->queue = 'backups';
    }

    public function handle(BackupService $backupService, TaskService $taskService, RcloneService $rcloneService): void
    {
        $this->taskService = $taskService;

        // Load task if taskId provided
        if ($this->taskId) {
            $this->task = Task::find($this->taskId);
            if ($this->task) {
                $this->taskService->start($this->task);
                $this->updateTask(5, 'Starting restore operation...');
            }
        }

        // Include soft-deleted backups for remote restore
        $backup = Backup::withTrashed()->find($this->restoreOperation->backup_id);
        $targetPath = $this->restoreOperation->target_path;
        $includePaths = $this->restoreOperation->include_paths ?? [];

        Log::info('Starting restore job', [
            'restore_operation_id' => $this->restoreOperation->id,
            'backup_id' => $backup->id,
            'snapshot_id' => $backup->snapshot_id,
            'target_path' => $targetPath,
            'include_paths' => $includePaths,
            'include_paths_count' => count($includePaths),
            'task_id' => $this->taskId,
            'is_trashed' => $backup->trashed(),
            'synced_remotes' => $backup->synced_remotes ?? [],
        ]);

        // Mark as running
        $this->restoreOperation->markAsRunning();
        $this->updateTask(10, 'Restore operation started...');

        // Check if we need to restore from archive (local or remote)
        $restoreSource = $this->determineRestoreSource($backup, $backupService);

        Log::info('Restore source determined', [
            'restore_operation_id' => $this->restoreOperation->id,
            'backup_id' => $backup->id,
            'source' => $restoreSource['source'],
        ]);

        if ($restoreSource['source'] === 'local_archive') {
            $this->updateTask(15, 'Restoring from local archive...');

            $extractResult = $this->restoreFromLocalArchive($backup, $rcloneService);

            if (!$extractResult['success']) {
                // Fallback to remote if local archive failed
                Log::warning('Local archive restore failed, trying remote', [
                    'backup_id' => $backup->id,
                    'error' => $extractResult['error'] ?? 'Unknown error',
                ]);
                $restoreSource['source'] = 'remote';
            } else {
                $this->updateTask(40, 'Local archive extracted. Restoring files...');
            }
        }

        if ($restoreSource['source'] === 'remote') {
            $this->updateTask(15, 'Syncing backup from remote storage...');

            $syncResult = $this->syncFromRemote($backup, $rcloneService);

            if (!$syncResult['success']) {
                $error = 'Failed to sync from remote: ' . ($syncResult['error'] ?? 'Unknown error');
                $this->restoreOperation->markAsFailed($error);
                $this->failTask($error);

                Log::error('Failed to sync backup from remote', [
                    'restore_operation_id' => $this->restoreOperation->id,
                    'backup_id' => $backup->id,
                    'error' => $syncResult['error'] ?? 'Unknown error',
                ]);
                return;
            }

            $this->updateTask(40, 'Remote sync completed. Extracting files...');
        } elseif ($restoreSource['source'] === 'local_restic') {
            $this->updateTask(20, 'Extracting files from local restic backup...');
        }

        $result = $backupService->restore(
            $backup,
            $targetPath,
            $includePaths
        );

        if ($result['success']) {
            $this->updateTask(80, 'Files extracted. Processing results...');

            // Parse the output to get files/bytes restored
            $output = $result['output'] ?? '';
            $filesRestored = 0;
            $bytesRestored = 0;

            // Parse restic output: "Summary: Restored 890 files/dirs (228.793 MiB) in 0:01"
            if (preg_match('/Restored\s+(\d+)\s+/', $output, $matches)) {
                $filesRestored = (int) $matches[1];
            }
            if (preg_match('/\(([\d.]+)\s*(B|KiB|MiB|GiB|TiB)\)/', $output, $matches)) {
                $size = (float) $matches[1];
                $unit = $matches[2];
                $multipliers = ['B' => 1, 'KiB' => 1024, 'MiB' => 1024 * 1024, 'GiB' => 1024 * 1024 * 1024, 'TiB' => 1024 * 1024 * 1024 * 1024];
                $bytesRestored = (int) ($size * ($multipliers[$unit] ?? 1));
            }

            $this->restoreOperation->markAsCompleted($filesRestored, $bytesRestored, $output);

            $summary = "Restored {$filesRestored} files/dirs";
            if ($bytesRestored > 0) {
                $summary .= ' (' . $this->formatBytes($bytesRestored) . ')';
            }

            $this->completeTask($summary);

            Log::info('Restore completed successfully', [
                'restore_operation_id' => $this->restoreOperation->id,
                'backup_id' => $backup->id,
                'target_path' => $targetPath,
                'files_restored' => $filesRestored,
                'bytes_restored' => $bytesRestored,
            ]);
        } else {
            $error = $result['error'] ?? 'Unknown error';
            $this->restoreOperation->markAsFailed($error);
            $this->failTask($error);

            Log::error('Restore failed', [
                'restore_operation_id' => $this->restoreOperation->id,
                'backup_id' => $backup->id,
                'error' => $error,
            ]);
        }
    }

    /**
     * Update task progress
     */
    protected function updateTask(int $progress, string $message): void
    {
        if ($this->task && $this->taskService) {
            $this->taskService->updateProgress($this->task, $progress, $message);
        }
    }

    /**
     * Complete task
     */
    protected function completeTask(string $message): void
    {
        if ($this->task && $this->taskService) {
            $this->taskService->complete($this->task, $message);
        }
    }

    /**
     * Fail task
     */
    protected function failTask(string $error): void
    {
        if ($this->task && $this->taskService) {
            $this->taskService->fail($this->task, $error);
        }
    }

    /**
     * Determine the best source for restore
     * Priority: local_restic > local_archive > remote
     */
    protected function determineRestoreSource(Backup $backup, BackupService $backupService): array
    {
        $config = $backup->backupConfig;
        $metadata = $backup->metadata ?? [];

        // 1. Check if snapshot exists in local restic repository
        if ($config) {
            $snapshotExists = $backupService->snapshotExists($config, $backup->snapshot_id);
            if ($snapshotExists) {
                Log::debug('Snapshot found in local restic repository', [
                    'backup_id' => $backup->id,
                    'snapshot_id' => $backup->snapshot_id,
                ]);
                return ['source' => 'local_restic'];
            }
        }

        // 2. Check if local archive exists
        $localArchivePath = $metadata['local_archive']['path'] ?? null;
        $archiveName = $metadata['archive']['name'] ?? null;

        // Also check default location
        if (!$localArchivePath && $archiveName) {
            $localArchivePath = "/var/backups/vsispanel-archives/{$archiveName}";
        }

        if ($localArchivePath && file_exists($localArchivePath)) {
            Log::info('Local archive found', [
                'backup_id' => $backup->id,
                'archive_path' => $localArchivePath,
            ]);
            return [
                'source' => 'local_archive',
                'path' => $localArchivePath,
            ];
        }

        // 3. Check if we have synced remotes
        $syncedRemotes = $backup->synced_remotes ?? [];
        if (!empty($syncedRemotes)) {
            Log::info('Will restore from remote storage', [
                'backup_id' => $backup->id,
                'synced_remotes' => $syncedRemotes,
            ]);
            return ['source' => 'remote'];
        }

        // No restore source available - will fail at restore step
        Log::warning('No restore source available', ['backup_id' => $backup->id]);
        return ['source' => 'none'];
    }

    /**
     * Restore from local archive (extract to restic repository)
     */
    protected function restoreFromLocalArchive(Backup $backup, RcloneService $rcloneService): array
    {
        $config = $backup->backupConfig;
        $metadata = $backup->metadata ?? [];

        if (!$config) {
            return [
                'success' => false,
                'error' => 'Backup configuration not found',
            ];
        }

        // Get archive path
        $localArchivePath = $metadata['local_archive']['path'] ?? null;
        $archiveName = $metadata['archive']['name'] ?? null;

        if (!$localArchivePath && $archiveName) {
            $localArchivePath = "/var/backups/vsispanel-archives/{$archiveName}";
        }

        if (!$localArchivePath || !file_exists($localArchivePath)) {
            return [
                'success' => false,
                'error' => 'Local archive not found',
            ];
        }

        // Get local restic path from config
        $destinationConfig = $config->destination_config ?? [];
        $localPath = $destinationConfig['path'] ?? '/var/backups/vsispanel';

        Log::info('Extracting local archive', [
            'backup_id' => $backup->id,
            'archive_path' => $localArchivePath,
            'target_path' => $localPath,
        ]);

        $this->updateTask(20, 'Extracting local archive...');

        // Extract archive to restic repository path
        $extractResult = $rcloneService->extractBackupArchive($localArchivePath, $localPath);

        if ($extractResult['success']) {
            Log::info('Successfully extracted local archive', [
                'backup_id' => $backup->id,
                'archive_path' => $localArchivePath,
            ]);
            return ['success' => true];
        }

        Log::warning('Failed to extract local archive', [
            'backup_id' => $backup->id,
            'error' => $extractResult['error'] ?? 'Unknown error',
        ]);

        return [
            'success' => false,
            'error' => $extractResult['error'] ?? 'Failed to extract archive',
        ];
    }

    /**
     * Sync backup from remote storage to local (download and extract archive)
     */
    protected function syncFromRemote(Backup $backup, RcloneService $rcloneService): array
    {
        $syncedRemotes = $backup->synced_remotes ?? [];
        $config = $backup->backupConfig;
        $metadata = $backup->metadata ?? [];

        if (empty($syncedRemotes)) {
            return [
                'success' => false,
                'error' => 'No synced remotes available',
            ];
        }

        if (!$config) {
            return [
                'success' => false,
                'error' => 'Backup configuration not found',
            ];
        }

        // Get archive name from metadata
        $archiveName = $metadata['remote_sync']['archive_name'] ?? null;

        // Get local backup path from config
        $destinationConfig = $config->destination_config ?? [];
        $localPath = $destinationConfig['path'] ?? '/var/backups/vsispanel';

        // Try each synced remote until one succeeds
        foreach ($syncedRemotes as $remoteId) {
            $remote = StorageRemote::find($remoteId);
            if (!$remote) {
                Log::warning('Synced remote not found', ['remote_id' => $remoteId]);
                continue;
            }

            $rcloneName = $remote->getRcloneRemoteName();
            $basePath = trim($remote->config['path'] ?? '/backups', '/');

            // Check if we have an archive (new format) or folder (old format)
            if ($archiveName) {
                // New format: Download and extract archive
                $remotePath = "{$rcloneName}:{$basePath}/{$archiveName}";
                $tempDir = sys_get_temp_dir();
                $localArchivePath = "{$tempDir}/{$archiveName}";

                Log::info('Downloading backup archive from remote', [
                    'backup_id' => $backup->id,
                    'remote_name' => $remote->display_name,
                    'archive_name' => $archiveName,
                    'remote_path' => $remotePath,
                ]);

                $this->updateTask(20, "Downloading archive from {$remote->display_name}...");

                $downloadResult = $rcloneService->downloadFile($remotePath, $localArchivePath);

                if (!$downloadResult['success']) {
                    Log::warning('Failed to download archive, trying next remote', [
                        'backup_id' => $backup->id,
                        'remote_name' => $remote->display_name,
                        'error' => $downloadResult['error'] ?? 'Unknown error',
                    ]);
                    continue;
                }

                $this->updateTask(30, "Extracting backup archive...");

                // Extract archive to local path
                $extractResult = $rcloneService->extractBackupArchive($localArchivePath, $localPath);

                // Clean up downloaded archive
                if (file_exists($localArchivePath)) {
                    unlink($localArchivePath);
                }

                if ($extractResult['success']) {
                    Log::info('Successfully restored backup from remote archive', [
                        'backup_id' => $backup->id,
                        'remote_name' => $remote->display_name,
                        'archive_name' => $archiveName,
                    ]);

                    return ['success' => true];
                }

                Log::warning('Failed to extract archive, trying next remote', [
                    'backup_id' => $backup->id,
                    'remote_name' => $remote->display_name,
                    'error' => $extractResult['error'] ?? 'Unknown error',
                ]);
            } else {
                // Old format: Sync folder directly (backward compatibility)
                $backupFolderName = 'vsispanel-backup-' . $config->id;
                $remotePath = "{$rcloneName}:{$basePath}/{$backupFolderName}";

                Log::info('Syncing backup folder from remote (old format)', [
                    'backup_id' => $backup->id,
                    'remote_name' => $remote->display_name,
                    'remote_path' => $remotePath,
                    'local_path' => $localPath,
                ]);

                $this->updateTask(25, "Downloading backup from {$remote->display_name}...");

                $result = $rcloneService->syncFromRemote($remotePath, $localPath);

                if ($result['success']) {
                    Log::info('Successfully synced backup from remote', [
                        'backup_id' => $backup->id,
                        'remote_name' => $remote->display_name,
                    ]);

                    return ['success' => true];
                }

                Log::warning('Failed to sync from remote, trying next', [
                    'backup_id' => $backup->id,
                    'remote_name' => $remote->display_name,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }
        }

        return [
            'success' => false,
            'error' => 'Failed to restore from all available remotes',
        ];
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Restore job failed', [
            'restore_operation_id' => $this->restoreOperation->id,
            'task_id' => $this->taskId,
            'error' => $exception->getMessage(),
        ]);

        $this->restoreOperation->markAsFailed($exception->getMessage());

        // Mark task as failed if exists
        if ($this->taskId) {
            $task = Task::find($this->taskId);
            if ($task) {
                $taskService = app(TaskService::class);
                $taskService->fail($task, $exception->getMessage());
            }
        }
    }
}
