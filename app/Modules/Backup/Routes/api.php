<?php

declare(strict_types=1);

use App\Modules\Backup\Http\Controllers\BackupConfigController;
use App\Modules\Backup\Http\Controllers\BackupController;
use App\Modules\Backup\Http\Controllers\OAuthController;
use App\Modules\Backup\Http\Controllers\StorageRemoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', 'auth:sanctum'])->group(function () {
    // Backup statistics
    Route::get('backups/stats', [BackupController::class, 'stats']);
    Route::get('backups/recent', [BackupController::class, 'recent']);

    // Restore operations
    Route::get('restore-operations/recent', [BackupController::class, 'recentRestores']);
    Route::get('restore-operations/{restoreOperationId}', [BackupController::class, 'restoreStatus']);

    // Backup configurations
    Route::prefix('backup-configs')->group(function () {
        Route::get('/', [BackupConfigController::class, 'index']);
        Route::post('/', [BackupConfigController::class, 'store']);
        Route::get('/{backupConfig}', [BackupConfigController::class, 'show']);
        Route::put('/{backupConfig}', [BackupConfigController::class, 'update']);
        Route::delete('/{backupConfig}', [BackupConfigController::class, 'destroy']);

        // Config actions
        Route::post('/{backupConfig}/test-connection', [BackupConfigController::class, 'testConnection']);
        Route::post('/{backupConfig}/init-repository', [BackupConfigController::class, 'initRepository']);
        Route::get('/{backupConfig}/stats', [BackupConfigController::class, 'stats']);
        Route::get('/{backupConfig}/snapshots', [BackupConfigController::class, 'snapshots']);
        Route::post('/{backupConfig}/toggle', [BackupConfigController::class, 'toggle']);
    });

    // Backups
    Route::prefix('backups')->group(function () {
        Route::get('/', [BackupController::class, 'index']);
        Route::post('/', [BackupController::class, 'store']);
        Route::delete('/batch', [BackupController::class, 'batchDestroy']);
        Route::get('/{backupId}', [BackupController::class, 'show']);
        Route::delete('/{backup}', [BackupController::class, 'destroy']);

        // Backup actions
        Route::post('/{backupId}/restore', [BackupController::class, 'restore']);
        Route::get('/{backupId}/browse', [BackupController::class, 'browse']);
        Route::get('/{backupId}/objects', [BackupController::class, 'objects']);
    });

    // Local archives
    Route::get('local-archives', [BackupController::class, 'listLocalArchives']);

    // Storage Remotes (Rclone)
    Route::prefix('storage-remotes')->group(function () {
        Route::get('/rclone-status', [StorageRemoteController::class, 'rcloneStatus']);
        Route::post('/install-rclone', [StorageRemoteController::class, 'installRclone']);
        Route::get('/types', [StorageRemoteController::class, 'getTypes']);

        // OAuth routes for cloud storage
        Route::prefix('oauth')->group(function () {
            Route::get('/{provider}/config', [OAuthController::class, 'getConfig']);
            Route::post('/{provider}/authorize', [OAuthController::class, 'initiateAuth']);
            Route::post('/{provider}/token', [OAuthController::class, 'callbackWithToken']);
        });

        Route::get('/', [StorageRemoteController::class, 'index']);
        Route::post('/', [StorageRemoteController::class, 'store']);
        Route::get('/{storageRemote}', [StorageRemoteController::class, 'show']);
        Route::put('/{storageRemote}', [StorageRemoteController::class, 'update']);
        Route::delete('/{storageRemote}', [StorageRemoteController::class, 'destroy']);

        Route::post('/{storageRemote}/test', [StorageRemoteController::class, 'testConnection']);
        Route::get('/{storageRemote}/space', [StorageRemoteController::class, 'getSpace']);
        Route::post('/{storageRemote}/refresh-token', [OAuthController::class, 'refreshToken']);
        Route::get('/{remoteId}/backups', [BackupController::class, 'listRemoteBackups']);
    });
});

// Public OAuth callback route (no auth required - user returns from OAuth provider)
Route::prefix('api/v1/storage-remotes/oauth')->middleware(['api'])->group(function () {
    Route::get('/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
});
