<?php

declare(strict_types=1);

namespace App\Modules\FileManager\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Domain\Models\Domain;
use App\Modules\FileManager\Http\Requests\CompressFilesRequest;
use App\Modules\FileManager\Http\Requests\CopyMoveRequest;
use App\Modules\FileManager\Http\Requests\CreateFileRequest;
use App\Modules\FileManager\Http\Requests\DeleteFilesRequest;
use App\Modules\FileManager\Http\Requests\ExtractArchiveRequest;
use App\Modules\FileManager\Http\Requests\RenameRequest;
use App\Modules\FileManager\Http\Requests\SaveFileRequest;
use App\Modules\FileManager\Http\Requests\SetPermissionsRequest;
use App\Modules\FileManager\Http\Requests\UploadFilesRequest;
use App\Modules\FileManager\Services\FileManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileManagerController extends Controller
{
    public function __construct(
        protected FileManagerService $fileManager
    ) {}

    /**
     * List directory contents.
     */
    public function index(Request $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $path = $request->get('path') ?? '';

        try {
            $data = $this->fileManager->listDirectory($domain, (string) $path);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LIST_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get file content for editing.
     */
    public function show(Request $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $path = $request->get('path', '');

        if (!$path) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PATH_REQUIRED',
                    'message' => 'File path is required.',
                ],
            ], 400);
        }

        try {
            $data = $this->fileManager->getFileContent($domain, $path);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'READ_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Save file content.
     */
    public function save(SaveFileRequest $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $validated = $request->validated();

        try {
            $data = $this->fileManager->saveFileContent(
                $domain,
                $validated['path'],
                $validated['content']
            );

            return response()->json([
                'success' => true,
                'message' => 'File saved successfully.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SAVE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Create a new file.
     */
    public function createFile(CreateFileRequest $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $validated = $request->validated();

        try {
            $data = $this->fileManager->createFile(
                $domain,
                $validated['path'],
                $validated['content'] ?? ''
            );

            return response()->json([
                'success' => true,
                'message' => 'File created successfully.',
                'data' => $data,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CREATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Create a new directory.
     */
    public function createDirectory(CreateFileRequest $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $validated = $request->validated();

        try {
            $data = $this->fileManager->createDirectory($domain, $validated['path']);

            return response()->json([
                'success' => true,
                'message' => 'Directory created successfully.',
                'data' => $data,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CREATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Upload files.
     */
    public function upload(UploadFilesRequest $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $validated = $request->validated();
        $path = $validated['path'] ?? '';
        $files = $request->file('files');

        if (empty($files)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_FILES',
                    'message' => 'No files were uploaded.',
                ],
            ], 400);
        }

        try {
            $result = $this->fileManager->uploadFiles($domain, $path, $files);

            $hasErrors = !empty($result['errors']);

            return response()->json([
                'success' => !$hasErrors || !empty($result['uploaded']),
                'message' => $hasErrors
                    ? 'Some files failed to upload.'
                    : 'Files uploaded successfully.',
                'data' => $result,
            ], $hasErrors && empty($result['uploaded']) ? 400 : 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPLOAD_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Download a file.
     */
    public function download(Request $request, Domain $domain): BinaryFileResponse|JsonResponse
    {
        $this->authorizeDomain($domain);

        $path = $request->get('path', '');

        if (!$path) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PATH_REQUIRED',
                    'message' => 'File path is required.',
                ],
            ], 400);
        }

        try {
            return $this->fileManager->downloadFile($domain, $path);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOWNLOAD_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Rename a file or directory.
     */
    public function rename(RenameRequest $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $validated = $request->validated();

        try {
            $data = $this->fileManager->rename(
                $domain,
                $validated['path'],
                $validated['new_name']
            );

            return response()->json([
                'success' => true,
                'message' => 'Renamed successfully.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RENAME_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Copy a file or directory.
     */
    public function copy(CopyMoveRequest $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $validated = $request->validated();

        try {
            $data = $this->fileManager->copy(
                $domain,
                $validated['source'],
                $validated['destination']
            );

            return response()->json([
                'success' => true,
                'message' => 'Copied successfully.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'COPY_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Move a file or directory.
     */
    public function move(CopyMoveRequest $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $validated = $request->validated();

        try {
            $data = $this->fileManager->move(
                $domain,
                $validated['source'],
                $validated['destination']
            );

            return response()->json([
                'success' => true,
                'message' => 'Moved successfully.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MOVE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Delete a file or directory.
     */
    public function delete(DeleteFilesRequest $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $validated = $request->validated();
        $paths = $validated['paths'];

        try {
            if (count($paths) === 1) {
                $this->fileManager->delete($domain, $paths[0]);

                return response()->json([
                    'success' => true,
                    'message' => 'Deleted successfully.',
                ]);
            }

            $result = $this->fileManager->deleteMultiple($domain, $paths);

            $hasErrors = !empty($result['errors']);

            return response()->json([
                'success' => !$hasErrors,
                'message' => $hasErrors
                    ? 'Some items failed to delete.'
                    : 'Items deleted successfully.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Compress files into a zip archive.
     */
    public function compress(CompressFilesRequest $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $validated = $request->validated();

        try {
            $data = $this->fileManager->compress(
                $domain,
                $validated['paths'],
                $validated['archive_name']
            );

            return response()->json([
                'success' => true,
                'message' => 'Archive created successfully.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'COMPRESS_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Extract a zip archive.
     */
    public function extract(ExtractArchiveRequest $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $validated = $request->validated();

        try {
            $data = $this->fileManager->extract(
                $domain,
                $validated['path'],
                $validated['destination'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Archive extracted successfully.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EXTRACT_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get file/directory permissions.
     */
    public function permissions(Request $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $path = $request->get('path', '');

        if (!$path) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PATH_REQUIRED',
                    'message' => 'Path is required.',
                ],
            ], 400);
        }

        try {
            $data = $this->fileManager->getPermissions($domain, $path);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PERMISSIONS_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Set file/directory permissions.
     */
    public function setPermissions(SetPermissionsRequest $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $validated = $request->validated();

        try {
            $data = $this->fileManager->setPermissions(
                $domain,
                $validated['path'],
                $validated['permissions']
            );

            return response()->json([
                'success' => true,
                'message' => 'Permissions updated successfully.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PERMISSIONS_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Search for files and directories.
     */
    public function search(Request $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $query = $request->get('query', '');
        $basePath = $request->get('path', '');

        if (!$query || strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUERY_TOO_SHORT',
                    'message' => 'Search query must be at least 2 characters.',
                ],
            ], 400);
        }

        try {
            $data = $this->fileManager->search($domain, $query, $basePath);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SEARCH_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get disk usage information.
     */
    public function diskUsage(Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        try {
            $data = $this->fileManager->getDiskUsage($domain);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DISK_USAGE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Download a file from a remote URL.
     */
    public function remoteDownload(Request $request, Domain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        $url = $request->get('url', '');
        $path = $request->get('path') ?? '';
        $filename = $request->get('filename');

        if (!$url) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'URL_REQUIRED',
                    'message' => 'URL is required.',
                ],
            ], 400);
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_URL',
                    'message' => 'Invalid URL format.',
                ],
            ], 400);
        }

        // Only allow http and https schemes
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array(strtolower($scheme), ['http', 'https'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_SCHEME',
                    'message' => 'Only HTTP and HTTPS URLs are allowed.',
                ],
            ], 400);
        }

        try {
            $data = $this->fileManager->remoteDownload($domain, $url, $path, $filename);

            return response()->json([
                'success' => true,
                'message' => 'File downloaded successfully.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REMOTE_DOWNLOAD_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get file preview info.
     */
    public function preview(Request $request, Domain $domain)
    {
        $this->authorizeDomain($domain);
        $path = $request->query('path', '');

        try {
            $data = $this->fileManager->getFilePreview($domain, $path);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PREVIEW_FAILED', 'message' => $e->getMessage()],
            ], 400);
        }
    }

    /**
     * Calculate folder size.
     */
    public function calculateSize(Request $request, Domain $domain)
    {
        $this->authorizeDomain($domain);
        $path = $request->input('path', '');

        try {
            $size = $this->fileManager->calculateSize($domain, $path);

            return response()->json([
                'success' => true,
                'data' => ['size' => $size],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'SIZE_FAILED', 'message' => $e->getMessage()],
            ], 400);
        }
    }

    /**
     * Compare two files.
     */
    public function compare(Request $request, Domain $domain)
    {
        $this->authorizeDomain($domain);
        $request->validate([
            'path1' => 'required|string',
            'path2' => 'required|string',
        ]);

        try {
            $data = $this->fileManager->compareFiles($domain, $request->path1, $request->path2);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'COMPARE_FAILED', 'message' => $e->getMessage()],
            ], 400);
        }
    }

    /**
     * Authorize access to domain's files.
     */
    protected function authorizeDomain(Domain $domain): void
    {
        $this->authorize('update', $domain);
    }
}
