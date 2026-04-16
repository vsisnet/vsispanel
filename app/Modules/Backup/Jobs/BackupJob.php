<?php

declare(strict_types=1);

namespace App\Modules\Backup\Jobs;

use App\Modules\Backup\Models\Backup;
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

/**
 * Simple Backup Job - No Restic, just mysqldump + tar + rclone
 */
class BackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 10800;

    protected ?Task $task = null;
    protected ?TaskService $taskService = null;

    public function __construct(
        public readonly Backup $backup,
        public readonly ?string $taskId = null
    ) {
        $this->queue = 'backups';
    }

    public function handle(BackupService $backupService, RcloneService $rcloneService, TaskService $taskService): void
    {
        $this->taskService = $taskService;

        // Load task
        if ($this->taskId) {
            $this->task = Task::find($this->taskId);
            if ($this->task) {
                $this->taskService->start($this->task);
                $this->updateTask(5, 'Starting backup...');
            }
        }

        try {
            $config = $this->backup->backupConfig;
            $backupItems = $config->backup_items ?? ['databases', 'files'];
            
            Log::info('BackupJob started', [
                'backup_id' => $this->backup->id,
                'items' => $backupItems,
            ]);

            // Step 1: Create backup (mysqldump + tar)
            $this->updateTask(10, 'Creating backup...');
            $result = $backupService->createBackup($this->backup);

            if (!$result['success']) {
                $this->failTask($result['error'] ?? 'Backup failed');
                $this->backup->update(['status' => 'failed']);
                return;
            }

            $this->updateTask(50, 'Backup created. Uploading to remote...');

            // Step 2: Upload to remote storage
            $archivePaths = $result['archives'] ?? [];
            $destinations = $config->destinations ?? [];

            if (!empty($archivePaths) && !empty($destinations)) {
                foreach ($destinations as $destination) {
                    $remoteName = $this->getRemoteName($destination, $config);
                    if ($remoteName) {
                        $this->updateTask(60, "Uploading to {$remoteName}...");
                        $remotePath = $this->getRemotePath($destination);
                        $uploadResult = $backupService->uploadToRemote($archivePaths, $remoteName, $remotePath);
                        
                        Log::info('Upload result', [
                            'backup_id' => $this->backup->id,
                            'remote' => $remoteName,
                            'result' => $uploadResult,
                        ]);
                    }
                }
            }

            // Step 3: Apply retention policy
            $this->updateTask(80, 'Applying retention policy...');
            $retention = $config->retention_policy ?? ['keep_last' => 7];
            $keepLast = $retention['keep_last'] ?? 7;

            foreach ($destinations as $destination) {
                $remoteName = $this->getRemoteName($destination, $config);
                if ($remoteName) {
                    $remotePath = $this->getRemotePath($destination);
                    $backupService->applyRetention($remoteName, $remotePath, $keepLast);
                }
            }

            // Step 4: Cleanup local temp files
            $this->updateTask(90, 'Cleaning up...');
            $backupService->cleanup();

            // Done!
            $this->updateTask(100, 'Backup completed successfully.');
            $this->completeTask('Backup completed');
            // Mark backup record as completed
            $this->backup->update(["completed_at" => now()]);

            Log::info('BackupJob completed', [
                'backup_id' => $this->backup->id,
                'archives' => $archivePaths,
            ]);

        } catch (\Exception $e) {
            Log::error('BackupJob failed', [
                'backup_id' => $this->backup->id,
                'error' => $e->getMessage(),
            ]);

            $this->failTask($e->getMessage());
            $this->backup->update(['status' => 'failed']);
            throw $e;
        }
    }

    /**
     * Get remote name from destination config
     */
    protected function getRemoteName(string $destination, $config): ?string
    {
        // Format: "remote:<storage_remote_id>" or just remote name
        if (str_starts_with($destination, 'remote:')) {
            $remoteId = substr($destination, 7);
            $remote = \App\Modules\Backup\Models\StorageRemote::find($remoteId);
            return $remote?->getRcloneRemoteName();
        }
        return $destination;
    }

    protected function getRemotePath(string $destination): string
    {
        if (str_starts_with($destination, 'remote:')) {
            $remoteId = substr($destination, 7);
            $remote = \App\Modules\Backup\Models\StorageRemote::find($remoteId);
            if ($remote && !empty($remote->config['path'])) {
                return trim($remote->config['path'], '/');
            }
        }
        return 'backups';
    }

    protected function updateTask(int $progress, string $message): void
    {
        if ($this->task && $this->taskService) {
            $this->taskService->updateProgress($this->task, $progress, $message);
        }
    }

    protected function completeTask(string $message): void
    {
        if ($this->task && $this->taskService) {
            $this->taskService->complete($this->task, $message);
        }
    }

    protected function failTask(string $error): void
    {
        if ($this->task && $this->taskService) {
            $this->taskService->fail($this->task, $error);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BackupJob failed exception', [
            'backup_id' => $this->backup->id,
            'error' => $exception->getMessage(),
        ]);

        $this->backup->update(['status' => 'failed']);
        $this->failTask($exception->getMessage());
    }
}
