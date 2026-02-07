<?php

declare(strict_types=1);

use App\Modules\FileManager\Http\Controllers\FileManagerController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', 'auth:sanctum'])->group(function () {
    // File Manager routes - all operations are scoped to a domain
    Route::prefix('domains/{domain}/files')->group(function () {
        // List directory contents
        Route::get('/', [FileManagerController::class, 'index'])->name('files.index');

        // Get file content for editing
        Route::get('/content', [FileManagerController::class, 'show'])->name('files.show');

        // Save file content
        Route::put('/content', [FileManagerController::class, 'save'])->name('files.save');

        // Create file
        Route::post('/file', [FileManagerController::class, 'createFile'])->name('files.create-file');

        // Create directory
        Route::post('/directory', [FileManagerController::class, 'createDirectory'])->name('files.create-directory');

        // Upload files
        Route::post('/upload', [FileManagerController::class, 'upload'])->name('files.upload');

        // Download file
        Route::get('/download', [FileManagerController::class, 'download'])->name('files.download');

        // Rename file/directory
        Route::post('/rename', [FileManagerController::class, 'rename'])->name('files.rename');

        // Copy file/directory
        Route::post('/copy', [FileManagerController::class, 'copy'])->name('files.copy');

        // Move file/directory
        Route::post('/move', [FileManagerController::class, 'move'])->name('files.move');

        // Delete files/directories
        Route::delete('/', [FileManagerController::class, 'delete'])->name('files.delete');

        // Compress files
        Route::post('/compress', [FileManagerController::class, 'compress'])->name('files.compress');

        // Extract archive
        Route::post('/extract', [FileManagerController::class, 'extract'])->name('files.extract');

        // Get permissions
        Route::get('/permissions', [FileManagerController::class, 'permissions'])->name('files.permissions');

        // Set permissions
        Route::post('/permissions', [FileManagerController::class, 'setPermissions'])->name('files.set-permissions');

        // Search
        Route::get('/search', [FileManagerController::class, 'search'])->name('files.search');

        // Disk usage
        Route::get('/disk-usage', [FileManagerController::class, 'diskUsage'])->name('files.disk-usage');

        // Remote download
        Route::post('/remote-download', [FileManagerController::class, 'remoteDownload'])->name('files.remote-download');

        // File preview info
        Route::get('/preview', [FileManagerController::class, 'preview'])->name('files.preview');

        // Calculate folder size
        Route::post('/calculate-size', [FileManagerController::class, 'calculateSize'])->name('files.calculate-size');

        // Compare files
        Route::post('/compare', [FileManagerController::class, 'compare'])->name('files.compare');
    });
});
