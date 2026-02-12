<?php

declare(strict_types=1);

namespace App\Modules\Backup\Jobs;

use App\Modules\Backup\Models\Backup;
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

class BackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600; // 1 hour
    public int $backoff = 60; // 1 minute

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

        // Load task if taskId provided
        if ($this->taskId) {
            $this->task = Task::find($this->taskId);
            if ($this->task) {
                $this->taskService->start($this->task);
                $this->updateTask(5, 'Starting backup job...');
            }
        }

        try {
            $this->executeBackup($backupService, $rcloneService);
        } catch (\Throwable $e) {
            Log::error('BackupJob unexpected exception', [
                'backup_id' => $this->backup->id,
                'task_id' => $this->taskId,
                'error' => $e->getMessage(),
            ]);
            $this->backup->markAsFailed($e->getMessage());
            $this->failTask('Unexpected error: ' . $e->getMessage());
        }
    }

    /**
     * Execute the actual backup logic
     */
    protected function executeBackup(BackupService $backupService, RcloneService $rcloneService): void
    {
        Log::info('Starting backup job', [
            'backup_id' => $this->backup->id,
            'type' => $this->backup->type,
            'task_id' => $this->taskId,
        ]);

        // Ensure repository is initialized
        if ($this->backup->backupConfig) {
            $this->updateTask(10, 'Initializing backup repository...');

            $initResult = $backupService->initRepository($this->backup->backupConfig);
            if (!$initResult['success'] && !str_contains($initResult['error'] ?? '', 'already')) {
                $error = $initResult['error'] ?? 'Failed to initialize repository';
                Log::error('Failed to initialize repository', [
                    'backup_id' => $this->backup->id,
                    'error' => $error,
                ]);
                $this->backup->markAsFailed($error);
                $this->failTask($error);
                return;
            }
        }

        $this->updateTask(15, 'Repository initialized. Creating backup...');

        // Run the backup
        $result = $backupService->createBackup($this->backup);

        if ($result['success']) {
            $this->updateTask(60, 'Backup created successfully. Snapshot: ' . ($result['snapshot_id'] ?? 'N/A'));

            Log::info('Backup completed successfully', [
                'backup_id' => $this->backup->id,
                'snapshot_id' => $result['snapshot_id'] ?? null,
                'size_bytes' => $result['size_bytes'] ?? 0,
            ]);

            // Apply retention policy after successful backup
            if ($this->backup->backupConfig) {
                $this->updateTask(65, 'Applying retention policy...');

                $retentionResult = $backupService->applyRetention($this->backup->backupConfig);
                if (!$retentionResult['success']) {
                    $this->updateTask(70, 'Warning: Retention policy failed - ' . ($retentionResult['error'] ?? 'Unknown'));
                    Log::warning('Failed to apply retention policy', [
                        'backup_id' => $this->backup->id,
                        'error' => $retentionResult['error'] ?? 'Unknown error',
                    ]);
                } else {
                    $this->updateTask(70, 'Retention policy applied.');
                }

                // Create archive and sync to destinations (local + remote)
                $this->createAndSyncArchive($rcloneService);
            }

            $this->completeTask('Backup completed successfully!');
        } else {
            $error = $result['error'] ?? 'Unknown error';
            Log::error('Backup failed', [
                'backup_id' => $this->backup->id,
                'error' => $error,
            ]);
            $this->failTask($error);
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
     * Create backup archive and sync to all destinations (local + remote)
     */
    protected function createAndSyncArchive(RcloneService $rcloneService): void
    {
        $config = $this->backup->backupConfig;
        $destinations = $config->destinations ?? [];

        // Get local backup path (restic repository)
        $destinationConfig = $config->destination_config ?? [];
        $resticPath = $destinationConfig['path'] ?? '/var/backups/vsispanel';

        // Local archive storage directory
        $localArchiveDir = '/var/backups/vsispanel-archives';

        if (!is_dir($resticPath)) {
            $this->updateTask(75, 'Warning: Restic backup path does not exist.');
            Log::warning('Restic backup path does not exist', [
                'backup_id' => $this->backup->id,
                'path' => $resticPath,
            ]);
            return;
        }

        // Create archive name: {config_name}_{date}_{time}.tar.gz
        $configName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $config->name);
        $datetime = $this->backup->created_at->format('Y-m-d_H-i-s');
        $archiveName = "{$configName}_{$datetime}.tar.gz";

        $this->updateTask(72, 'Creating compressed backup archive...');

        // Create compressed archive
        $archiveResult = $rcloneService->createBackupArchive($resticPath, $archiveName);

        if (!$archiveResult['success']) {
            $this->updateTask(75, 'Failed to create archive: ' . ($archiveResult['error'] ?? 'Unknown error'));
            Log::error('Failed to create backup archive', [
                'backup_id' => $this->backup->id,
                'error' => $archiveResult['error'] ?? 'Unknown error',
            ]);
            return;
        }

        $tempArchivePath = $archiveResult['archive_path'];
        $archiveSize = $archiveResult['size_bytes'];

        Log::info('Backup archive created', [
            'backup_id' => $this->backup->id,
            'archive_name' => $archiveName,
            'archive_size' => $archiveSize,
        ]);

        // Save archive locally
        $localArchivePath = null;
        $localSaveSuccess = false;

        // Check if 'local' is in destinations
        $hasLocalDestination = in_array('local', $destinations);

        if ($hasLocalDestination) {
            $this->updateTask(74, 'Saving archive to local storage...');

            // Ensure local archive directory exists
            if (!is_dir($localArchiveDir)) {
                if (!mkdir($localArchiveDir, 0755, true)) {
                    Log::warning('Failed to create local archive directory', [
                        'backup_id' => $this->backup->id,
                        'path' => $localArchiveDir,
                    ]);
                }
            }

            if (is_dir($localArchiveDir)) {
                $localArchivePath = "{$localArchiveDir}/{$archiveName}";

                // Copy archive to local storage
                if (copy($tempArchivePath, $localArchivePath)) {
                    $localSaveSuccess = true;
                    Log::info('Backup archive saved locally', [
                        'backup_id' => $this->backup->id,
                        'archive_name' => $archiveName,
                        'local_path' => $localArchivePath,
                    ]);
                } else {
                    Log::warning('Failed to save archive locally', [
                        'backup_id' => $this->backup->id,
                        'archive_name' => $archiveName,
                    ]);
                }
            }
        }

        // Filter out 'local' to get only remote destinations
        $remoteDestinations = array_filter($destinations, fn($d) => $d !== 'local' && str_starts_with($d, 'remote:'));

        $syncResults = [];
        $syncedRemoteIds = [];

        // Upload to remote destinations if rclone is installed
        if (!empty($remoteDestinations)) {
            if (!$rcloneService->isInstalled()) {
                $this->updateTask(75, 'Warning: Rclone not installed, skipping remote sync.');
                Log::warning('Rclone not installed, skipping remote sync', [
                    'backup_id' => $this->backup->id,
                ]);
            } else {
                $this->updateTask(75, 'Archive created (' . $this->formatBytes($archiveSize) . '). Uploading to remotes...');

                $totalRemotes = count($remoteDestinations);
                $currentRemote = 0;

                foreach ($remoteDestinations as $destination) {
                    $currentRemote++;
                    $remoteId = substr($destination, 7);
                    $remote = StorageRemote::find($remoteId);

                    if (!$remote) {
                        $this->updateTask(
                            75 + (int)((20 / $totalRemotes) * $currentRemote),
                            "Warning: Remote storage not found (ID: {$remoteId})"
                        );
                        Log::warning('Remote storage not found', [
                            'backup_id' => $this->backup->id,
                            'remote_id' => $remoteId,
                        ]);
                        continue;
                    }

                    // Use getRcloneRemoteName() to get correct name (e.g., vsispanel_ftp1)
                    $rcloneRemoteName = $remote->getRcloneRemoteName();
                    $basePath = trim($remote->config['path'] ?? '/backups', '/');
                    $fullRemotePath = "{$rcloneRemoteName}:{$basePath}/{$archiveName}";

                    $this->updateTask(
                        75 + (int)((20 / $totalRemotes) * $currentRemote),
                        "Uploading to {$remote->display_name}..."
                    );

                    Log::info('Uploading backup archive to remote', [
                        'backup_id' => $this->backup->id,
                        'remote_name' => $remote->display_name,
                        'archive_name' => $archiveName,
                        'remote_path' => $fullRemotePath,
                    ]);

                    $uploadResult = $rcloneService->uploadFile($tempArchivePath, $fullRemotePath);

                    Log::info('Upload result', [
                        'backup_id' => $this->backup->id,
                        'remote_name' => $remote->display_name,
                        'success' => $uploadResult['success'],
                        'error' => $uploadResult['error'] ?? null,
                    ]);

                    $syncResults[$remote->id] = [
                        'name' => $remote->display_name,
                        'success' => $uploadResult['success'],
                        'error' => $uploadResult['error'] ?? null,
                        'archive_name' => $archiveName,
                        'archive_size' => $archiveSize,
                    ];

                    if ($uploadResult['success']) {
                        $syncedRemoteIds[] = $remote->id;
                        $this->updateTask(
                            75 + (int)((22 / $totalRemotes) * $currentRemote),
                            "Successfully uploaded to {$remote->display_name}"
                        );

                        Log::info('Backup archive uploaded successfully', [
                            'backup_id' => $this->backup->id,
                            'remote_name' => $remote->display_name,
                            'archive_name' => $archiveName,
                        ]);

                        // Update remote last sync time
                        $remote->update([
                            'last_tested_at' => now(),
                            'last_test_result' => true,
                        ]);
                    } else {
                        $this->updateTask(
                            75 + (int)((22 / $totalRemotes) * $currentRemote),
                            "Failed to upload to {$remote->display_name}: " . ($uploadResult['error'] ?? 'Unknown error')
                        );

                        Log::error('Failed to upload backup archive', [
                            'backup_id' => $this->backup->id,
                            'remote_name' => $remote->display_name,
                            'error' => $uploadResult['error'] ?? 'Unknown error',
                        ]);
                    }
                }
            }
        }

        // Clean up temp archive (we already saved locally if needed)
        $rcloneService->cleanupArchive($tempArchivePath);

        // Update backup with archive info and synced remote IDs
        $updateData = [];

        if (!empty($syncedRemoteIds)) {
            $updateData['synced_remotes'] = $syncedRemoteIds;
            $updateData['storage_remote_id'] = $syncedRemoteIds[0]; // First synced remote for backward compatibility
        }

        $metadata = $this->backup->metadata ?? [];
        $metadata['archive'] = [
            'name' => $archiveName,
            'size' => $archiveSize,
            'created_at' => now()->toISOString(),
        ];

        if ($localSaveSuccess && $localArchivePath) {
            $metadata['local_archive'] = [
                'path' => $localArchivePath,
                'saved_at' => now()->toISOString(),
            ];
        }

        if (!empty($syncResults)) {
            $metadata['remote_sync'] = [
                'synced_at' => now()->toISOString(),
                'archive_name' => $archiveName,
                'archive_size' => $archiveSize,
                'results' => $syncResults,
            ];
        }

        $updateData['metadata'] = $metadata;
        $this->backup->update($updateData);

        // Summary
        $summaryParts = [];
        if ($localSaveSuccess) {
            $summaryParts[] = 'Local: saved';
        }
        if (!empty($remoteDestinations)) {
            $successCount = count($syncedRemoteIds);
            $totalRemotes = count($remoteDestinations);
            $failCount = $totalRemotes - $successCount;
            $remoteSummary = "Remote: {$successCount}/{$totalRemotes}";
            if ($failCount > 0) {
                $remoteSummary .= " ({$failCount} failed)";
            }
            $summaryParts[] = $remoteSummary;
        }

        $summary = !empty($summaryParts) ? implode(' | ', $summaryParts) : 'Archive created';
        $this->updateTask(100, $summary);
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
        Log::error('Backup job failed', [
            'backup_id' => $this->backup->id,
            'task_id' => $this->taskId,
            'error' => $exception->getMessage(),
        ]);

        $this->backup->markAsFailed($exception->getMessage());

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
