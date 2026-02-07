<?php

declare(strict_types=1);

namespace App\Modules\FileManager\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use App\Services\SystemCommandExecutor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class FileManagerService
{
    protected string $basePath;
    protected array $allowedExtensions;
    protected array $blockedExtensions;
    protected array $editableExtensions;
    protected array $hiddenPatterns;
    protected array $protectedPaths;
    protected int $maxUploadSize;
    protected int $maxEditableSize;
    protected SystemCommandExecutor $executor;

    public function __construct(SystemCommandExecutor $executor)
    {
        $this->executor = $executor;
        $this->basePath = config('filemanager.base_path', '/var/www/vhosts');
        $this->allowedExtensions = config('filemanager.allowed_extensions', []);
        $this->blockedExtensions = config('filemanager.blocked_extensions', []);
        $this->editableExtensions = config('filemanager.editable_extensions', []);
        $this->hiddenPatterns = config('filemanager.hidden_patterns', []);
        $this->protectedPaths = config('filemanager.protected_paths', []);
        $this->maxUploadSize = config('filemanager.max_upload_size', 104857600);
        $this->maxEditableSize = config('filemanager.max_editable_size', 2097152);
    }

    /**
     * Get the base path for a domain.
     * Uses domain's document_root if set, removing the public_html suffix.
     */
    public function getDomainPath(Domain $domain): string
    {
        // If domain has a custom document_root, use its parent directory
        if ($domain->document_root) {
            // document_root typically ends with /public_html, get the parent
            $docRoot = rtrim($domain->document_root, '/');
            $webRoot = config('filemanager.web_root', 'public_html');
            if (str_ends_with($docRoot, '/' . $webRoot)) {
                return dirname($docRoot);
            }
            return dirname($docRoot);
        }

        return $this->basePath . '/' . $domain->name;
    }

    /**
     * Get the web root path for a domain.
     * Uses domain's document_root directly if set.
     */
    public function getWebRootPath(Domain $domain): string
    {
        // If domain has a custom document_root, use it directly
        if ($domain->document_root) {
            return rtrim($domain->document_root, '/');
        }

        $webRoot = config('filemanager.web_root', 'public_html');
        return $this->getDomainPath($domain) . '/' . $webRoot;
    }

    /**
     * Resolve and validate a path within a domain.
     */
    public function resolvePath(Domain $domain, string $relativePath = ''): string
    {
        $basePath = $this->getDomainPath($domain);
        $relativePath = $this->sanitizePath($relativePath);

        $fullPath = $basePath . '/' . ltrim($relativePath, '/');
        $realPath = realpath($fullPath) ?: $fullPath;

        // Ensure path is within the domain's directory
        if (!str_starts_with($realPath, $basePath) && !str_starts_with($fullPath, $basePath)) {
            throw new RuntimeException('Access denied: Path is outside allowed directory.');
        }

        return $fullPath;
    }

    /**
     * Sanitize a path to prevent directory traversal.
     */
    protected function sanitizePath(string $path): string
    {
        // Remove null bytes
        $path = str_replace("\0", '', $path);

        // Normalize slashes
        $path = str_replace('\\', '/', $path);

        // Remove multiple slashes
        $path = preg_replace('#/+#', '/', $path);

        // Remove .. and resolve path
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }

    /**
     * Ensure domain directory structure exists.
     */
    public function ensureDomainDirectoryExists(Domain $domain): void
    {
        $domainPath = $this->getDomainPath($domain);

        if (!File::isDirectory($domainPath)) {
            // Create the domain directory with root permissions
            $this->executor->executeAsRoot('mkdir', ['-p', $domainPath]);

            // Create standard directories
            $webRoot = config('filemanager.web_root', 'public_html');
            $standardDirs = [
                $webRoot,
                'logs',
                'tmp',
            ];

            foreach ($standardDirs as $dir) {
                $dirPath = $domainPath . '/' . $dir;
                $this->executor->executeAsRoot('mkdir', ['-p', $dirPath]);
            }

            // Create a default index.html in public_html
            $indexPath = $domainPath . '/' . $webRoot . '/index.html';
            if (!File::exists($indexPath)) {
                $defaultContent = "<!DOCTYPE html>\n<html>\n<head>\n    <title>Welcome to {$domain->name}</title>\n</head>\n<body>\n    <h1>Welcome to {$domain->name}</h1>\n    <p>Your website is ready.</p>\n</body>\n</html>";
                // Write to temp file first, then move with root permissions
                $tempFile = sys_get_temp_dir() . '/vsispanel_' . uniqid() . '.html';
                File::put($tempFile, $defaultContent);
                $this->executor->executeAsRoot('cp', [$tempFile, $indexPath]);
                @unlink($tempFile);
            }

            // Set proper ownership for web server (www-data)
            $this->executor->executeAsRoot('chown', ['-R', 'www-data:www-data', $domainPath]);
            $this->executor->executeAsRoot('chmod', ['-R', '755', $domainPath]);
        }
    }

    /**
     * List files and directories in a path.
     */
    public function listDirectory(Domain $domain, string $relativePath = ''): array
    {
        // Ensure domain directory exists when accessing root
        if ($relativePath === '' || $relativePath === '/') {
            $this->ensureDomainDirectoryExists($domain);
        }

        $fullPath = $this->resolvePath($domain, $relativePath);

        if (!File::isDirectory($fullPath)) {
            throw new RuntimeException('Directory not found.');
        }

        $items = [];
        $entries = File::files($fullPath);
        $directories = File::directories($fullPath);

        // Add directories
        foreach ($directories as $dir) {
            $name = basename($dir);
            if ($this->isHidden($name)) {
                continue;
            }

            $items[] = [
                'name' => $name,
                'path' => $relativePath ? $relativePath . '/' . $name : $name,
                'type' => 'directory',
                'size' => $this->getDirectorySize($dir),
                'permissions' => substr(sprintf('%o', fileperms($dir)), -4),
                'modified_at' => date('c', filemtime($dir)),
            ];
        }

        // Add files
        foreach ($entries as $file) {
            $name = $file->getFilename();
            if ($this->isHidden($name)) {
                continue;
            }

            $items[] = [
                'name' => $name,
                'path' => $relativePath ? $relativePath . '/' . $name : $name,
                'type' => 'file',
                'size' => $file->getSize(),
                'extension' => $file->getExtension(),
                'mime_type' => File::mimeType($file->getPathname()),
                'permissions' => substr(sprintf('%o', $file->getPerms()), -4),
                'modified_at' => date('c', $file->getMTime()),
                'is_editable' => $this->isEditable($file->getPathname()),
            ];
        }

        // Sort: directories first, then by name
        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'path' => $relativePath,
            'items' => $items,
            'parent' => $relativePath ? dirname($relativePath) : null,
        ];
    }

    /**
     * Get file content for editing.
     */
    public function getFileContent(Domain $domain, string $relativePath): array
    {
        $fullPath = $this->resolvePath($domain, $relativePath);

        if (!File::exists($fullPath)) {
            throw new RuntimeException('File not found.');
        }

        if (File::isDirectory($fullPath)) {
            throw new RuntimeException('Cannot read directory as file.');
        }

        if (!$this->isEditable($fullPath)) {
            throw new RuntimeException('File type is not editable.');
        }

        $size = File::size($fullPath);
        if ($size > $this->maxEditableSize) {
            throw new RuntimeException('File is too large to edit. Maximum size: ' . $this->formatBytes($this->maxEditableSize));
        }

        return [
            'name' => basename($fullPath),
            'path' => $relativePath,
            'content' => File::get($fullPath),
            'size' => $size,
            'mime_type' => File::mimeType($fullPath),
            'extension' => pathinfo($fullPath, PATHINFO_EXTENSION),
            'modified_at' => date('c', filemtime($fullPath)),
        ];
    }

    /**
     * Save file content.
     */
    public function saveFileContent(Domain $domain, string $relativePath, string $content): array
    {
        $fullPath = $this->resolvePath($domain, $relativePath);

        if (File::isDirectory($fullPath)) {
            throw new RuntimeException('Cannot write to directory.');
        }

        if (!$this->isEditable($fullPath)) {
            throw new RuntimeException('File type is not editable.');
        }

        if ($this->isProtected($domain, $relativePath)) {
            throw new RuntimeException('This file is protected and cannot be modified.');
        }

        // Create backup before saving
        if (File::exists($fullPath)) {
            $backupPath = $fullPath . '.bak';
            File::copy($fullPath, $backupPath);
        }

        File::put($fullPath, $content);

        return [
            'name' => basename($fullPath),
            'path' => $relativePath,
            'size' => File::size($fullPath),
            'modified_at' => date('c', filemtime($fullPath)),
        ];
    }

    /**
     * Create a new file.
     */
    public function createFile(Domain $domain, string $relativePath, string $content = ''): array
    {
        $fullPath = $this->resolvePath($domain, $relativePath);

        if (File::exists($fullPath)) {
            throw new RuntimeException('File already exists.');
        }

        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        if (!$this->isExtensionAllowed($extension)) {
            throw new RuntimeException('File extension is not allowed.');
        }

        // Ensure parent directory exists
        $directory = dirname($fullPath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($fullPath, $content);

        return [
            'name' => basename($fullPath),
            'path' => $relativePath,
            'size' => File::size($fullPath),
            'modified_at' => date('c', filemtime($fullPath)),
        ];
    }

    /**
     * Create a new directory.
     */
    public function createDirectory(Domain $domain, string $relativePath): array
    {
        $fullPath = $this->resolvePath($domain, $relativePath);

        if (File::exists($fullPath)) {
            throw new RuntimeException('Directory already exists.');
        }

        File::makeDirectory($fullPath, 0755, true);

        return [
            'name' => basename($fullPath),
            'path' => $relativePath,
            'type' => 'directory',
            'modified_at' => date('c', filemtime($fullPath)),
        ];
    }

    /**
     * Upload files.
     */
    public function uploadFiles(Domain $domain, string $relativePath, array $files): array
    {
        $directory = $this->resolvePath($domain, $relativePath);

        if (!File::isDirectory($directory)) {
            throw new RuntimeException('Upload directory not found.');
        }

        $uploaded = [];
        $errors = [];

        foreach ($files as $file) {
            /** @var UploadedFile $file */
            try {
                $this->validateUploadedFile($file);

                $filename = $this->getUniqueFilename($directory, $file->getClientOriginalName());
                $file->move($directory, $filename);

                $fullPath = $directory . '/' . $filename;
                $uploaded[] = [
                    'name' => $filename,
                    'path' => $relativePath ? $relativePath . '/' . $filename : $filename,
                    'size' => filesize($fullPath),
                    'modified_at' => date('c', filemtime($fullPath)),
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'uploaded' => $uploaded,
            'errors' => $errors,
        ];
    }

    /**
     * Download a file.
     */
    public function downloadFile(Domain $domain, string $relativePath): BinaryFileResponse
    {
        $fullPath = $this->resolvePath($domain, $relativePath);

        if (!File::exists($fullPath)) {
            throw new RuntimeException('File not found.');
        }

        if (File::isDirectory($fullPath)) {
            throw new RuntimeException('Cannot download directory directly. Use compress first.');
        }

        return response()->download($fullPath);
    }

    /**
     * Rename a file or directory.
     */
    public function rename(Domain $domain, string $relativePath, string $newName): array
    {
        $fullPath = $this->resolvePath($domain, $relativePath);

        if (!File::exists($fullPath)) {
            throw new RuntimeException('File or directory not found.');
        }

        if ($this->isProtected($domain, $relativePath)) {
            throw new RuntimeException('This item is protected and cannot be renamed.');
        }

        // Validate new name
        if (preg_match('/[\/\\\]/', $newName)) {
            throw new RuntimeException('Invalid filename.');
        }

        $directory = dirname($fullPath);
        $newPath = $directory . '/' . $newName;

        if (File::exists($newPath)) {
            throw new RuntimeException('A file or directory with this name already exists.');
        }

        // Check extension for files
        if (File::isFile($fullPath)) {
            $extension = pathinfo($newName, PATHINFO_EXTENSION);
            if (!$this->isExtensionAllowed($extension)) {
                throw new RuntimeException('File extension is not allowed.');
            }
        }

        File::move($fullPath, $newPath);

        $newRelativePath = dirname($relativePath) === '.'
            ? $newName
            : dirname($relativePath) . '/' . $newName;

        return [
            'name' => $newName,
            'path' => $newRelativePath,
            'type' => File::isDirectory($newPath) ? 'directory' : 'file',
        ];
    }

    /**
     * Copy a file or directory.
     */
    public function copy(Domain $domain, string $sourcePath, string $destinationPath): array
    {
        $sourceFullPath = $this->resolvePath($domain, $sourcePath);
        $destFullPath = $this->resolvePath($domain, $destinationPath);

        if (!File::exists($sourceFullPath)) {
            throw new RuntimeException('Source not found.');
        }

        if (File::exists($destFullPath)) {
            throw new RuntimeException('Destination already exists.');
        }

        if (File::isDirectory($sourceFullPath)) {
            File::copyDirectory($sourceFullPath, $destFullPath);
        } else {
            // Check extension
            $extension = pathinfo($destFullPath, PATHINFO_EXTENSION);
            if (!$this->isExtensionAllowed($extension)) {
                throw new RuntimeException('File extension is not allowed.');
            }

            // Ensure destination directory exists
            $destDir = dirname($destFullPath);
            if (!File::isDirectory($destDir)) {
                File::makeDirectory($destDir, 0755, true);
            }

            File::copy($sourceFullPath, $destFullPath);
        }

        return [
            'name' => basename($destFullPath),
            'path' => $destinationPath,
            'type' => File::isDirectory($destFullPath) ? 'directory' : 'file',
        ];
    }

    /**
     * Move a file or directory.
     */
    public function move(Domain $domain, string $sourcePath, string $destinationPath): array
    {
        $sourceFullPath = $this->resolvePath($domain, $sourcePath);
        $destFullPath = $this->resolvePath($domain, $destinationPath);

        if (!File::exists($sourceFullPath)) {
            throw new RuntimeException('Source not found.');
        }

        if ($this->isProtected($domain, $sourcePath)) {
            throw new RuntimeException('This item is protected and cannot be moved.');
        }

        if (File::exists($destFullPath)) {
            throw new RuntimeException('Destination already exists.');
        }

        // Ensure destination directory exists
        $destDir = dirname($destFullPath);
        if (!File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        File::move($sourceFullPath, $destFullPath);

        return [
            'name' => basename($destFullPath),
            'path' => $destinationPath,
            'type' => File::isDirectory($destFullPath) ? 'directory' : 'file',
        ];
    }

    /**
     * Delete a file or directory.
     */
    public function delete(Domain $domain, string $relativePath): void
    {
        $fullPath = $this->resolvePath($domain, $relativePath);

        if (!File::exists($fullPath)) {
            throw new RuntimeException('File or directory not found.');
        }

        if ($this->isProtected($domain, $relativePath)) {
            throw new RuntimeException('This item is protected and cannot be deleted.');
        }

        if (File::isDirectory($fullPath)) {
            File::deleteDirectory($fullPath);
        } else {
            File::delete($fullPath);
        }
    }

    /**
     * Delete multiple items.
     */
    public function deleteMultiple(Domain $domain, array $paths): array
    {
        $deleted = [];
        $errors = [];

        foreach ($paths as $path) {
            try {
                $this->delete($domain, $path);
                $deleted[] = $path;
            } catch (\Exception $e) {
                $errors[] = [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'deleted' => $deleted,
            'errors' => $errors,
        ];
    }

    /**
     * Compress files/directories into a zip archive.
     */
    public function compress(Domain $domain, array $paths, string $archiveName): array
    {
        $basePath = $this->getDomainPath($domain);

        // Validate archive name
        if (!str_ends_with(strtolower($archiveName), '.zip')) {
            $archiveName .= '.zip';
        }

        // Determine archive path (in the directory of the first item)
        $firstItemPath = $this->resolvePath($domain, $paths[0]);
        $archiveDir = File::isDirectory($firstItemPath)
            ? dirname($firstItemPath)
            : dirname($firstItemPath);
        $archivePath = $archiveDir . '/' . $archiveName;

        if (File::exists($archivePath)) {
            throw new RuntimeException('Archive file already exists.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Failed to create archive.');
        }

        foreach ($paths as $path) {
            $fullPath = $this->resolvePath($domain, $path);

            if (!File::exists($fullPath)) {
                continue;
            }

            if (File::isDirectory($fullPath)) {
                $this->addDirectoryToZip($zip, $fullPath, basename($fullPath));
            } else {
                $zip->addFile($fullPath, basename($fullPath));
            }
        }

        $zip->close();

        $relativePath = str_replace($basePath . '/', '', $archivePath);

        return [
            'name' => $archiveName,
            'path' => $relativePath,
            'size' => File::size($archivePath),
        ];
    }

    /**
     * Extract an archive (zip, tar, tar.gz, tar.bz2, gz).
     */
    public function extract(Domain $domain, string $archivePath, ?string $destinationPath = null): array
    {
        $fullArchivePath = $this->resolvePath($domain, $archivePath);

        if (!File::exists($fullArchivePath)) {
            throw new RuntimeException('Archive not found.');
        }

        // Determine archive type
        $archiveType = $this->getArchiveType($fullArchivePath);
        if (!$archiveType) {
            throw new RuntimeException('Unsupported archive format. Supported: zip, tar, tar.gz, tgz, tar.bz2, tbz2, gz');
        }

        // Determine destination
        if ($destinationPath) {
            $destFullPath = $this->resolvePath($domain, $destinationPath);
        } else {
            // Extract to same directory as archive
            $destFullPath = dirname($fullArchivePath);
        }

        if (!File::isDirectory($destFullPath)) {
            File::makeDirectory($destFullPath, 0755, true);
        }

        $count = 0;

        switch ($archiveType) {
            case 'zip':
                $count = $this->extractZip($fullArchivePath, $destFullPath);
                break;

            case 'tar':
            case 'tar.gz':
            case 'tar.bz2':
                $count = $this->extractTar($fullArchivePath, $destFullPath, $archiveType);
                break;

            case 'gz':
                $count = $this->extractGz($fullArchivePath, $destFullPath);
                break;
        }

        $basePath = $this->getDomainPath($domain);
        $relativePath = str_replace($basePath . '/', '', $destFullPath);

        return [
            'destination' => $relativePath,
            'files_extracted' => $count,
        ];
    }

    /**
     * Determine archive type from file path.
     */
    protected function getArchiveType(string $filePath): ?string
    {
        $filename = strtolower(basename($filePath));

        // Check for compound extensions first
        if (str_ends_with($filename, '.tar.gz') || str_ends_with($filename, '.tgz')) {
            return 'tar.gz';
        }
        if (str_ends_with($filename, '.tar.bz2') || str_ends_with($filename, '.tbz2') || str_ends_with($filename, '.tbz')) {
            return 'tar.bz2';
        }

        // Simple extensions
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        $supportedTypes = [
            'zip' => 'zip',
            'tar' => 'tar',
            'gz' => 'gz',
        ];

        return $supportedTypes[$extension] ?? null;
    }

    /**
     * Extract ZIP archive.
     */
    protected function extractZip(string $archivePath, string $destPath): int
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Failed to open ZIP archive.');
        }

        $zip->extractTo($destPath);
        $count = $zip->numFiles;
        $zip->close();

        return $count;
    }

    /**
     * Extract TAR archive (including tar.gz and tar.bz2).
     */
    protected function extractTar(string $archivePath, string $destPath, string $type): int
    {
        try {
            // For compressed tar files, we need to decompress first
            if ($type === 'tar.gz') {
                $phar = new \PharData($archivePath);
                // Decompress to a temporary tar file
                $tarPath = sys_get_temp_dir() . '/' . uniqid('vsispanel_') . '.tar';
                $phar->decompress();
                // The decompressed file will be in the same directory with .tar extension
                $decompressedPath = preg_replace('/\.(gz|tgz)$/i', '', $archivePath);
                if (str_ends_with(strtolower($archivePath), '.tgz')) {
                    $decompressedPath = preg_replace('/\.tgz$/i', '.tar', $archivePath);
                }

                if (File::exists($decompressedPath)) {
                    $phar = new \PharData($decompressedPath);
                    $phar->extractTo($destPath, null, true);
                    $count = iterator_count(new \RecursiveIteratorIterator($phar));
                    // Clean up decompressed tar
                    @unlink($decompressedPath);
                    return $count;
                }
            } elseif ($type === 'tar.bz2') {
                $phar = new \PharData($archivePath);
                $phar->decompress();
                $decompressedPath = preg_replace('/\.(bz2|tbz2|tbz)$/i', '', $archivePath);
                if (str_ends_with(strtolower($archivePath), '.tbz2') || str_ends_with(strtolower($archivePath), '.tbz')) {
                    $decompressedPath = preg_replace('/\.(tbz2|tbz)$/i', '.tar', $archivePath);
                }

                if (File::exists($decompressedPath)) {
                    $phar = new \PharData($decompressedPath);
                    $phar->extractTo($destPath, null, true);
                    $count = iterator_count(new \RecursiveIteratorIterator($phar));
                    @unlink($decompressedPath);
                    return $count;
                }
            }

            // Plain tar file
            $phar = new \PharData($archivePath);
            $phar->extractTo($destPath, null, true);
            return iterator_count(new \RecursiveIteratorIterator($phar));
        } catch (\Exception $e) {
            // Fallback to shell command if PharData fails
            return $this->extractTarWithShell($archivePath, $destPath, $type);
        }
    }

    /**
     * Extract TAR using shell command as fallback.
     */
    protected function extractTarWithShell(string $archivePath, string $destPath, string $type): int
    {
        $flags = '-xf';
        if ($type === 'tar.gz') {
            $flags = '-xzf';
        } elseif ($type === 'tar.bz2') {
            $flags = '-xjf';
        }

        $command = sprintf(
            'tar %s %s -C %s 2>&1',
            $flags,
            escapeshellarg($archivePath),
            escapeshellarg($destPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException('Failed to extract TAR archive: ' . implode("\n", $output));
        }

        // Count extracted files
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($destPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            $count++;
        }

        return $count;
    }

    /**
     * Extract GZ file (single file compression).
     */
    protected function extractGz(string $archivePath, string $destPath): int
    {
        $filename = basename($archivePath);
        $outputFilename = preg_replace('/\.gz$/i', '', $filename);

        // If the inner file is still a tar, handle it
        if (str_ends_with(strtolower($outputFilename), '.tar')) {
            return $this->extractTar($archivePath, $destPath, 'tar.gz');
        }

        $outputPath = $destPath . '/' . $outputFilename;

        $gz = gzopen($archivePath, 'rb');
        if (!$gz) {
            throw new RuntimeException('Failed to open GZ archive.');
        }

        $out = fopen($outputPath, 'wb');
        if (!$out) {
            gzclose($gz);
            throw new RuntimeException('Failed to create output file.');
        }

        while (!gzeof($gz)) {
            fwrite($out, gzread($gz, 4096));
        }

        gzclose($gz);
        fclose($out);

        return 1;
    }

    /**
     * Get file/directory permissions.
     */
    public function getPermissions(Domain $domain, string $relativePath): array
    {
        $fullPath = $this->resolvePath($domain, $relativePath);

        if (!File::exists($fullPath)) {
            throw new RuntimeException('File or directory not found.');
        }

        $perms = fileperms($fullPath);

        return [
            'path' => $relativePath,
            'numeric' => substr(sprintf('%o', $perms), -4),
            'readable' => $this->formatPermissions($perms),
            'owner' => function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($fullPath))['name'] ?? fileowner($fullPath) : fileowner($fullPath),
            'group' => function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($fullPath))['name'] ?? filegroup($fullPath) : filegroup($fullPath),
        ];
    }

    /**
     * Set file/directory permissions.
     */
    public function setPermissions(Domain $domain, string $relativePath, string $permissions): array
    {
        $fullPath = $this->resolvePath($domain, $relativePath);

        if (!File::exists($fullPath)) {
            throw new RuntimeException('File or directory not found.');
        }

        if ($this->isProtected($domain, $relativePath)) {
            throw new RuntimeException('This item is protected and permissions cannot be changed.');
        }

        // Validate permissions format (e.g., "0755", "755")
        if (!preg_match('/^0?[0-7]{3,4}$/', $permissions)) {
            throw new RuntimeException('Invalid permissions format.');
        }

        $mode = octdec($permissions);

        if (!chmod($fullPath, $mode)) {
            throw new RuntimeException('Failed to change permissions.');
        }

        return $this->getPermissions($domain, $relativePath);
    }

    /**
     * Search for files and directories.
     */
    public function search(Domain $domain, string $query, string $basePath = ''): array
    {
        $searchPath = $this->resolvePath($domain, $basePath);
        $domainPath = $this->getDomainPath($domain);

        if (!File::isDirectory($searchPath)) {
            throw new RuntimeException('Search path not found.');
        }

        $results = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($searchPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $query = strtolower($query);
        $count = 0;
        $maxResults = 100;

        foreach ($iterator as $file) {
            if ($count >= $maxResults) {
                break;
            }

            $name = $file->getFilename();
            if ($this->isHidden($name)) {
                continue;
            }

            if (str_contains(strtolower($name), $query)) {
                $relativePath = str_replace($domainPath . '/', '', $file->getPathname());

                $results[] = [
                    'name' => $name,
                    'path' => $relativePath,
                    'type' => $file->isDir() ? 'directory' : 'file',
                    'size' => $file->isDir() ? null : $file->getSize(),
                    'modified_at' => date('c', $file->getMTime()),
                ];

                $count++;
            }
        }

        return [
            'query' => $query,
            'results' => $results,
            'count' => count($results),
            'truncated' => $count >= $maxResults,
        ];
    }

    /**
     * Get disk usage information for a domain.
     */
    public function getDiskUsage(Domain $domain): array
    {
        $path = $this->getDomainPath($domain);

        if (!File::isDirectory($path)) {
            return [
                'used' => 0,
                'formatted' => '0 B',
            ];
        }

        $size = $this->getDirectorySize($path);

        return [
            'used' => $size,
            'formatted' => $this->formatBytes($size),
        ];
    }

    /**
     * Download a file from a remote URL.
     */
    public function remoteDownload(Domain $domain, string $url, string $relativePath = '', ?string $filename = null): array
    {
        $directory = $this->resolvePath($domain, $relativePath);

        if (!File::isDirectory($directory)) {
            throw new RuntimeException('Destination directory not found.');
        }

        // Determine filename from URL if not provided
        if (!$filename) {
            $parsedUrl = parse_url($url);
            $pathInfo = pathinfo($parsedUrl['path'] ?? '');
            $filename = $pathInfo['basename'] ?? 'downloaded_file';

            // Clean up query parameters from filename
            if (str_contains($filename, '?')) {
                $filename = explode('?', $filename)[0];
            }

            // If still no valid filename, generate one
            if (!$filename || $filename === '') {
                $filename = 'downloaded_' . time();
            }
        }

        // Validate filename extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if (!$this->isExtensionAllowed($extension)) {
            throw new RuntimeException("File extension '{$extension}' is not allowed.");
        }

        // Get unique filename if file already exists
        $filename = $this->getUniqueFilename($directory, $filename);
        $fullPath = $directory . '/' . $filename;

        // Download the file using curl for better control
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 300, // 5 minutes timeout
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'VSISPanel/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
            ],
        ]);

        // First, make a HEAD request to check content-length
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 400) {
            curl_close($ch);
            throw new RuntimeException("Failed to download: HTTP error {$httpCode}");
        }

        // Check if file is too large (use max upload size as limit)
        if ($contentLength > 0 && $contentLength > $this->maxUploadSize) {
            curl_close($ch);
            throw new RuntimeException('File is too large. Maximum size: ' . $this->formatBytes($this->maxUploadSize));
        }

        // Now download the actual file
        curl_setopt($ch, CURLOPT_NOBODY, false);
        $content = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($content === false || $error) {
            throw new RuntimeException('Failed to download file: ' . ($error ?: 'Unknown error'));
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("Failed to download: HTTP error {$httpCode}");
        }

        if (empty($content)) {
            throw new RuntimeException('Downloaded file is empty.');
        }

        // Check actual downloaded size
        $downloadedSize = strlen($content);
        if ($downloadedSize > $this->maxUploadSize) {
            throw new RuntimeException('Downloaded file is too large. Maximum size: ' . $this->formatBytes($this->maxUploadSize));
        }

        // Save the file
        if (File::put($fullPath, $content) === false) {
            throw new RuntimeException('Failed to save downloaded file.');
        }

        $basePath = $this->getDomainPath($domain);
        $savedRelativePath = str_replace($basePath . '/', '', $fullPath);

        return [
            'name' => $filename,
            'path' => $savedRelativePath,
            'size' => $downloadedSize,
            'formatted_size' => $this->formatBytes($downloadedSize),
            'modified_at' => date('c', filemtime($fullPath)),
        ];
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    protected function isHidden(string $name): bool
    {
        foreach ($this->hiddenPatterns as $pattern) {
            if ($name === $pattern || fnmatch($pattern, $name)) {
                return true;
            }
        }
        return false;
    }

    protected function isProtected(Domain $domain, string $relativePath): bool
    {
        foreach ($this->protectedPaths as $protected) {
            if (str_starts_with($relativePath, $protected)) {
                return true;
            }
        }
        return false;
    }

    protected function isEditable(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Files without extension (like .htaccess) - check the filename
        if ($extension === '') {
            $filename = basename($path);
            return in_array($filename, ['htaccess', '.htaccess']);
        }

        return in_array($extension, $this->editableExtensions);
    }

    protected function isExtensionAllowed(string $extension): bool
    {
        $extension = strtolower($extension);

        // Check blocked extensions first
        if (in_array($extension, $this->blockedExtensions)) {
            return false;
        }

        // If allowed extensions is empty, allow all (except blocked)
        if (empty($this->allowedExtensions)) {
            return true;
        }

        return in_array($extension, $this->allowedExtensions);
    }

    protected function validateUploadedFile(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (!$this->isExtensionAllowed($extension)) {
            throw new RuntimeException("File extension '{$extension}' is not allowed.");
        }

        if ($file->getSize() > $this->maxUploadSize) {
            throw new RuntimeException('File exceeds maximum upload size of ' . $this->formatBytes($this->maxUploadSize));
        }
    }

    protected function getUniqueFilename(string $directory, string $filename): string
    {
        if (!File::exists($directory . '/' . $filename)) {
            return $filename;
        }

        $name = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $counter = 1;

        do {
            $newFilename = $name . '_' . $counter . ($extension ? '.' . $extension : '');
            $counter++;
        } while (File::exists($directory . '/' . $newFilename));

        return $newFilename;
    }

    protected function getDirectorySize(string $path): int
    {
        $size = 0;

        if (!File::isDirectory($path)) {
            return $size;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    protected function formatPermissions(int $perms): string
    {
        $info = '';

        // File type
        if (($perms & 0xC000) === 0xC000) $info = 's'; // Socket
        elseif (($perms & 0xA000) === 0xA000) $info = 'l'; // Symbolic Link
        elseif (($perms & 0x8000) === 0x8000) $info = '-'; // Regular
        elseif (($perms & 0x6000) === 0x6000) $info = 'b'; // Block special
        elseif (($perms & 0x4000) === 0x4000) $info = 'd'; // Directory
        elseif (($perms & 0x2000) === 0x2000) $info = 'c'; // Character special
        elseif (($perms & 0x1000) === 0x1000) $info = 'p'; // FIFO pipe
        else $info = 'u'; // Unknown

        // Owner
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));

        // Group
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));

        // World
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));

        return $info;
    }

    /**
     * Get file preview data including syntax hint.
     */
    public function getFilePreview(Domain $domain, string $relativePath): array
    {
        $fullPath = $this->resolvePath($domain, $relativePath);

        if (! File::exists($fullPath) || ! File::isFile($fullPath)) {
            throw new RuntimeException('File not found.');
        }

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mimeType = File::mimeType($fullPath) ?: 'application/octet-stream';
        $size = File::size($fullPath);
        $info = [
            'name' => basename($fullPath),
            'path' => $relativePath,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $size,
            'syntax' => $this->detectSyntax($extension),
            'is_editable' => in_array($extension, $this->editableExtensions),
            'is_image' => str_starts_with($mimeType, 'image/'),
            'is_video' => str_starts_with($mimeType, 'video/'),
            'is_pdf' => $mimeType === 'application/pdf',
        ];

        // Add image dimensions
        if ($info['is_image'] && function_exists('getimagesize')) {
            $dims = @getimagesize($fullPath);
            if ($dims) {
                $info['width'] = $dims[0];
                $info['height'] = $dims[1];
            }
        }

        return $info;
    }

    /**
     * Detect syntax mode from file extension.
     */
    public function detectSyntax(string $extension): string
    {
        return match ($extension) {
            'php', 'phtml' => 'php',
            'html', 'htm' => 'html',
            'css', 'scss', 'less' => 'css',
            'js', 'jsx', 'ts', 'tsx', 'mjs' => 'javascript',
            'json' => 'json',
            'xml', 'svg' => 'xml',
            'md', 'markdown' => 'markdown',
            'sql' => 'sql',
            'yaml', 'yml' => 'yaml',
            'sh', 'bash', 'zsh' => 'shell',
            'py' => 'python',
            'env', 'ini', 'conf', 'cfg', 'htaccess', 'nginx' => 'text',
            default => 'text',
        };
    }

    /**
     * Calculate folder size.
     */
    public function calculateSize(Domain $domain, string $relativePath): int
    {
        $fullPath = $this->resolvePath($domain, $relativePath);

        if (! File::exists($fullPath)) {
            throw new RuntimeException('Path not found.');
        }

        $result = $this->executor->execute("du -sb " . escapeshellarg($fullPath));

        if ($result->successful()) {
            $parts = explode("\t", trim($result->output()));
            return (int) ($parts[0] ?? 0);
        }

        return 0;
    }

    /**
     * Compare two files.
     */
    public function compareFiles(Domain $domain, string $path1, string $path2): array
    {
        $fullPath1 = $this->resolvePath($domain, $path1);
        $fullPath2 = $this->resolvePath($domain, $path2);

        if (! File::isFile($fullPath1) || ! File::isFile($fullPath2)) {
            throw new RuntimeException('One or both files not found.');
        }

        return [
            'file1' => [
                'path' => $path1,
                'content' => File::get($fullPath1),
                'size' => File::size($fullPath1),
            ],
            'file2' => [
                'path' => $path2,
                'content' => File::get($fullPath2),
                'size' => File::size($fullPath2),
            ],
        ];
    }

    protected function addDirectoryToZip(ZipArchive $zip, string $path, string $relativePath): void
    {
        $zip->addEmptyDir($relativePath);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getPathname();
            $localPath = $relativePath . '/' . substr($filePath, strlen($path) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($localPath);
            } else {
                $zip->addFile($filePath, $localPath);
            }
        }
    }
}
