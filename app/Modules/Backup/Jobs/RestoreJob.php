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
            $this->updateTask(15, 'Downloading backup from remote storage...');

            $config = $backup->backupConfig;
            $destinations = $config->destinations ?? [];
            $downloaded = false;

            foreach ($destinations as $destination) {
                if (str_starts_with($destination, 'remote:')) {
                    $remoteId = substr($destination, 7);
                    $remote = StorageRemote::find($remoteId);
                    if (!$remote) continue;

                    $remoteName = $remote->getRcloneRemoteName();
                    $remotePath = trim($remote->config['path'] ?? 'backups', '/');

                    $this->updateTask(20, "Downloading from {$remote->display_name}...");

                    $dlResult = $backupService->downloadFromRemote($remoteName, $remotePath, $backup->snapshot_id);
                    if ($dlResult['success']) {
                        $downloaded = true;
                        $this->updateTask(40, 'Download completed.');
                        break;
                    }
                }
            }

            if (!$downloaded) {
                $error = 'Failed to download backup from any remote';
                $this->restoreOperation->markAsFailed($error);
                $this->failTask($error);
                return;
            }
        }

        $result = $backupService->restore(
            $backup,
            $targetPath,
            $includePaths
        );

        if ($result['success']) {
            $this->updateTask(80, 'Files extracted. Processing results...');

            $filesRestored = $result['files_restored'] ?? 0;
            $bytesRestored = $result['bytes_restored'] ?? 0;
            $output = $result['output'] ?? '';

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
        $snapshotId = $backup->snapshot_id;

        // 1. Check if local archive files exist (simple flow: /tmp/vsispanel_backups/*_<snapshot>*)
        if ($snapshotId && $backupService->snapshotExists($config, $snapshotId)) {
            Log::info('Local archive files found', [
                'backup_id' => $backup->id,
                'snapshot_id' => $snapshotId,
            ]);
            return ['source' => 'local_archive'];
        }

        // 2. Check metadata archives
        $archives = $metadata['archives'] ?? [];
        foreach ($archives as $path) {
            if (is_string($path) && file_exists($path)) {
                return ['source' => 'local_archive'];
            }
        }

        // 3. Try remote download
        $destinations = $config->destinations ?? [];
        if (!empty($destinations)) {
            return ['source' => 'remote'];
        }

        Log::warning('No restore source available', ['backup_id' => $backup->id]);
        return ['source' => 'none'];
    }

    /**
     * Restore from local archive (simple flow)
     */
    protected function restoreFromLocalArchive(Backup $backup, RcloneService $rcloneService): array
    {
        // Simple flow: archive files are already in /tmp/vsispanel_backups/
        // BackupService::restore() handles finding and restoring them
        return ['success' => true];
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
