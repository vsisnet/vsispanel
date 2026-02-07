<?php

declare(strict_types=1);

use App\Modules\FTP\Http\Controllers\FtpAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1')->group(function () {
    // FTP Service Management
    Route::prefix('ftp')->name('ftp.')->group(function () {
        // Service status and control
        Route::get('/status', [FtpAccountController::class, 'status'])->name('status');
        Route::get('/statistics', [FtpAccountController::class, 'statistics'])->name('statistics');
        Route::get('/connected-users', [FtpAccountController::class, 'connectedUsers'])->name('connected-users');
        Route::post('/disconnect-user', [FtpAccountController::class, 'disconnectUser'])->name('disconnect-user');
        Route::get('/logs', [FtpAccountController::class, 'logs'])->name('logs');
        Route::get('/transfer-logs', [FtpAccountController::class, 'transferLogs'])->name('transfer-logs');
        Route::post('/restart', [FtpAccountController::class, 'restart'])->name('restart');
        Route::post('/reload', [FtpAccountController::class, 'reload'])->name('reload');
        Route::get('/test-config', [FtpAccountController::class, 'testConfig'])->name('test-config');

        // FTP Accounts CRUD
        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/', [FtpAccountController::class, 'index'])->name('index');
            Route::post('/', [FtpAccountController::class, 'store'])->name('store');

            // Bulk operations (must be before {ftpAccount} routes)
            Route::post('/bulk-activate', [FtpAccountController::class, 'bulkActivate'])->name('bulk-activate');
            Route::post('/bulk-suspend', [FtpAccountController::class, 'bulkSuspend'])->name('bulk-suspend');
            Route::post('/bulk-delete', [FtpAccountController::class, 'bulkDelete'])->name('bulk-delete');

            Route::get('/{ftpAccount}', [FtpAccountController::class, 'show'])->name('show');
            Route::put('/{ftpAccount}', [FtpAccountController::class, 'update'])->name('update');
            Route::delete('/{ftpAccount}', [FtpAccountController::class, 'destroy'])->name('destroy');
            Route::post('/{ftpAccount}/change-password', [FtpAccountController::class, 'changePassword'])->name('change-password');
            Route::post('/{ftpAccount}/toggle-status', [FtpAccountController::class, 'toggleStatus'])->name('toggle-status');
        });
    });
});
