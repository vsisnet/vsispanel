<?php

use App\Modules\Marketplace\Http\Controllers\AppInstallerController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        // App marketplace
        Route::get('/apps', [AppInstallerController::class, 'index']);

        // Domain-scoped installation
        Route::prefix('domains/{domain}/apps')->group(function () {
            Route::post('/check-requirements', [AppInstallerController::class, 'checkRequirements']);
            Route::post('/install', [AppInstallerController::class, 'install']);
            Route::get('/install-status', [AppInstallerController::class, 'installStatus']);
        });
    });
