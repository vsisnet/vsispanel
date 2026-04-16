<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services;

use App\Modules\Backup\Models\Backup;
use App\Modules\Backup\Models\BackupConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Simple Backup Service - No Restic, just mysqldump + tar + rclone
 */
class BackupService
{
    private const SQL_DUMP_DIR = '/tmp/vsispanel_sql_dumps';
    private const ARCHIVE_DIR = '/tmp/vsispanel_backups';
    private const DEFAULT_TIMEOUT = 3600;

    /**
     * Create a backup
     */
    public function createBackup(Backup $backup): array
    {
        $config = $backup->backupConfig;
        $backupItems = $config->backup_items ?? ['databases', 'files'];
        $timestamp = $backup->created_at->format('Y-m-d_H-i-s');
        $results = [];

        // Ensure directories exist
        $this->ensureDirectories();

        try {
            // Backup databases
            if (in_array('databases', $backupItems)) {
                $dbResult = $this->backupDatabases($backup, $config, $timestamp);
                $results['databases'] = $dbResult;
                
                if (!$dbResult['success']) {
                    return ['success' => false, 'error' => 'Database backup failed: ' . ($dbResult['error'] ?? 'Unknown'), 'results' => $results];
                }
            }

            // Backup files (uploads only, no code)
            if (in_array('files', $backupItems)) {
                $fileResult = $this->backupFiles($backup, $config, $timestamp);
                $results['files'] = $fileResult;
                
                if (!$fileResult['success']) {
                    return ['success' => false, 'error' => 'File backup failed: ' . ($fileResult['error'] ?? 'Unknown'), 'results' => $results];
                }
            }

            // Get archive paths for upload
            $archivePaths = [];
            if (isset($results['databases']['archive_paths'])) {
                $archivePaths = array_merge($archivePaths, $results['databases']['archive_paths']);
            } elseif (isset($results['databases']['archive_path'])) {
                $archivePaths[] = $results['databases']['archive_path'];
            }
            if (isset($results['files']['archive_path'])) {
                $archivePaths[] = $results['files']['archive_path'];
            }

            // Update backup record
            $backup->update([
                'status' => 'completed',
                'snapshot_id' => $timestamp,
                'size_bytes' => $this->calculateTotalSize($archivePaths),
                'metadata' => array_merge($backup->metadata ?? [], [
                    'archives' => $archivePaths,
                    'backup_items' => $backupItems,
                ]),
            ]);

            return [
                'success' => true,
                'archives' => $archivePaths,
                'results' => $results,
            ];

        } catch (\Exception $e) {
            Log::error('Backup failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            $backup->update(['status' => 'failed']);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => $results,
            ];
        }
    }

    /**
     * Backup databases - separate file per database
     */
    protected function backupDatabases(Backup $backup, BackupConfig $config, string $timestamp): array
    {
        $databases = $this->getDatabases($config);
        $archives = [];
        $totalSize = 0;

        Log::info('Starting database backup', ['databases' => $databases]);

        try {
            foreach ($databases as $dbName) {
                $archiveName = "db_{$dbName}_{$timestamp}.sql.gz";
                $archivePath = self::ARCHIVE_DIR . '/' . $archiveName;
                $tempSqlFile = self::SQL_DUMP_DIR . "/{$dbName}_{$timestamp}.sql";

                // Dump single database
                $dumpCmd = "mysqldump --defaults-file=/etc/mysql/vsispanel.cnf {$dbName} --single-transaction --quick > '{$tempSqlFile}' 2>&1";
                $process = Process::fromShellCommandline($dumpCmd);
                $process->setTimeout(self::DEFAULT_TIMEOUT);
                $dbCmdStart = microtime(true);
                Log::info('Backup DB command start', [
                    'database' => $dbName,
                    'backup_id' => $backup->id,
                ]);
                $process->run();
                $dbCmdDurationMs = (int) round((microtime(true) - $dbCmdStart) * 1000);
                Log::info('Backup DB command end', [
                    'database' => $dbName,
                    'backup_id' => $backup->id,
                    'exit_code' => $process->getExitCode(),
                    'duration_ms' => $dbCmdDurationMs,
                    'stderr' => Str::limit((string) $process->getErrorOutput(), 500),
                ]);

                if ($process->getExitCode() !== 0) {
                    Log::error("mysqldump failed for {$dbName}", ['exit' => $process->getExitCode()]);
                    continue;
                }

                // Compress
                $gzipCmd = "nice -n 19 ionice -c 3 gzip -1 -c '{$tempSqlFile}' > '{$archivePath}'";
                Process::fromShellCommandline($gzipCmd)->setTimeout(600)->run();

                @unlink($tempSqlFile);

                if (file_exists($archivePath)) {
                    $size = filesize($archivePath);
                    $totalSize += $size;
                    $archives[] = [
                        'database' => $dbName,
                        'archive_path' => $archivePath,
                        'archive_name' => $archiveName,
                        'size_bytes' => $size,
                    ];
                    Log::info("Database {$dbName} backed up", ['size' => $size]);
                }
            }

            if (empty($archives)) {
                return ['success' => false, 'error' => 'No databases backed up'];
            }

            return [
                'success' => true,
                'archive_paths' => array_column($archives, 'archive_path'),
                'archives' => $archives,
                'size_bytes' => $totalSize,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Backup files - tar uploads folders only (no code, no vendor)
     */
    protected function backupFiles(Backup $backup, BackupConfig $config, string $timestamp): array
    {
        $paths = $this->getFilePaths($config);
        $archiveName = "files_{$timestamp}.tar";
        $archivePath = self::ARCHIVE_DIR . '/' . $archiveName;

        Log::info('Starting file backup', ['paths' => $paths]);

        try {
            // Build tar command with nice + ionice for low CPU
            $pathsStr = implode(' ', array_map('escapeshellarg', $paths));
            $excludes = "--exclude='*.log' --exclude='cache' --exclude='.git' --exclude='node_modules' --exclude='vendor'";
            
            $tarCmd = "nice -n 19 ionice -c 3 tar -cf '{$archivePath}' {$excludes} {$pathsStr} 2>/dev/null";
            
            $process = Process::fromShellCommandline($tarCmd);
            $process->setTimeout(self::DEFAULT_TIMEOUT);
            $tarCmdStart = microtime(true);
            Log::info('Backup file command start', [
                'backup_id' => $backup->id,
                'paths_count' => count($paths),
            ]);
            $process->run();
            $tarCmdDurationMs = (int) round((microtime(true) - $tarCmdStart) * 1000);
            Log::info('Backup file command end', [
                'backup_id' => $backup->id,
                'exit_code' => $process->getExitCode(),
                'duration_ms' => $tarCmdDurationMs,
                'stderr' => Str::limit((string) $process->getErrorOutput(), 500),
            ]);

            // tar returns 1 if files changed during archive, that's OK
            if (!$process->isSuccessful() && $process->getExitCode() > 1) {
                return ['success' => false, 'error' => 'tar failed: ' . $process->getErrorOutput()];
            }

            $size = filesize($archivePath);
            Log::info('File backup completed', [
                'archive' => $archivePath,
                'size' => $size,
            ]);

            return [
                'success' => true,
                'archive_path' => $archivePath,
                'archive_name' => $archiveName,
                'size_bytes' => $size,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload archives to remote storage via rclone
     */
    public function uploadToRemote(array $archivePaths, string $remoteName, string $remotePath = 'backups'): array
    {
        $results = [];

        foreach ($archivePaths as $archivePath) {
            if (!file_exists($archivePath)) {
                $results[$archivePath] = ['success' => false, 'error' => 'File not found'];
                continue;
            }

            $archiveName = basename($archivePath);
            $rcloneCmd = "rclone --config /etc/rclone/rclone.conf copy '{$archivePath}' '{$remoteName}:{$remotePath}/' --progress 2>&1";
            
            $process = Process::fromShellCommandline($rcloneCmd);
            $process->setTimeout(self::DEFAULT_TIMEOUT);
            $uploadStart = microtime(true);
            Log::info('Backup upload command start', [
                'archive' => $archiveName,
                'remote' => $remoteName,
            ]);
            $process->run();
            $uploadDurationMs = (int) round((microtime(true) - $uploadStart) * 1000);
            Log::info('Backup upload command end', [
                'archive' => $archiveName,
                'remote' => $remoteName,
                'exit_code' => $process->getExitCode(),
                'duration_ms' => $uploadDurationMs,
                'stderr' => Str::limit((string) $process->getErrorOutput(), 500),
            ]);

            if ($process->isSuccessful()) {
                $results[$archivePath] = ['success' => true, 'remote' => "{$remoteName}:{$remotePath}/{$archiveName}"];
                Log::info('Uploaded to remote', ['file' => $archiveName, 'remote' => $remoteName]);
            } else {
                $results[$archivePath] = ['success' => false, 'error' => $process->getErrorOutput()];
                Log::error('Upload failed', ['file' => $archiveName, 'error' => $process->getErrorOutput()]);
            }
        }

        return $results;
    }

    /**
     * Restore database from backup
     */
    public function restoreDatabase(string $archivePath): array
    {
        if (!file_exists($archivePath)) {
            return ['success' => false, 'error' => 'Archive not found'];
        }

        try {
            // Extract database name from filename: db_<dbname>_<timestamp>.sql.gz
            $dbName = $this->extractDbNameFromArchive($archivePath);
            $dbFlag = $dbName ? " {$dbName}" : '';
            $cmd = "gunzip -c '{$archivePath}' | mysql --defaults-file=/etc/mysql/vsispanel.cnf{$dbFlag}";
            
            $process = Process::fromShellCommandline($cmd);
            $process->setTimeout(self::DEFAULT_TIMEOUT);
            $process->run();

            if (!$process->isSuccessful()) {
                return ['success' => false, 'error' => $process->getErrorOutput()];
            }

            return ['success' => true, 'message' => 'Database restored successfully'];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Restore files from backup
     */
    public function restoreFiles(string $archivePath, string $targetPath = '/'): array
    {
        if (!file_exists($archivePath)) {
            return ['success' => false, 'error' => 'Archive not found'];
        }

        try {
            $cmd = "tar -xf '{$archivePath}' -C '{$targetPath}'";
            
            $process = Process::fromShellCommandline($cmd);
            $process->setTimeout(self::DEFAULT_TIMEOUT);
            $process->run();

            if (!$process->isSuccessful()) {
                return ['success' => false, 'error' => $process->getErrorOutput()];
            }

            return ['success' => true, 'message' => 'Files restored successfully'];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Apply retention policy - keep N most recent backups
     */
    public function applyRetention(string $remoteName, string $remotePath, int $keepLast = 7): array
    {
        try {
            // List files
            $listCmd = "rclone --config /etc/rclone/rclone.conf lsf '{$remoteName}:{$remotePath}/' --format 'tp' | sort -r";
            $process = Process::fromShellCommandline($listCmd);
            $process->run();
            
            $files = array_filter(explode("\n", trim($process->getOutput())));
            
            // Group by type (db_ or files_)
            $dbFiles = array_filter($files, fn($f) => str_contains($f, 'db_'));
            $fileBackups = array_filter($files, fn($f) => str_contains($f, 'files_'));
            
            $toDelete = [];
            
            // Keep only last N of each type
            if (count($dbFiles) > $keepLast) {
                $toDelete = array_merge($toDelete, array_slice($dbFiles, $keepLast));
            }
            if (count($fileBackups) > $keepLast) {
                $toDelete = array_merge($toDelete, array_slice($fileBackups, $keepLast));
            }
            
            // Delete old files
            foreach ($toDelete as $file) {
                $fileName = trim(explode(' ', $file)[1] ?? $file);
                $deleteCmd = "rclone --config /etc/rclone/rclone.conf delete '{$remoteName}:{$remotePath}/{$fileName}'";
                Process::fromShellCommandline($deleteCmd)->run();
            }
            
            return ['success' => true, 'deleted' => count($toDelete)];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cleanup local temp files
     */
    public function cleanup(): void
    {
        // Remove archives older than 1 day
        $cmd = "find " . self::ARCHIVE_DIR . " -type f -mtime +1 -delete 2>/dev/null";
        Process::fromShellCommandline($cmd)->run();
        
        // Remove SQL dumps
        $cmd = "rm -f " . self::SQL_DUMP_DIR . "/*.sql 2>/dev/null";
        Process::fromShellCommandline($cmd)->run();
    }

    // === Helper methods ===

    protected function ensureDirectories(): void
    {
        if (!is_dir(self::SQL_DUMP_DIR)) {
            mkdir(self::SQL_DUMP_DIR, 0755, true);
        }
        if (!is_dir(self::ARCHIVE_DIR)) {
            mkdir(self::ARCHIVE_DIR, 0755, true);
        }
    }

    protected function getDatabases(BackupConfig $config): array
    {
        // Get from config or auto-detect from sites
        $databases = $config->databases ?? [];
        
        if (empty($databases)) {
            // Auto-detect: Get all non-system databases
            $cmd = "mysql --defaults-file=/etc/mysql/vsispanel.cnf -N -e \"SHOW DATABASES\" | grep -Ev '^(information_schema|performance_schema|mysql|sys)$'";
            $process = Process::fromShellCommandline($cmd);
            $process->run();
            $databases = array_filter(explode("\n", trim($process->getOutput())));
        }
        
        return $databases;
    }

    protected function getFilePaths(BackupConfig $config): array
    {
        $paths = $config->include_paths ?? [];
        
        if (empty($paths)) {
            // Default: backup WordPress uploads only
            $paths = glob('/home/*/domains/*/public_html/wp-content/uploads') ?: 
                     glob('/home/*/public_html/wp-content/uploads') ?: 
                     ['/home/administrator/domains'];
        }
        
        // Filter to only existing paths
        return array_filter($paths, 'is_dir');
    }

    protected function buildMysqlDumpCommand(array $databases, string $outputFile): string
    {
        $creds = $this->getMysqlCredentials();
        $dbList = implode(' ', array_map('escapeshellarg', $databases));
        
        return "mysqldump --defaults-file=/etc/mysql/vsispanel.cnf --databases {$dbList} --single-transaction --quick > '{$outputFile}' 2>&1";
    }

    protected function getMysqlCredentials(): array
    {
        return [
            'user' => config('database.connections.mysql.username', 'root'),
            'password' => config('database.connections.mysql.password', ''),
            'host' => config('database.connections.mysql.host', '127.0.0.1'),
        ];
    }

    protected function calculateTotalSize(array $paths): int
    {
        $total = 0;
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $total += filesize($path);
            }
        }
        return $total;
    }

    /**
     * Get repository password (for compatibility)
     */
    protected function getRepositoryPassword(BackupConfig $config): string
    {
        $destinationConfig = $config->destination_config ?? [];
        return $destinationConfig['password'] ?? config('backup.default_password', 'vsispanel_backup_secret');
    }

    /**
     * Initialize repository - stub for compatibility (no restic needed)
     */
    public function initRepository(BackupConfig $config): array
    {
        return [
            'success' => true,
            'message' => 'Repository initialized (simple backup mode)',
        ];
    }

    /**
     * Check repository - stub for compatibility
     */
    public function checkRepository(BackupConfig $config): array
    {
        return [
            'success' => true,
            'message' => 'Repository OK',
        ];
    }


    /**
     * Delete a snapshot (local archive files)
     */
    public function deleteSnapshot(BackupConfig $config, string $snapshotId): array
    {
        // Delete local archive files matching snapshot
        $pattern = self::ARCHIVE_DIR . "/*_{$snapshotId}*";
        $files = glob($pattern);
        $deleted = 0;
        foreach ($files as $file) {
            if (@unlink($file)) $deleted++;
        }
        return ['success' => true, 'deleted' => $deleted];
    }

    /**
     * Check if a snapshot/archive exists locally
     */
    public function snapshotExists(BackupConfig $config, ?string $snapshotId): bool
    {
        if (!$snapshotId) {
            return false;
        }

        // Check for archives matching the snapshot timestamp
        $pattern = self::ARCHIVE_DIR . "/*_{$snapshotId}.*";
        $matches = glob($pattern);
        return !empty($matches);
    }

    /**
     * Restore a backup (databases and/or files)
     */
    public function restore(Backup $backup, string $targetPath, array $includePaths = []): array
    {
        $snapshotId = $backup->snapshot_id;
        $metadata = $backup->metadata ?? [];
        $archives = $metadata['archives'] ?? [];
        $config = $backup->backupConfig;
        $backupItems = $config->backup_items ?? ['databases', 'files'];
        $results = [];
        $totalFiles = 0;
        $totalBytes = 0;

        Log::info('Starting restore', [
            'backup_id' => $backup->id,
            'snapshot_id' => $snapshotId,
            'target_path' => $targetPath,
            'backup_items' => $backupItems,
        ]);

        // Find archive files - check local first, then metadata paths
        $archiveFiles = $this->findArchiveFiles($snapshotId, $archives);

        if (empty($archiveFiles['db']) && empty($archiveFiles['files'])) {
            return ['success' => false, 'error' => 'No archive files found for snapshot: ' . $snapshotId];
        }

        // Restore databases
        if (in_array('databases', $backupItems) && !empty($archiveFiles['db'])) {
            foreach ($archiveFiles['db'] as $dbArchive) {
                if (!empty($includePaths) && !$this->matchesIncludePaths($dbArchive, $includePaths)) {
                    continue;
                }
                $dbResult = $this->restoreDatabase($dbArchive);
                $results[] = $dbResult;
                if ($dbResult['success']) {
                    $totalFiles++;
                    $totalBytes += filesize($dbArchive) ?: 0;
                }
            }
        }

        // Restore files
        if (in_array('files', $backupItems) && !empty($archiveFiles['files'])) {
            foreach ($archiveFiles['files'] as $fileArchive) {
                $fileResult = $this->restoreFiles($fileArchive, $targetPath);
                $results[] = $fileResult;
                if ($fileResult['success']) {
                    $totalFiles++;
                    $totalBytes += filesize($fileArchive) ?: 0;
                }
            }
        }

        $hasErrors = collect($results)->contains(fn($r) => !$r['success']);

        return [
            'success' => !$hasErrors || $totalFiles > 0,
            'output' => "Restored {$totalFiles} archives (" . $this->formatBytesHelper($totalBytes) . ")",
            'files_restored' => $totalFiles,
            'bytes_restored' => $totalBytes,
            'results' => $results,
        ];
    }

    /**
     * Find archive files for a given snapshot ID
     */
    protected function findArchiveFiles(?string $snapshotId, array $metadataArchives = []): array
    {
        $dbFiles = [];
        $fileArchives = [];

        // Search in local archive dir
        if ($snapshotId && is_dir(self::ARCHIVE_DIR)) {
            $allFiles = glob(self::ARCHIVE_DIR . "/*_{$snapshotId}*");
            foreach ($allFiles as $file) {
                $basename = basename($file);
                if (str_starts_with($basename, 'db_')) {
                    $dbFiles[] = $file;
                } elseif (str_starts_with($basename, 'files_')) {
                    $fileArchives[] = $file;
                }
            }
        }

        // Also check metadata paths if local not found
        if (empty($dbFiles) && empty($fileArchives)) {
            foreach ($metadataArchives as $path) {
                if (is_string($path) && file_exists($path)) {
                    $basename = basename($path);
                    if (str_starts_with($basename, 'db_')) {
                        $dbFiles[] = $path;
                    } elseif (str_starts_with($basename, 'files_')) {
                        $fileArchives[] = $path;
                    }
                }
            }
        }

        return ['db' => $dbFiles, 'files' => $fileArchives];
    }

    /**
     * Download archive files from remote storage
     */
    public function downloadFromRemote(string $remoteName, string $remotePath, string $snapshotId): array
    {
        $this->ensureDirectories();
        $downloadedFiles = [];

        // List files matching snapshot
        $listCmd = "rclone --config /etc/rclone/rclone.conf lsf '{$remoteName}:{$remotePath}/' 2>/dev/null | grep '{$snapshotId}'";
        $process = Process::fromShellCommandline($listCmd);
        $process->setTimeout(120);
        $process->run();

        $files = array_filter(explode("\n", trim($process->getOutput())));

        if (empty($files)) {
            return ['success' => false, 'error' => "No files found on remote for snapshot {$snapshotId}"];
        }

        foreach ($files as $fileName) {
            $fileName = trim($fileName);
            if (empty($fileName)) continue;

            $localPath = self::ARCHIVE_DIR . '/' . $fileName;
            $copyCmd = "rclone --config /etc/rclone/rclone.conf copy '{$remoteName}:{$remotePath}/{$fileName}' '" . self::ARCHIVE_DIR . "/' 2>&1";
            $process = Process::fromShellCommandline($copyCmd);
            $process->setTimeout(self::DEFAULT_TIMEOUT);
            $process->run();

            if ($process->isSuccessful() && file_exists($localPath)) {
                $downloadedFiles[] = $localPath;
                Log::info("Downloaded from remote: {$fileName}");
            } else {
                Log::error("Failed to download: {$fileName}", ['error' => $process->getErrorOutput()]);
            }
        }

        return [
            'success' => !empty($downloadedFiles),
            'files' => $downloadedFiles,
        ];
    }

    protected function matchesIncludePaths(string $archivePath, array $includePaths): bool
    {
        $basename = basename($archivePath);
        foreach ($includePaths as $pattern) {
            if (str_contains($basename, $pattern)) {
                return true;
            }
        }
        return empty($includePaths);
    }


    protected function extractDbNameFromArchive(string $archivePath): ?string
    {
        $basename = basename($archivePath);
        // Pattern: db_<dbname>_<YYYY-MM-DD_HH-ii-ss>.sql.gz
        if (preg_match('/^db_(.+)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/', $basename, $matches)) {
            return $matches[1];
        }
        return null;
    }
    protected function formatBytesHelper(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

}
