<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RcloneService
{
    protected string $configPath = '/etc/rclone/rclone.conf';

    /**
     * Get the config flag for rclone commands
     */
    protected function getConfigFlag(): string
    {
        return "--config {$this->configPath}";
    }

    /**
     * Build rclone command with config flag
     */
    protected function rclone(string $subCommand): string
    {
        return "rclone {$this->getConfigFlag()} {$subCommand}";
    }

    /**
     * Check if rclone is installed
     */
    public function isInstalled(): bool
    {
        $result = $this->runCommand('which rclone');
        return $result['success'] && !empty(trim($result['output']));
    }

    /**
     * Install rclone
     */
    public function install(): array
    {
        // Download and install rclone using the official script
        $result = $this->runCommand('curl https://rclone.org/install.sh | sudo bash', 120);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to install rclone: ' . $result['error'],
            ];
        }

        // Create config directory
        $this->runCommand('mkdir -p /etc/rclone');

        // Create empty config file if it doesn't exist
        if (!file_exists($this->configPath)) {
            file_put_contents($this->configPath, '');
            chmod($this->configPath, 0600);
        }

        Log::info('Rclone installed successfully');

        return [
            'success' => true,
            'message' => 'Rclone installed successfully',
        ];
    }

    /**
     * Get rclone version
     */
    public function getVersion(): ?string
    {
        $result = $this->runCommand($this->rclone('version --check'));
        if ($result['success']) {
            if (preg_match('/rclone v([\d.]+)/', $result['output'], $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * List all configured remotes
     */
    public function listRemotes(): array
    {
        $result = $this->runCommand($this->rclone('listremotes'));
        if (!$result['success']) {
            return [];
        }

        $remotes = array_filter(array_map('trim', explode("\n", $result['output'])));
        return array_map(fn($remote) => rtrim($remote, ':'), $remotes);
    }

    /**
     * Get remote configuration
     */
    public function getRemoteConfig(string $remoteName): array
    {
        $result = $this->runCommand($this->rclone("config show {$remoteName}"));
        if (!$result['success']) {
            return [];
        }

        $config = [];
        $lines = explode("\n", $result['output']);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $config[$key] = $value;
            }
        }

        return $config;
    }

    /**
     * Create a new remote
     */
    public function createRemote(string $name, string $type, array $config): array
    {
        // Validate name (alphanumeric and underscores only)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            return [
                'success' => false,
                'error' => 'Invalid remote name. Use only letters, numbers, and underscores.',
            ];
        }

        // For OAuth remotes (drive, onedrive, dropbox), write directly to config file
        // to avoid rclone trying to start its own OAuth server
        $hasToken = isset($config['token']) && !empty($config['token']);
        $isOAuthType = in_array($type, ['drive', 'onedrive', 'dropbox']);

        Log::debug('createRemote check', [
            'name' => $name,
            'type' => $type,
            'hasToken' => $hasToken,
            'isOAuthType' => $isOAuthType,
            'tokenValue' => isset($config['token']) ? substr((string)$config['token'], 0, 50) . '...' : 'null',
        ]);

        if ($hasToken && $isOAuthType) {
            Log::info('Using createOAuthRemote for direct config file write', ['name' => $name, 'type' => $type]);
            return $this->createOAuthRemote($name, $type, $config);
        }

        // Build the rclone config command parameters
        $params = $this->buildConfigParams($type, $config);

        // Create remote using rclone config create
        $paramStr = implode(' ', $params);
        $result = $this->runCommand($this->rclone("config create {$name} {$type} {$paramStr}"));

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to create remote: ' . $result['error'],
            ];
        }

        Log::info('Rclone remote created', ['name' => $name, 'type' => $type]);

        return [
            'success' => true,
            'message' => 'Remote created successfully',
        ];
    }

    /**
     * Create OAuth remote by directly writing to config file
     * This avoids rclone trying to start its own OAuth server
     */
    protected function createOAuthRemote(string $name, string $type, array $config): array
    {
        try {
            // Ensure config directory exists
            $configDir = dirname($this->configPath);
            if (!is_dir($configDir)) {
                mkdir($configDir, 0755, true);
            }

            // Read existing config
            $configContent = file_exists($this->configPath) ? file_get_contents($this->configPath) : '';

            // Check if remote already exists
            if (preg_match('/^\[' . preg_quote($name, '/') . '\]/m', $configContent)) {
                return [
                    'success' => false,
                    'error' => 'Remote with this name already exists',
                ];
            }

            // Build config section
            $section = "\n[{$name}]\n";
            $section .= "type = {$type}\n";

            foreach ($config as $key => $value) {
                if (!empty($value)) {
                    $section .= "{$key} = {$value}\n";
                }
            }

            // Append to config file
            file_put_contents($this->configPath, $configContent . $section);

            // Set proper permissions
            chmod($this->configPath, 0600);

            Log::info('Rclone OAuth remote created', ['name' => $name, 'type' => $type]);

            return [
                'success' => true,
                'message' => 'Remote created successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create OAuth remote', [
                'name' => $name,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create remote: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update an existing remote
     * For OAuth remotes (drive, onedrive, dropbox), we edit the config file directly
     * to avoid triggering re-authentication
     */
    public function updateRemote(string $name, array $config): array
    {
        // Get current remote config to check if it's an OAuth type
        $currentConfig = $this->getRemoteConfig($name);
        $remoteType = $currentConfig['type'] ?? '';
        $isOAuthType = in_array($remoteType, ['drive', 'onedrive', 'dropbox']);

        if ($isOAuthType) {
            // For OAuth remotes, directly edit the config file to avoid re-auth
            return $this->updateRemoteConfigFile($name, $config);
        }

        // For non-OAuth remotes, use rclone config update
        foreach ($config as $key => $value) {
            if (!empty($value)) {
                $escapedValue = escapeshellarg($value);
                $result = $this->runCommand($this->rclone("config update {$name} {$key} {$escapedValue}"));
                if (!$result['success']) {
                    return [
                        'success' => false,
                        'error' => "Failed to update {$key}: " . $result['error'],
                    ];
                }
            }
        }

        Log::info('Rclone remote updated', ['name' => $name]);

        return [
            'success' => true,
            'message' => 'Remote updated successfully',
        ];
    }

    /**
     * Update remote config by directly editing the config file
     * This avoids OAuth re-authentication for cloud storage remotes
     */
    protected function updateRemoteConfigFile(string $name, array $updates): array
    {
        try {
            if (!file_exists($this->configPath)) {
                return [
                    'success' => false,
                    'error' => 'Rclone config file not found',
                ];
            }

            $configContent = file_get_contents($this->configPath);
            $lines = explode("\n", $configContent);
            $newLines = [];
            $inSection = false;
            $sectionFound = false;
            $updatedKeys = [];

            foreach ($lines as $line) {
                // Check if we're entering the target section
                if (preg_match('/^\[' . preg_quote($name, '/') . '\]$/', trim($line))) {
                    $inSection = true;
                    $sectionFound = true;
                    $newLines[] = $line;
                    continue;
                }

                // Check if we're leaving the section (entering a new one)
                if ($inSection && preg_match('/^\[.+\]$/', trim($line))) {
                    // Before leaving, add any keys that weren't updated
                    foreach ($updates as $key => $value) {
                        if (!in_array($key, $updatedKeys) && $value !== null && $value !== '') {
                            $newLines[] = "{$key} = {$value}";
                        }
                    }
                    $inSection = false;
                }

                // If we're in the target section, check if this line should be updated
                if ($inSection && preg_match('/^(\w+)\s*=\s*(.*)$/', $line, $matches)) {
                    $key = $matches[1];
                    if (array_key_exists($key, $updates)) {
                        // Update the value if provided, or keep existing if null/empty
                        if ($updates[$key] !== null && $updates[$key] !== '') {
                            $newLines[] = "{$key} = {$updates[$key]}";
                        } else {
                            $newLines[] = $line; // Keep existing value
                        }
                        $updatedKeys[] = $key;
                        continue;
                    }
                }

                $newLines[] = $line;
            }

            // If we ended while still in the section, add remaining keys
            if ($inSection) {
                foreach ($updates as $key => $value) {
                    if (!in_array($key, $updatedKeys) && $value !== null && $value !== '') {
                        // Insert before the last empty line if exists
                        $lastLine = array_pop($newLines);
                        $newLines[] = "{$key} = {$value}";
                        if (!empty(trim($lastLine))) {
                            $newLines[] = $lastLine;
                        }
                    }
                }
            }

            if (!$sectionFound) {
                return [
                    'success' => false,
                    'error' => "Remote '{$name}' not found in config file",
                ];
            }

            // Write back to config file
            file_put_contents($this->configPath, implode("\n", $newLines));
            chmod($this->configPath, 0600);

            Log::info('Rclone remote config updated via file edit', ['name' => $name, 'updates' => array_keys($updates)]);

            return [
                'success' => true,
                'message' => 'Remote updated successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update remote config file', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to update remote: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a remote
     */
    public function deleteRemote(string $name): array
    {
        $result = $this->runCommand($this->rclone("config delete {$name}"));

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to delete remote: ' . $result['error'],
            ];
        }

        Log::info('Rclone remote deleted', ['name' => $name]);

        return [
            'success' => true,
            'message' => 'Remote deleted successfully',
        ];
    }

    /**
     * Test remote connection
     */
    public function testConnection(string $name): array
    {
        $result = $this->runCommand($this->rclone("lsd {$name}: --max-depth 0"), 30);

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Connection successful' : ($result['error'] ?: 'Connection failed'),
        ];
    }

    /**
     * Get available space on remote (if supported)
     */
    public function getRemoteSpace(string $name): ?array
    {
        $result = $this->runCommand($this->rclone("about {$name}: --json"), 30);

        if (!$result['success']) {
            return null;
        }

        $data = json_decode($result['output'], true);
        return $data ?: null;
    }

    /**
     * List backup folders on a remote storage
     * Returns info about each vsispanel-backup-* folder
     */
    public function listBackupFolders(string $remoteName, string $basePath): array
    {
        $remotePath = "{$remoteName}:{$basePath}";

        Log::info('listBackupFolders: Listing backup folders', [
            'remoteName' => $remoteName,
            'basePath' => $basePath,
            'remotePath' => $remotePath,
        ]);

        // List directories at the base path
        $result = $this->runCommand($this->rclone("lsjson \"{$remotePath}\" --dirs-only"), 120);

        if (!$result['success']) {
            Log::warning('listBackupFolders: Failed to list remote path', [
                'remotePath' => $remotePath,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
            return [];
        }

        $folders = json_decode($result['output'], true) ?: [];
        $backupFolders = [];

        foreach ($folders as $folder) {
            $name = $folder['Name'] ?? '';

            // Only include vsispanel-backup-* folders
            if (str_starts_with($name, 'vsispanel-backup-')) {
                $configId = substr($name, strlen('vsispanel-backup-'));
                $folderPath = "{$remotePath}/{$name}";

                // Get folder size
                $sizeResult = $this->runCommand($this->rclone("size \"{$folderPath}\" --json"), 60);
                $sizeData = json_decode($sizeResult['output'] ?? '{}', true);

                // Get modification time from the folder itself
                $modTime = $folder['ModTime'] ?? null;

                $backupFolders[] = [
                    'name' => $name,
                    'config_id' => $configId,
                    'path' => $folderPath,
                    'size_bytes' => $sizeData['bytes'] ?? 0,
                    'file_count' => $sizeData['count'] ?? 0,
                    'modified_at' => $modTime,
                ];
            }
        }

        Log::info('listBackupFolders: Found backup folders', [
            'count' => count($backupFolders),
        ]);

        return $backupFolders;
    }

    /**
     * List files in remote path
     */
    public function listFiles(string $remotePath, int $maxDepth = 1): array
    {
        Log::debug('listFiles: Checking remote path', [
            'remotePath' => $remotePath,
            'maxDepth' => $maxDepth,
        ]);

        $result = $this->runCommand($this->rclone("lsf \"{$remotePath}\" --max-depth {$maxDepth}"), 60);

        Log::debug('listFiles: Result', [
            'success' => $result['success'],
            'output' => substr($result['output'] ?? '', 0, 500),
            'error' => substr($result['error'] ?? '', 0, 500),
        ]);

        if (!$result['success']) {
            Log::warning('listFiles: Failed to list remote path', [
                'remotePath' => $remotePath,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
            return [];
        }

        $files = array_filter(array_map('trim', explode("\n", $result['output'])));

        Log::debug('listFiles: Found files', [
            'count' => count($files),
            'files' => array_slice($files, 0, 10),
        ]);

        return $files;
    }

    /**
     * Copy file to remote
     */
    public function copyToRemote(string $localPath, string $remotePath): array
    {
        $result = $this->runCommand($this->rclone("copy {$localPath} {$remotePath} --progress"), 3600);

        return [
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'output' => $result['output'] ?? null,
        ];
    }

    /**
     * Copy file from remote
     */
    public function copyFromRemote(string $remotePath, string $localPath): array
    {
        $result = $this->runCommand($this->rclone("copy {$remotePath} {$localPath} --progress"), 3600);

        return [
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'output' => $result['output'] ?? null,
        ];
    }

    /**
     * Create a compressed archive of the backup repository
     * Returns the path to the created archive
     */
    public function createBackupArchive(string $sourcePath, string $archiveName): array
    {
        $tempDir = sys_get_temp_dir();
        $archivePath = "{$tempDir}/{$archiveName}";

        Log::info('createBackupArchive: Creating archive', [
            'sourcePath' => $sourcePath,
            'archivePath' => $archivePath,
        ]);

        // Use tar with gzip compression
        // -C changes to source directory, . includes all files
        $command = "tar -czf \"{$archivePath}\" -C \"{$sourcePath}\" . 2>&1";

        try {
            $result = Process::timeout(3600)->run($command);

            if (!$result->successful()) {
                Log::error('createBackupArchive: Failed to create archive', [
                    'error' => $result->errorOutput(),
                    'output' => $result->output(),
                ]);
                return [
                    'success' => false,
                    'error' => 'Failed to create archive: ' . $result->errorOutput(),
                ];
            }

            // Verify archive was created
            if (!file_exists($archivePath)) {
                return [
                    'success' => false,
                    'error' => 'Archive file was not created',
                ];
            }

            $archiveSize = filesize($archivePath);
            Log::info('createBackupArchive: Archive created successfully', [
                'archivePath' => $archivePath,
                'sizeBytes' => $archiveSize,
                'sizeFormatted' => $this->formatBytes($archiveSize),
            ]);

            return [
                'success' => true,
                'archive_path' => $archivePath,
                'size_bytes' => $archiveSize,
            ];
        } catch (\Exception $e) {
            Log::error('createBackupArchive: Exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract a backup archive to the target directory
     */
    public function extractBackupArchive(string $archivePath, string $targetPath): array
    {
        Log::info('extractBackupArchive: Extracting archive', [
            'archivePath' => $archivePath,
            'targetPath' => $targetPath,
        ]);

        // Create target directory if it doesn't exist
        if (!is_dir($targetPath)) {
            if (!mkdir($targetPath, 0755, true)) {
                return [
                    'success' => false,
                    'error' => "Failed to create target directory: {$targetPath}",
                ];
            }
        }

        // Use tar to extract
        $command = "tar -xzf \"{$archivePath}\" -C \"{$targetPath}\" 2>&1";

        try {
            $result = Process::timeout(3600)->run($command);

            if (!$result->successful()) {
                Log::error('extractBackupArchive: Failed to extract archive', [
                    'error' => $result->errorOutput(),
                    'output' => $result->output(),
                ]);
                return [
                    'success' => false,
                    'error' => 'Failed to extract archive: ' . $result->errorOutput(),
                ];
            }

            // Verify extraction
            $files = glob($targetPath . '/*');
            if (empty($files)) {
                return [
                    'success' => false,
                    'error' => 'Archive extracted but no files found',
                ];
            }

            Log::info('extractBackupArchive: Archive extracted successfully', [
                'targetPath' => $targetPath,
                'fileCount' => count($files),
            ]);

            return [
                'success' => true,
                'file_count' => count($files),
            ];
        } catch (\Exception $e) {
            Log::error('extractBackupArchive: Exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload a single file to remote storage
     */
    public function uploadFile(string $localFilePath, string $remotePath): array
    {
        if (!file_exists($localFilePath)) {
            return [
                'success' => false,
                'error' => "Local file does not exist: {$localFilePath}",
            ];
        }

        Log::info('uploadFile: Uploading file to remote', [
            'localFile' => $localFilePath,
            'remotePath' => $remotePath,
            'fileSize' => filesize($localFilePath),
        ]);

        // Use rclone copyto for single file
        $command = $this->rclone("copyto \"{$localFilePath}\" \"{$remotePath}\" -v --stats 1s 2>&1");

        $result = $this->runCommand($command, 3600);

        Log::info('uploadFile: Result', [
            'success' => $result['success'],
            'exit_code' => $result['exit_code'] ?? 'unknown',
        ]);

        return [
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'output' => $result['output'] ?? null,
        ];
    }

    /**
     * Download a single file from remote storage
     */
    public function downloadFile(string $remotePath, string $localFilePath): array
    {
        Log::info('downloadFile: Downloading file from remote', [
            'remotePath' => $remotePath,
            'localFile' => $localFilePath,
        ]);

        // Create parent directory if it doesn't exist
        $parentDir = dirname($localFilePath);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        // Use rclone copyto for single file
        $command = $this->rclone("copyto \"{$remotePath}\" \"{$localFilePath}\" -v --stats 1s 2>&1");

        $result = $this->runCommand($command, 3600);

        if ($result['success'] && !file_exists($localFilePath)) {
            return [
                'success' => false,
                'error' => 'Download completed but file not found locally',
            ];
        }

        Log::info('downloadFile: Result', [
            'success' => $result['success'],
            'fileExists' => file_exists($localFilePath),
            'fileSize' => file_exists($localFilePath) ? filesize($localFilePath) : 0,
        ]);

        return [
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'output' => $result['output'] ?? null,
            'file_size' => file_exists($localFilePath) ? filesize($localFilePath) : 0,
        ];
    }

    /**
     * List backup archive files on remote storage
     */
    public function listBackupArchives(string $remoteName, string $basePath): array
    {
        $remotePath = "{$remoteName}:{$basePath}";

        Log::info('listBackupArchives: Listing archives', [
            'remotePath' => $remotePath,
        ]);

        // List files with .tar.gz extension
        $result = $this->runCommand($this->rclone("lsjson \"{$remotePath}\" --files-only"), 120);

        if (!$result['success']) {
            Log::warning('listBackupArchives: Failed to list', [
                'error' => $result['error'] ?? 'Unknown error',
            ]);
            return [];
        }

        $files = json_decode($result['output'], true) ?: [];
        $archives = [];

        foreach ($files as $file) {
            $name = $file['Name'] ?? '';
            // Only include .tar.gz files that match our naming pattern
            if (preg_match('/^(.+)_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})\.tar\.gz$/', $name, $matches)) {
                $archives[] = [
                    'name' => $name,
                    'config_name' => $matches[1],
                    'date' => $matches[2],
                    'time' => str_replace('-', ':', $matches[3]),
                    'datetime' => $matches[2] . ' ' . str_replace('-', ':', $matches[3]),
                    'path' => "{$remotePath}/{$name}",
                    'size_bytes' => $file['Size'] ?? 0,
                    'modified_at' => $file['ModTime'] ?? null,
                ];
            }
        }

        // Sort by datetime descending (newest first)
        usort($archives, fn($a, $b) => strcmp($b['datetime'], $a['datetime']));

        Log::info('listBackupArchives: Found archives', [
            'count' => count($archives),
        ]);

        return $archives;
    }

    /**
     * Delete local archive file after successful upload
     */
    public function cleanupArchive(string $archivePath): void
    {
        if (file_exists($archivePath)) {
            unlink($archivePath);
            Log::debug('cleanupArchive: Deleted local archive', ['path' => $archivePath]);
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
     * Sync remote to local (download backup from remote storage)
     */
    public function syncFromRemote(string $remotePath, string $localPath): array
    {
        // Create local path if it doesn't exist
        if (!is_dir($localPath)) {
            if (!mkdir($localPath, 0755, true)) {
                Log::error('syncFromRemote: Failed to create local path', ['path' => $localPath]);
                return [
                    'success' => false,
                    'error' => "Failed to create local path: {$localPath}",
                    'output' => null,
                ];
            }
        }

        Log::info('syncFromRemote: Starting sync from remote', [
            'remotePath' => $remotePath,
            'localPath' => $localPath,
        ]);

        // Use sync command to download from remote to local
        // Use -v for verbose output
        $command = $this->rclone("sync \"{$remotePath}\" \"{$localPath}\" -v --stats 1s 2>&1");

        Log::info('syncFromRemote: Running command', ['command' => $command]);

        $result = $this->runCommand($command, 3600);

        Log::info('syncFromRemote: Command result', [
            'success' => $result['success'],
            'exit_code' => $result['exit_code'] ?? 'unknown',
            'output' => substr($result['output'] ?? '', 0, 2000),
            'error' => substr($result['error'] ?? '', 0, 2000),
        ]);

        // Check if there's any indication of failure in the output
        $output = ($result['output'] ?? '') . ($result['error'] ?? '');
        $hasError = preg_match('/ERROR|FATAL|failed|Failed|denied|Denied|timeout|Timeout/i', $output);

        if ($hasError && $result['success']) {
            Log::warning('syncFromRemote: Command succeeded but output contains errors', [
                'output' => $output,
            ]);
        }

        // Verify sync by checking if files exist locally
        $verified = false;
        if ($result['success'] && !$hasError) {
            $localFiles = glob($localPath . '/*');
            $verified = !empty($localFiles);

            if (!$verified) {
                Log::warning('syncFromRemote: Sync reported success but no files found locally', [
                    'localPath' => $localPath,
                ]);
                return [
                    'success' => false,
                    'error' => 'Sync completed but no files found locally - verification failed',
                    'output' => $result['output'] ?? null,
                ];
            }

            Log::info('syncFromRemote: Sync verified', [
                'localFileCount' => count($localFiles),
            ]);
        }

        return [
            'success' => $result['success'] && !$hasError && $verified,
            'error' => $result['error'] ?? ($hasError ? "Sync had errors: " . substr($output, 0, 500) : null),
            'output' => $result['output'] ?? null,
        ];
    }

    /**
     * Sync local to remote
     */
    public function syncToRemote(string $localPath, string $remotePath): array
    {
        // Check if local path exists and has content
        if (!is_dir($localPath)) {
            Log::error('syncToRemote: Local path does not exist', ['path' => $localPath]);
            return [
                'success' => false,
                'error' => "Local path does not exist: {$localPath}",
                'output' => null,
            ];
        }

        // Check local path size
        $localFiles = glob($localPath . '/*');
        Log::info('syncToRemote: Starting sync', [
            'localPath' => $localPath,
            'remotePath' => $remotePath,
            'localFileCount' => count($localFiles),
            'localFiles' => array_map('basename', array_slice($localFiles, 0, 10)),
        ]);

        // Use -v for verbose output instead of --progress (which goes to stderr)
        // Also add --stats 0 to disable stats (cleaner output) and -v for verbose
        $command = $this->rclone("sync \"{$localPath}\" \"{$remotePath}\" -v --stats 1s 2>&1");

        Log::info('syncToRemote: Running command', ['command' => $command]);

        $result = $this->runCommand($command, 3600);

        Log::info('syncToRemote: Command result', [
            'success' => $result['success'],
            'exit_code' => $result['exit_code'] ?? 'unknown',
            'output' => substr($result['output'] ?? '', 0, 2000),
            'error' => substr($result['error'] ?? '', 0, 2000),
        ]);

        // Check if there's any indication of failure in the output even with exit code 0
        $output = ($result['output'] ?? '') . ($result['error'] ?? '');
        $hasError = preg_match('/ERROR|FATAL|failed|Failed|denied|Denied|timeout|Timeout/i', $output);

        if ($hasError && $result['success']) {
            Log::warning('syncToRemote: Command succeeded but output contains errors', [
                'output' => $output,
            ]);
        }

        return [
            'success' => $result['success'] && !$hasError,
            'error' => $result['error'] ?? ($hasError ? "Sync had errors: " . substr($output, 0, 500) : null),
            'output' => $result['output'] ?? null,
        ];
    }

    /**
     * Delete file/folder on remote
     */
    public function deleteOnRemote(string $remotePath): array
    {
        $result = $this->runCommand($this->rclone("delete {$remotePath}"));

        return [
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Get supported remote types
     */
    public function getSupportedTypes(): array
    {
        return [
            'ftp' => [
                'name' => 'FTP',
                'description' => 'FTP Connection',
                'fields' => ['host', 'user', 'pass', 'port'],
            ],
            'sftp' => [
                'name' => 'SFTP',
                'description' => 'SSH/SFTP Connection',
                'fields' => ['host', 'user', 'pass', 'port', 'key_file'],
            ],
            'drive' => [
                'name' => 'Google Drive',
                'description' => 'Google Drive storage',
                'fields' => ['client_id', 'client_secret', 'token', 'root_folder_id'],
            ],
            'onedrive' => [
                'name' => 'OneDrive',
                'description' => 'Microsoft OneDrive storage',
                'fields' => ['client_id', 'client_secret', 'token', 'drive_id'],
            ],
            'dropbox' => [
                'name' => 'Dropbox',
                'description' => 'Dropbox storage',
                'fields' => ['client_id', 'client_secret', 'token'],
            ],
            's3' => [
                'name' => 'Amazon S3',
                'description' => 'Amazon S3 compatible storage',
                'fields' => ['provider', 'access_key_id', 'secret_access_key', 'region', 'endpoint', 'bucket'],
            ],
            'b2' => [
                'name' => 'Backblaze B2',
                'description' => 'Backblaze B2 storage',
                'fields' => ['account', 'key', 'bucket'],
            ],
            'webdav' => [
                'name' => 'WebDAV',
                'description' => 'WebDAV compatible servers',
                'fields' => ['url', 'user', 'pass', 'vendor'],
            ],
        ];
    }

    /**
     * Generate OAuth URL for drive-type remotes
     */
    public function generateOAuthUrl(string $type, array $config): ?string
    {
        // OAuth flow is complex and typically requires interactive authentication
        // For now, users need to run 'rclone config' manually for OAuth remotes
        // or use a pre-generated token
        return null;
    }

    /**
     * Build config parameters for rclone config create command
     */
    protected function buildConfigParams(string $type, array $config): array
    {
        $params = [];

        foreach ($config as $key => $value) {
            if (!empty($value)) {
                $escapedValue = escapeshellarg((string)$value);
                $params[] = "{$key}={$escapedValue}";
            }
        }

        return $params;
    }

    /**
     * Run a shell command
     */
    protected function runCommand(string $command, int $timeout = 30): array
    {
        Log::debug('Running rclone command', [
            'command' => $command,
            'timeout' => $timeout,
            'config_path' => $this->configPath,
            'config_exists' => file_exists($this->configPath),
            'user' => get_current_user(),
            'cwd' => getcwd(),
        ]);

        try {
            $result = Process::timeout($timeout)->run($command);

            $success = $result->successful();
            $output = $result->output();
            $error = $result->errorOutput();
            $exitCode = $result->exitCode();

            Log::debug('Rclone command completed', [
                'success' => $success,
                'exit_code' => $exitCode,
                'output_length' => strlen($output),
                'error_length' => strlen($error),
            ]);

            return [
                'success' => $success,
                'output' => $output,
                'error' => $error,
                'exit_code' => $exitCode,
            ];
        } catch (\Exception $e) {
            Log::error('Rclone command exception', [
                'command' => $command,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'exit_code' => -1,
            ];
        }
    }
}
