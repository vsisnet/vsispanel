<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services;

use App\Modules\Backup\Models\Backup;
use App\Modules\Backup\Models\BackupConfig;
use App\Modules\Backup\Services\Destinations\BackupDestinationInterface;
use App\Modules\Backup\Services\Destinations\B2Destination;
use App\Modules\Backup\Services\Destinations\FtpDestination;
use App\Modules\Backup\Services\Destinations\LocalDestination;
use App\Modules\Backup\Services\Destinations\S3Destination;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class BackupService
{
    private const RESTIC_BIN = '/usr/bin/restic';
    private const DEFAULT_PASSWORD_ENV = 'RESTIC_PASSWORD';
    private const DEFAULT_TIMEOUT = 3600; // 1 hour
    private const SQL_DUMP_DIR = '/tmp/vsispanel_sql_dumps';

    /**
     * Build restic command array with sudo -E prefix when not running as root.
     * This ensures restic can access the repository from both
     * PHP-FPM (www-data) and Horizon (root) contexts.
     */
    private function resticCommand(array $args): array
    {
        $command = [self::RESTIC_BIN, ...$args];

        if (posix_getuid() !== 0) {
            array_unshift($command, 'sudo', '-E');
        }

        return $command;
    }

    /**
     * Initialize a new Restic repository
     */
    public function initRepository(BackupConfig $config): array
    {
        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);

        // Check if repository already exists
        $checkResult = $this->checkRepository($config);
        if ($checkResult['success']) {
            return [
                'success' => true,
                'message' => 'Repository already initialized',
            ];
        }

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        $process = new Process($this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'init',
        ]), null, $env);

        $process->setTimeout(300);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'error' => $process->getErrorOutput() ?: $process->getOutput(),
                ];
            }

            return [
                'success' => true,
                'message' => 'Repository initialized successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Restic init failed', [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if repository exists and is accessible
     */
    public function checkRepository(BackupConfig $config): array
    {
        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        $process = new Process($this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'snapshots',
            '--json',
        ]), null, $env);

        $process->setTimeout(60);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'error' => 'Repository not accessible or not initialized',
                ];
            }

            return [
                'success' => true,
                'snapshots' => json_decode($process->getOutput(), true) ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a backup
     */
    public function createBackup(Backup $backup): array
    {
        $config = $backup->backupConfig;
        if (!$config) {
            return [
                'success' => false,
                'error' => 'No backup configuration found',
            ];
        }

        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);

        $backup->markAsRunning();

        // Build backup paths based on type
        $paths = $this->getBackupPaths($backup);
        if (empty($paths)) {
            $backup->markAsFailed('No paths to backup');
            return [
                'success' => false,
                'error' => 'No paths to backup',
            ];
        }

        // Check if we need to backup databases - create SQL dumps instead of physical files
        $sqlDumpCreated = false;
        $hasDatabasePath = in_array('/var/lib/mysql', $paths) ||
                          collect($paths)->contains(fn($p) => str_starts_with($p, '/var/lib/mysql/'));

        if ($hasDatabasePath) {
            $dumpResult = $this->createDatabaseDumps($backup);
            if ($dumpResult['success']) {
                $sqlDumpCreated = true;
                // Replace /var/lib/mysql with SQL dump directory
                $paths = array_map(function ($path) {
                    if ($path === '/var/lib/mysql') {
                        return self::SQL_DUMP_DIR;
                    }
                    if (str_starts_with($path, '/var/lib/mysql/')) {
                        $dbName = basename($path);
                        return self::SQL_DUMP_DIR . '/' . $dbName . '.sql';
                    }
                    return $path;
                }, $paths);
                Log::info('Using SQL dumps for database backup', ['paths' => $paths]);
            } else {
                Log::warning('Failed to create SQL dumps, falling back to physical files', [
                    'error' => $dumpResult['error'] ?? 'Unknown error'
                ]);
            }
        }

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        // Build exclude patterns
        $excludes = $this->buildExcludeArgs($config);

        // Build restic command
        $command = $this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'backup',
            '--json',
            '--tag', 'vsispanel',
            '--tag', "type:{$backup->type}",
            '--tag', "user:{$backup->user_id}",
        ]);

        // Add excludes
        foreach ($excludes as $exclude) {
            $command[] = '--exclude';
            $command[] = $exclude;
        }

        // Add paths
        foreach ($paths as $path) {
            $command[] = $path;
        }

        $process = new Process($command, null, $env);
        $process->setTimeout(self::DEFAULT_TIMEOUT);

        try {
            $output = '';
            $process->run(function ($type, $buffer) use (&$output) {
                $output .= $buffer;
            });

            if (!$process->isSuccessful()) {
                $error = $process->getErrorOutput() ?: $output;
                $backup->markAsFailed($error);
                Log::error('Backup failed', [
                    'backup_id' => $backup->id,
                    'error' => $error,
                ]);

                // Cleanup SQL dumps if created
                if ($sqlDumpCreated) {
                    $this->cleanupSqlDumps();
                }

                return [
                    'success' => false,
                    'error' => $error,
                ];
            }

            // Cleanup SQL dumps after successful backup
            if ($sqlDumpCreated) {
                $this->cleanupSqlDumps();
            }

            // Parse the JSON output to get snapshot ID and size
            $result = $this->parseBackupOutput($output);

            $backup->markAsCompleted(
                $result['snapshot_id'] ?? Str::uuid()->toString(),
                $result['total_bytes'] ?? 0,
                [
                    'files_new' => $result['files_new'] ?? 0,
                    'files_changed' => $result['files_changed'] ?? 0,
                    'files_unmodified' => $result['files_unmodified'] ?? 0,
                    'dirs_new' => $result['dirs_new'] ?? 0,
                    'dirs_changed' => $result['dirs_changed'] ?? 0,
                    'dirs_unmodified' => $result['dirs_unmodified'] ?? 0,
                    'data_added' => $result['data_added'] ?? 0,
                    'sql_dump_backup' => $sqlDumpCreated, // Track that this backup uses SQL dumps
                ]
            );

            // Update config last_run_at
            $config->update(['last_run_at' => now()]);

            // NOTE: Remote sync is now handled by BackupJob.syncToRemoteDestinations()
            // which provides Task progress tracking

            return [
                'success' => true,
                'snapshot_id' => $result['snapshot_id'] ?? null,
                'size_bytes' => $result['total_bytes'] ?? 0,
            ];
        } catch (\Exception $e) {
            // Cleanup SQL dumps on exception
            if ($sqlDumpCreated) {
                $this->cleanupSqlDumps();
            }

            $backup->markAsFailed($e->getMessage());
            Log::error('Backup exception', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync backup to remote storage using rclone (legacy method for backward compatibility)
     */
    public function syncToRemote(BackupConfig $config, Backup $backup): array
    {
        $storageRemote = $config->storageRemote;
        if (!$storageRemote) {
            return [
                'success' => false,
                'error' => 'No storage remote configured',
            ];
        }

        return $this->syncToRemoteById($config, $backup, $storageRemote);
    }

    /**
     * Sync backup to a specific remote storage using rclone
     */
    public function syncToRemoteById(BackupConfig $config, Backup $backup, \App\Modules\Backup\Models\StorageRemote $storageRemote): array
    {
        $rcloneService = app(RcloneService::class);

        // Check if rclone is installed
        if (!$rcloneService->isInstalled()) {
            return [
                'success' => false,
                'error' => 'Rclone is not installed',
            ];
        }

        // Get local backup path
        $destinationConfig = $config->destination_config ?? [];
        $localPath = $destinationConfig['path'] ?? config('backup.default_local_path', '/var/backups/vsispanel');

        // Build remote path: remotename:path/backup_id
        $remoteName = $storageRemote->getRcloneRemoteName();
        $remoteBasePath = $storageRemote->config['path'] ?? '/backups';
        $remotePath = "{$remoteName}:{$remoteBasePath}";

        Log::info('Syncing backup to remote storage', [
            'backup_id' => $backup->id,
            'local_path' => $localPath,
            'remote_path' => $remotePath,
            'remote_name' => $storageRemote->display_name,
        ]);

        try {
            $result = $rcloneService->syncToRemote($localPath, $remotePath);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Backup synced to remote storage',
                    'remote_path' => $remotePath,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Sync failed',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync backup to remote', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List snapshots for a config
     */
    public function listSnapshots(BackupConfig $config): array
    {
        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        $process = new Process($this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'snapshots',
            '--json',
            '--tag', 'vsispanel',
        ]), null, $env);

        $process->setTimeout(120);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'error' => $process->getErrorOutput(),
                ];
            }

            $snapshots = json_decode($process->getOutput(), true) ?? [];

            return [
                'success' => true,
                'snapshots' => $snapshots,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if a specific snapshot exists in the repository
     */
    public function snapshotExists(BackupConfig $config, string $snapshotId): bool
    {
        if (empty($snapshotId)) {
            return false;
        }

        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        // Use restic snapshots command with the specific snapshot ID
        $process = new Process($this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'snapshots',
            $snapshotId,
            '--json',
        ]), null, $env);

        $process->setTimeout(60);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                Log::debug('Snapshot not found', [
                    'snapshot_id' => $snapshotId,
                    'config_id' => $config->id,
                    'error' => $process->getErrorOutput(),
                ]);
                return false;
            }

            $snapshots = json_decode($process->getOutput(), true) ?? [];
            $exists = !empty($snapshots);

            Log::debug('Snapshot existence check', [
                'snapshot_id' => $snapshotId,
                'config_id' => $config->id,
                'exists' => $exists,
            ]);

            return $exists;
        } catch (\Exception $e) {
            Log::warning('Failed to check snapshot existence', [
                'snapshot_id' => $snapshotId,
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Restore from a snapshot
     */
    public function restore(Backup $backup, string $targetPath, array $includePaths = []): array
    {
        $config = $backup->backupConfig;
        if (!$config || !$backup->snapshot_id) {
            return [
                'success' => false,
                'error' => 'Invalid backup or missing snapshot ID',
            ];
        }

        Log::info('Starting restore operation', [
            'backup_id' => $backup->id,
            'backup_type' => $backup->type,
            'target_path' => $targetPath,
            'include_paths' => $includePaths,
        ]);

        // Separate database paths from other paths
        $databasePaths = [];
        $otherPaths = [];

        foreach ($includePaths as $path) {
            // Check for database-related paths (both old and new format)
            if (str_starts_with($path, '/var/lib/mysql/') ||
                str_starts_with($path, self::SQL_DUMP_DIR)) {
                $databasePaths[] = $path;
            } else {
                $otherPaths[] = $path;
            }
        }

        // For full restore, check backup type to determine what to restore
        $isFullRestore = empty($includePaths);
        $shouldRestoreDatabases = !empty($databasePaths);

        // If full restore and backup type includes databases, add database restore
        if ($isFullRestore && in_array($backup->type, [Backup::TYPE_FULL, Backup::TYPE_DATABASES])) {
            $shouldRestoreDatabases = true;
            // Get all databases from the backup
            $databasePaths = $this->getBackupDatabasePaths($backup, $config);
            Log::info('Full restore - detected database paths', ['paths' => $databasePaths]);
        }

        $results = [];
        $totalOutput = '';

        // Restore non-database files first (if full restore or has other paths)
        if ($isFullRestore || !empty($otherPaths)) {
            // For full restore with databases, exclude database paths from file restore
            $fileIncludePaths = $isFullRestore ? [] : $otherPaths;

            $fileResult = $this->restoreFiles($backup, $config, $targetPath, $fileIncludePaths);
            $results['files'] = $fileResult;
            $totalOutput .= $fileResult['output'] ?? '';

            if (!$fileResult['success']) {
                return $fileResult;
            }
        }

        // Restore databases with special handling
        if ($shouldRestoreDatabases && !empty($databasePaths)) {
            Log::info('Restoring databases', ['paths' => $databasePaths]);

            $dbResult = $this->restoreDatabases($backup, $config, $targetPath, $databasePaths);
            $results['databases'] = $dbResult;
            $totalOutput .= "\n" . ($dbResult['output'] ?? '');

            if (!$dbResult['success']) {
                return [
                    'success' => false,
                    'error' => $dbResult['error'],
                    'output' => $totalOutput,
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Restore completed successfully',
            'target_path' => $targetPath,
            'output' => $totalOutput,
        ];
    }

    /**
     * Get database paths from backup (auto-detect format)
     */
    protected function getBackupDatabasePaths(Backup $backup, BackupConfig $config): array
    {
        $paths = [];

        // First check for SQL dumps (new format)
        if ($this->checkForSqlDumps($backup, $config)) {
            $result = $this->browseSnapshot($backup, self::SQL_DUMP_DIR);
            if ($result['success'] && !empty($result['files'])) {
                foreach ($result['files'] as $file) {
                    if (str_ends_with($file['name'] ?? '', '.sql')) {
                        $paths[] = self::SQL_DUMP_DIR . '/' . $file['name'];
                    }
                }
            }
        }

        // If no SQL dumps, check for physical MySQL files (old format)
        if (empty($paths)) {
            $result = $this->browseSnapshot($backup, '/var/lib/mysql');
            if ($result['success'] && !empty($result['files'])) {
                $skipDbs = ['mysql', 'performance_schema', 'information_schema', 'sys', '#innodb_redo', '#innodb_temp', 'lost+found'];
                foreach ($result['files'] as $file) {
                    if (($file['type'] ?? '') === 'dir' && !in_array($file['name'], $skipDbs)) {
                        $paths[] = '/var/lib/mysql/' . $file['name'];
                    }
                }
            }
        }

        Log::info('Auto-detected database paths in backup', ['paths' => $paths]);
        return $paths;
    }

    /**
     * Restore files (non-database)
     */
    protected function restoreFiles(Backup $backup, BackupConfig $config, string $targetPath, array $includePaths): array
    {
        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        $command = $this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'restore',
            $backup->snapshot_id,
            '--target', $targetPath,
        ]);

        // Add specific paths to restore
        foreach ($includePaths as $path) {
            $command[] = '--include';
            $command[] = $path;
        }

        $process = new Process($command, null, $env);
        $process->setTimeout(self::DEFAULT_TIMEOUT);

        Log::info('Executing file restore command', [
            'command' => implode(' ', $command),
            'target_path' => $targetPath,
            'include_paths' => $includePaths,
        ]);

        try {
            $process->run();

            Log::info('File restore process completed', [
                'exit_code' => $process->getExitCode(),
                'output' => $process->getOutput(),
                'error_output' => $process->getErrorOutput(),
            ]);

            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'error' => $process->getErrorOutput(),
                ];
            }

            return [
                'success' => true,
                'message' => 'File restore completed successfully',
                'output' => $process->getOutput(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restore databases - supports both SQL dump (new format) and physical files (old format)
     *
     * New backups use SQL dumps stored in /tmp/vsispanel_sql_dumps/
     * Old backups use physical files from /var/lib/mysql/
     */
    protected function restoreDatabases(Backup $backup, BackupConfig $config, string $targetPath, array $databasePaths): array
    {
        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);
        $mysqlCredentials = $this->getMysqlCredentials();

        Log::info('restoreDatabases called', [
            'backup_id' => $backup->id,
            'snapshot_id' => $backup->snapshot_id,
            'database_paths' => $databasePaths,
            'mysql_user' => $mysqlCredentials['user'],
        ]);

        $output = "Database restore started\n";
        $output .= "Paths to restore: " . implode(', ', $databasePaths) . "\n";
        $tempDir = '/tmp/vsispanel_db_restore_' . time();

        try {
            @mkdir($tempDir, 0755, true);
            $output .= "Temp directory: {$tempDir}\n";

            // First, check if backup contains SQL dumps (new format)
            $hasSqlDumps = $this->checkForSqlDumps($backup, $config);
            $output .= "Has SQL dumps in backup: " . ($hasSqlDumps ? 'YES' : 'NO') . "\n";

            Log::info('Checking for SQL dumps', [
                'has_sql_dumps' => $hasSqlDumps,
                'sql_dump_dir' => self::SQL_DUMP_DIR,
            ]);

            if ($hasSqlDumps) {
                $output .= "Using SQL dump restore method (no MySQL downtime)\n";
                $sqlResult = $this->restoreFromSqlDumps($backup, $config, $tempDir, $databasePaths, $mysqlCredentials);

                // Check if we need to fallback to physical files (SQL dumps existed in backup but not for requested databases)
                if (!$sqlResult['success'] && ($sqlResult['fallback'] ?? false)) {
                    Log::info('SQL dump restore signaled fallback, trying physical file restore');
                    $output .= "SQL dumps not found for requested databases, trying physical file restore...\n";

                    // Need to create a new temp directory since the old one was cleaned up
                    $tempDir = '/tmp/vsispanel_db_restore_' . time() . '_fallback';
                    @mkdir($tempDir, 0755, true);

                    return $this->restoreFromPhysicalFiles($backup, $config, $tempDir, $databasePaths, $mysqlCredentials);
                }

                return $sqlResult;
            }

            // Fall back to physical file restore (old backup format)
            $output .= "Using physical file restore method (requires MySQL stop)\n";
            Log::info('No SQL dumps found, using physical file restore', ['paths' => $databasePaths]);
            return $this->restoreFromPhysicalFiles($backup, $config, $tempDir, $databasePaths, $mysqlCredentials);

        } catch (\Exception $e) {
            Log::error('Database restore exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->cleanupTempDir($tempDir);

            return [
                'success' => false,
                'error' => 'Database restore failed: ' . $e->getMessage(),
                'output' => $output,
            ];
        }
    }

    /**
     * Check if backup contains SQL dumps
     */
    protected function checkForSqlDumps(Backup $backup, BackupConfig $config): bool
    {
        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        // Check if SQL dump directory exists in backup
        $process = new Process($this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'ls',
            $backup->snapshot_id,
            self::SQL_DUMP_DIR,
        ]), null, $env);
        $process->setTimeout(30);
        $process->run();

        $output = trim($process->getOutput());
        $success = $process->isSuccessful() && !empty($output);

        Log::info('checkForSqlDumps result', [
            'snapshot_id' => $backup->snapshot_id,
            'check_path' => self::SQL_DUMP_DIR,
            'is_successful' => $process->isSuccessful(),
            'output_length' => strlen($output),
            'has_sql_dumps' => $success,
            'error' => $process->getErrorOutput(),
        ]);

        return $success;
    }

    /**
     * Restore databases from SQL dumps (new format - simple and reliable)
     * Returns 'fallback' => true when no SQL dumps are found, indicating caller should try physical files
     */
    protected function restoreFromSqlDumps(Backup $backup, BackupConfig $config, string $tempDir, array $databasePaths, array $mysqlCredentials): array
    {
        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);

        $output = "Restoring databases from SQL dumps (no MySQL downtime)\n";

        // Extract database names from paths
        $databases = $this->extractDatabaseNames($databasePaths);

        Log::info('Restoring databases from SQL dumps', [
            'database_paths' => $databasePaths,
            'extracted_databases' => $databases,
        ]);

        if (empty($databases)) {
            $output .= "No databases found to restore from paths\n";
            return [
                'success' => false,
                'error' => 'No databases found in specified paths',
                'output' => $output,
            ];
        }

        $output .= "Databases to restore: " . implode(', ', $databases) . "\n";

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        // Extract SQL dumps from backup
        $command = $this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'restore',
            $backup->snapshot_id,
            '--target', $tempDir,
            '--include', self::SQL_DUMP_DIR,
        ]);

        $output .= "Extracting SQL dumps from backup...\n";
        $output .= "Command: " . implode(' ', array_map(fn($c) => str_contains($c, ' ') ? "\"$c\"" : $c, $command)) . "\n";

        Log::info('Executing restic restore for SQL dumps', [
            'command' => implode(' ', $command),
            'target' => $tempDir,
        ]);

        $restoreProcess = new Process($command, null, $env);
        $restoreProcess->setTimeout(self::DEFAULT_TIMEOUT);
        $restoreProcess->run();

        $output .= "Restic output: " . $restoreProcess->getOutput() . "\n";

        if (!$restoreProcess->isSuccessful()) {
            $error = $restoreProcess->getErrorOutput();
            $output .= "Restic error: {$error}\n";
            Log::error('Failed to extract SQL dumps', ['error' => $error]);
            $this->cleanupTempDir($tempDir);
            return [
                'success' => false,
                'error' => 'Failed to extract SQL dumps: ' . $error,
                'output' => $output,
            ];
        }

        $output .= "SQL dumps extracted successfully\n";

        // Import each database
        $successfulDbs = [];
        $failedDbs = [];
        $missingDumpsDbs = [];
        $extractedDumpDir = $tempDir . self::SQL_DUMP_DIR;

        Log::info('Looking for SQL dumps in', ['dir' => $extractedDumpDir]);
        $output .= "Looking for SQL dumps in: {$extractedDumpDir}\n";

        // List files in the extracted directory
        $availableSqlFiles = [];
        if (is_dir($extractedDumpDir)) {
            $files = scandir($extractedDumpDir);
            $output .= "Files found: " . implode(', ', $files) . "\n";
            Log::info('SQL dump files found', ['files' => $files]);
            // Get list of available .sql files
            foreach ($files as $file) {
                if (str_ends_with($file, '.sql')) {
                    $availableSqlFiles[] = $file;
                }
            }
        } else {
            $output .= "Warning: Extracted dump directory does not exist\n";
            Log::warning('Extracted dump directory does not exist', ['dir' => $extractedDumpDir]);
        }

        // Check if ANY of the requested databases have SQL dumps
        $hasSqlDumpForAny = false;
        foreach ($databases as $dbName) {
            if (in_array($dbName . '.sql', $availableSqlFiles)) {
                $hasSqlDumpForAny = true;
                break;
            }
        }

        // If no SQL dumps exist for ANY of the requested databases, signal fallback to physical files
        if (!$hasSqlDumpForAny) {
            $output .= "No SQL dump files found for any requested database, fallback to physical files\n";
            Log::info('No SQL dumps found for requested databases, signaling fallback', [
                'requested_databases' => $databases,
                'available_sql_files' => $availableSqlFiles,
            ]);
            $this->cleanupTempDir($tempDir);
            return [
                'success' => false,
                'fallback' => true, // Signal to try physical file restore
                'error' => 'No SQL dumps found for requested databases',
                'output' => $output,
            ];
        }

        foreach ($databases as $dbName) {
            $dumpFile = $extractedDumpDir . '/' . $dbName . '.sql';

            $output .= "Looking for dump file: {$dumpFile}\n";
            Log::info('Checking for dump file', ['file' => $dumpFile, 'exists' => file_exists($dumpFile)]);

            if (!file_exists($dumpFile)) {
                $output .= "SQL dump not found for {$dbName}, marking as missing\n";
                $missingDumpsDbs[] = $dbName;
                continue;
            }

            $fileSize = filesize($dumpFile);
            $output .= "Importing {$dbName} (size: " . $this->formatBytes($fileSize) . ")...\n";
            Log::info('Importing database from SQL dump', ['database' => $dbName, 'file' => $dumpFile, 'size' => $fileSize]);

            // Import the SQL dump - build command dynamically based on credentials
            $mysqlCommand = ['mysql', '-u', $mysqlCredentials['user']];
            if (!empty($mysqlCredentials['password'])) {
                $mysqlCommand[] = '-p' . $mysqlCredentials['password'];
            }
            $importProcess = new Process($mysqlCommand);
            $importProcess->setInput(file_get_contents($dumpFile));
            $importProcess->setTimeout(self::DEFAULT_TIMEOUT);
            $importProcess->run();

            if ($importProcess->isSuccessful()) {
                $successfulDbs[] = $dbName;
                $dumpSize = $this->formatBytes($fileSize);
                $output .= "Successfully restored {$dbName} ({$dumpSize})\n";
                Log::info('Database restored successfully', ['database' => $dbName]);
            } else {
                $failedDbs[] = $dbName;
                $error = $importProcess->getErrorOutput();
                $output .= "Failed to import {$dbName}: {$error}\n";
                Log::error('Failed to import database', ['database' => $dbName, 'error' => $error]);
            }
        }

        // Cleanup
        $this->cleanupTempDir($tempDir);
        $output .= "Cleaned up temporary files\n";

        // Final status - count missing dumps as failures only if we couldn't restore them
        $allFailedDbs = array_merge($failedDbs, $missingDumpsDbs);

        if (!empty($allFailedDbs) && empty($successfulDbs)) {
            return [
                'success' => false,
                'error' => 'All database restores failed',
                'output' => $output,
                'missing_dumps' => $missingDumpsDbs,
            ];
        }

        $output .= "\nDatabase restore completed.\n";
        if (!empty($successfulDbs)) {
            $output .= "Successfully restored: " . implode(', ', $successfulDbs) . "\n";
        }
        if (!empty($failedDbs)) {
            $output .= "Failed to restore: " . implode(', ', $failedDbs) . "\n";
        }
        if (!empty($missingDumpsDbs)) {
            $output .= "Missing SQL dumps for: " . implode(', ', $missingDumpsDbs) . "\n";
        }

        return [
            'success' => true,
            'message' => 'Database restore completed',
            'output' => $output,
            'successful_databases' => $successfulDbs,
            'failed_databases' => $failedDbs,
            'missing_dumps' => $missingDumpsDbs,
        ];
    }

    /**
     * Restore databases from physical files (old format)
     *
     * NOTE: This method is DISABLED because it requires stopping MySQL which disrupts
     * the entire application. Instead, users should create a NEW backup which will
     * use SQL dumps that can be restored without MySQL downtime.
     */
    protected function restoreFromPhysicalFiles(Backup $backup, BackupConfig $config, string $tempDir, array $databasePaths, array $mysqlCredentials): array
    {
        $databases = $this->extractDatabaseNames($databasePaths);

        Log::warning('Physical file restore attempted but disabled', [
            'backup_id' => $backup->id,
            'databases' => $databases,
        ]);

        $this->cleanupTempDir($tempDir);

        // Return informative error - don't stop MySQL as it disrupts the entire application
        return [
            'success' => false,
            'error' => 'OLD_BACKUP_FORMAT',
            'error_code' => 'OLD_BACKUP_FORMAT',
            'message' => 'This backup uses the old format (physical MySQL files) which requires stopping MySQL to restore. ' .
                        'To avoid service interruption, please create a NEW backup. New backups use SQL dumps which can be restored without MySQL downtime.',
            'output' => "Database restore cannot proceed.\n\n" .
                       "REASON: This backup was created before the SQL dump feature was implemented.\n" .
                       "Old backups store physical MySQL files which require stopping MySQL to restore.\n" .
                       "This would interrupt all websites and applications using this server.\n\n" .
                       "SOLUTION: Create a new backup. New backups use SQL dumps (mysqldump) which\n" .
                       "can be imported without stopping MySQL - zero downtime restore!\n\n" .
                       "Databases that need new backup: " . implode(', ', $databases) . "\n",
            'databases' => $databases,
        ];
    }

    /**
     * Extract database names from paths
     */
    protected function extractDatabaseNames(array $paths): array
    {
        $databases = [];
        foreach ($paths as $path) {
            // Handle both formats:
            // Old: /var/lib/mysql/database_name
            // New: /tmp/vsispanel_sql_dumps/database_name.sql
            if (str_contains($path, '/var/lib/mysql/')) {
                $parts = explode('/', trim($path, '/'));
                if (count($parts) >= 4) {
                    $databases[] = $parts[3];
                }
            } elseif (str_contains($path, self::SQL_DUMP_DIR)) {
                $filename = basename($path);
                if (str_ends_with($filename, '.sql')) {
                    $databases[] = substr($filename, 0, -4);
                }
            }
        }
        return array_unique($databases);
    }

    /**
     * Stop MySQL service
     */
    protected function stopMysql(): void
    {
        $stopProcess = new Process(['systemctl', 'stop', 'mysql']);
        $stopProcess->setTimeout(60);
        $stopProcess->run();

        if (!$stopProcess->isSuccessful()) {
            $stopProcess = new Process(['systemctl', 'stop', 'mariadb']);
            $stopProcess->setTimeout(60);
            $stopProcess->run();
        }
    }

    /**
     * Get MySQL credentials from environment or config
     */
    protected function getMysqlCredentials(): array
    {
        return [
            'user' => config('database.connections.mysql.username', 'root'),
            'password' => config('database.connections.mysql.password', ''),
            'host' => config('database.connections.mysql.host', '127.0.0.1'),
        ];
    }

    /**
     * Clean up temporary directory
     */
    protected function cleanupTempDir(string $dir): void
    {
        if (is_dir($dir) && str_starts_with($dir, '/tmp/')) {
            $rmProcess = new Process(['rm', '-rf', $dir]);
            $rmProcess->setTimeout(60);
            $rmProcess->run();
        }
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

    /**
     * Restart MySQL service (kept for compatibility)
     */
    protected function restartMysql(): void
    {
        $startProcess = new Process(['systemctl', 'start', 'mysql']);
        $startProcess->setTimeout(60);
        $startProcess->run();

        if (!$startProcess->isSuccessful()) {
            // Try mariadb
            $startProcess = new Process(['systemctl', 'start', 'mariadb']);
            $startProcess->setTimeout(60);
            $startProcess->run();
        }
    }

    /**
     * Delete a snapshot
     */
    public function deleteSnapshot(BackupConfig $config, string $snapshotId): array
    {
        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        // Use 'forget' without --prune for fast deletion.
        // Pruning is expensive and should be done separately (e.g. scheduled maintenance).
        $process = new Process($this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'forget',
            $snapshotId,
        ]), null, $env);

        $process->setTimeout(30);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                \Log::warning('Failed to forget restic snapshot', [
                    'snapshot_id' => $snapshotId,
                    'stderr' => $process->getErrorOutput(),
                ]);
                return [
                    'success' => false,
                    'error' => $process->getErrorOutput(),
                ];
            }

            return [
                'success' => true,
                'message' => 'Snapshot deleted successfully',
            ];
        } catch (\Exception $e) {
            \Log::error('Exception deleting restic snapshot', [
                'snapshot_id' => $snapshotId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Apply retention policy
     */
    public function applyRetention(BackupConfig $config): array
    {
        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);
        $retention = $config->retention_policy ?? [];

        if (empty($retention)) {
            return [
                'success' => true,
                'message' => 'No retention policy defined',
            ];
        }

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        $command = $this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'forget',
            '--prune',
            '--tag', 'vsispanel',
        ]);

        // Add retention flags
        if (isset($retention['keep_last'])) {
            $command[] = '--keep-last';
            $command[] = (string) $retention['keep_last'];
        }

        if (isset($retention['keep_hourly'])) {
            $command[] = '--keep-hourly';
            $command[] = (string) $retention['keep_hourly'];
        }

        if (isset($retention['keep_daily'])) {
            $command[] = '--keep-daily';
            $command[] = (string) $retention['keep_daily'];
        }

        if (isset($retention['keep_weekly'])) {
            $command[] = '--keep-weekly';
            $command[] = (string) $retention['keep_weekly'];
        }

        if (isset($retention['keep_monthly'])) {
            $command[] = '--keep-monthly';
            $command[] = (string) $retention['keep_monthly'];
        }

        if (isset($retention['keep_yearly'])) {
            $command[] = '--keep-yearly';
            $command[] = (string) $retention['keep_yearly'];
        }

        $process = new Process($command, null, $env);
        $process->setTimeout(600);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'error' => $process->getErrorOutput(),
                ];
            }

            return [
                'success' => true,
                'message' => 'Retention policy applied successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get repository statistics
     */
    public function getStats(BackupConfig $config): array
    {
        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        $process = new Process($this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'stats',
            '--json',
        ]), null, $env);

        $process->setTimeout(300);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'error' => $process->getErrorOutput(),
                ];
            }

            $stats = json_decode($process->getOutput(), true);

            return [
                'success' => true,
                'stats' => $stats,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Browse files in a snapshot
     */
    public function browseSnapshot(Backup $backup, string $path = '/'): array
    {
        $config = $backup->backupConfig;
        if (!$config || !$backup->snapshot_id) {
            return [
                'success' => false,
                'error' => 'Invalid backup or missing snapshot ID',
            ];
        }

        $destination = $this->createDestination($config);
        $password = $this->getRepositoryPassword($config);

        $env = array_merge(
            $destination->getEnvironmentVariables(),
            [self::DEFAULT_PASSWORD_ENV => $password]
        );

        $process = new Process($this->resticCommand([
            '-r', $destination->getRepositoryUrl(),
            'ls',
            '--json',
            $backup->snapshot_id,
            $path,
        ]), null, $env);

        $process->setTimeout(120);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'error' => $process->getErrorOutput(),
                ];
            }

            // Parse NDJSON output - filter out snapshot info and the path itself
            $files = [];
            $normalizedPath = rtrim($path, '/') ?: '/';
            $lines = explode("\n", trim($process->getOutput()));

            foreach ($lines as $line) {
                if (!$line) {
                    continue;
                }

                $entry = json_decode($line, true);
                if (!$entry) {
                    continue;
                }

                // Skip snapshot info line (first line of restic ls --json output)
                if (($entry['struct_type'] ?? '') === 'snapshot') {
                    continue;
                }

                // Skip the requested path itself (restic includes it in output)
                $entryPath = $entry['path'] ?? '';
                if ($entryPath === $normalizedPath) {
                    continue;
                }

                // Only include direct children of the requested path
                $parentDir = dirname($entryPath);
                if ($parentDir !== $normalizedPath) {
                    continue;
                }

                $files[] = [
                    'name' => $entry['name'] ?? basename($entryPath),
                    'type' => $entry['type'] ?? 'file',
                    'path' => $entryPath,
                    'size' => $entry['size'] ?? null,
                    'mtime' => $entry['mtime'] ?? null,
                    'permissions' => $entry['permissions'] ?? null,
                ];
            }

            // Sort: directories first, then by name
            usort($files, function ($a, $b) {
                if ($a['type'] === 'dir' && $b['type'] !== 'dir') {
                    return -1;
                }
                if ($a['type'] !== 'dir' && $b['type'] === 'dir') {
                    return 1;
                }
                return strcasecmp($a['name'], $b['name']);
            });

            return [
                'success' => true,
                'files' => $files,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create destination instance from config
     */
    private function createDestination(BackupConfig $config): BackupDestinationInterface
    {
        $destinationConfig = $config->destination_config ?? [];

        return match ($config->destination_type) {
            'local' => LocalDestination::fromConfig($destinationConfig),
            's3' => S3Destination::fromConfig($destinationConfig),
            'ftp' => FtpDestination::fromConfig($destinationConfig),
            'b2' => B2Destination::fromConfig($destinationConfig),
            default => LocalDestination::fromConfig(['path' => '/var/backups/vsispanel']),
        };
    }

    /**
     * Get repository password
     */
    private function getRepositoryPassword(BackupConfig $config): string
    {
        $destinationConfig = $config->destination_config ?? [];
        return $destinationConfig['password'] ?? config('backup.default_password', 'vsispanel_backup_secret');
    }

    /**
     * Get backup paths based on backup type
     */
    private function getBackupPaths(Backup $backup): array
    {
        $config = $backup->backupConfig;
        $customPaths = $config->include_paths ?? [];

        if (!empty($customPaths)) {
            return $customPaths;
        }

        // Handle custom type with backup_items
        if ($backup->type === 'custom') {
            return $this->getPathsFromBackupItems($config);
        }

        // Default paths based on type
        return match ($backup->type) {
            Backup::TYPE_FULL => [
                '/home',
                '/var/www',
                '/etc/nginx',
                '/etc/apache2',
                '/var/lib/mysql',
                '/etc/vsispanel',
            ],
            Backup::TYPE_FILES => [
                '/home',
                '/var/www',
            ],
            Backup::TYPE_DATABASES => [
                '/var/lib/mysql',
            ],
            Backup::TYPE_EMAILS => [
                '/var/mail',
                '/var/vmail',
            ],
            Backup::TYPE_CONFIG => [
                '/etc/nginx',
                '/etc/apache2',
                '/etc/postfix',
                '/etc/dovecot',
                '/etc/vsispanel',
            ],
            default => ['/home'],
        };
    }

    /**
     * Get backup paths from backup_items configuration
     */
    private function getPathsFromBackupItems(BackupConfig $config): array
    {
        $backupItems = $config->backup_items ?? [];
        $paths = [];

        foreach ($backupItems as $item) {
            switch ($item) {
                case 'files':
                    $paths[] = '/home';
                    $paths[] = '/var/www';
                    break;
                case 'databases':
                    $paths[] = '/var/lib/mysql';
                    break;
                case 'emails':
                    $paths[] = '/var/mail';
                    $paths[] = '/var/vmail';
                    break;
                case 'config':
                    $paths[] = '/etc/nginx';
                    $paths[] = '/etc/apache2';
                    $paths[] = '/etc/postfix';
                    $paths[] = '/etc/dovecot';
                    $paths[] = '/etc/vsispanel';
                    break;
            }
        }

        // Remove duplicates and return
        return array_unique($paths);
    }

    /**
     * Build exclude arguments from config
     */
    private function buildExcludeArgs(BackupConfig $config): array
    {
        $patterns = $config->exclude_patterns ?? [];

        // Add default excludes
        $defaults = [
            '*.log',
            '*.tmp',
            '.cache',
            'node_modules',
            '.git',
            'vendor',
            '*.sock',
        ];

        return array_unique(array_merge($defaults, $patterns));
    }

    /**
     * Parse restic backup JSON output
     */
    private function parseBackupOutput(string $output): array
    {
        $result = [
            'snapshot_id' => null,
            'total_bytes' => 0,
            'files_new' => 0,
            'files_changed' => 0,
            'files_unmodified' => 0,
            'dirs_new' => 0,
            'dirs_changed' => 0,
            'dirs_unmodified' => 0,
            'data_added' => 0,
        ];

        // Parse NDJSON output
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $data = json_decode($line, true);
            if (!$data) {
                continue;
            }

            if (isset($data['message_type'])) {
                if ($data['message_type'] === 'summary') {
                    $result['snapshot_id'] = $data['snapshot_id'] ?? null;
                    $result['total_bytes'] = $data['total_bytes_processed'] ?? 0;
                    $result['files_new'] = $data['files_new'] ?? 0;
                    $result['files_changed'] = $data['files_changed'] ?? 0;
                    $result['files_unmodified'] = $data['files_unmodified'] ?? 0;
                    $result['dirs_new'] = $data['dirs_new'] ?? 0;
                    $result['dirs_changed'] = $data['dirs_changed'] ?? 0;
                    $result['dirs_unmodified'] = $data['dirs_unmodified'] ?? 0;
                    $result['data_added'] = $data['data_added'] ?? 0;
                }
            }
        }

        return $result;
    }

    /**
     * Create SQL dumps for all databases
     */
    protected function createDatabaseDumps(Backup $backup): array
    {
        $dumpDir = self::SQL_DUMP_DIR;

        // Clean up old dumps and create fresh directory
        $this->cleanupSqlDumps();
        @mkdir($dumpDir, 0755, true);

        $mysqlCredentials = $this->getMysqlCredentials();
        $databases = $this->listDatabases($mysqlCredentials);

        if (empty($databases)) {
            return [
                'success' => false,
                'error' => 'No databases found to backup',
            ];
        }

        Log::info('Creating SQL dumps for databases', ['databases' => $databases]);

        $successCount = 0;
        $failedDbs = [];

        foreach ($databases as $dbName) {
            $dumpFile = $dumpDir . '/' . $dbName . '.sql';

            // Build mysqldump command dynamically based on credentials
            $dumpCommand = [
                'mysqldump',
                '-u', $mysqlCredentials['user'],
            ];
            if (!empty($mysqlCredentials['password'])) {
                $dumpCommand[] = '-p' . $mysqlCredentials['password'];
            }
            $dumpCommand = array_merge($dumpCommand, [
                '--single-transaction',
                '--routines',
                '--triggers',
                '--events',
                '--add-drop-database',
                '--databases', $dbName,
            ]);
            $dumpProcess = new Process($dumpCommand);
            $dumpProcess->setTimeout(self::DEFAULT_TIMEOUT);

            try {
                $dumpProcess->run();

                if ($dumpProcess->isSuccessful()) {
                    file_put_contents($dumpFile, $dumpProcess->getOutput());
                    $successCount++;
                    Log::info('SQL dump created', [
                        'database' => $dbName,
                        'size' => strlen($dumpProcess->getOutput()),
                    ]);
                } else {
                    $failedDbs[] = $dbName;
                    Log::warning('Failed to dump database', [
                        'database' => $dbName,
                        'error' => $dumpProcess->getErrorOutput(),
                    ]);
                }
            } catch (\Exception $e) {
                $failedDbs[] = $dbName;
                Log::error('Exception dumping database', [
                    'database' => $dbName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($successCount === 0) {
            return [
                'success' => false,
                'error' => 'Failed to create any SQL dumps',
            ];
        }

        return [
            'success' => true,
            'dump_dir' => $dumpDir,
            'databases_dumped' => $successCount,
            'failed_databases' => $failedDbs,
        ];
    }

    /**
     * List all user databases (excluding system databases)
     */
    protected function listDatabases(array $credentials): array
    {
        // Build mysql command dynamically based on credentials
        $mysqlCommand = ['mysql', '-u', $credentials['user']];
        if (!empty($credentials['password'])) {
            $mysqlCommand[] = '-p' . $credentials['password'];
        }
        $mysqlCommand[] = '-N';
        $mysqlCommand[] = '-e';
        $mysqlCommand[] = 'SHOW DATABASES';

        $process = new Process($mysqlCommand);
        $process->setTimeout(30);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('Failed to list databases', ['error' => $process->getErrorOutput()]);
                return [];
            }

            $databases = array_filter(
                explode("\n", trim($process->getOutput())),
                fn($db) => !in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys', ''])
            );

            return array_values($databases);
        } catch (\Exception $e) {
            Log::error('Exception listing databases', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Cleanup SQL dump directory
     */
    protected function cleanupSqlDumps(): void
    {
        $dumpDir = self::SQL_DUMP_DIR;
        if (is_dir($dumpDir)) {
            $rmProcess = new Process(['rm', '-rf', $dumpDir]);
            $rmProcess->setTimeout(60);
            $rmProcess->run();
        }
    }
}
