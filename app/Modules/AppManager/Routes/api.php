<?php

use App\Modules\AppManager\Http\Controllers\AppManagerController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/app-manager')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        Route::get('/', [AppManagerController::class, 'index']);
        Route::post('/scan', [AppManagerController::class, 'scan']);

        Route::get('/{slug}', [AppManagerController::class, 'show']);
        Route::post('/{slug}/start', [AppManagerController::class, 'start']);
        Route::post('/{slug}/stop', [AppManagerController::class, 'stop']);
        Route::post('/{slug}/restart', [AppManagerController::class, 'restart']);
        Route::post('/{slug}/enable', [AppManagerController::class, 'enable']);
        Route::post('/{slug}/disable', [AppManagerController::class, 'disable']);
        Route::post('/{slug}/install', [AppManagerController::class, 'install']);
        Route::post('/{slug}/uninstall', [AppManagerController::class, 'uninstall']);
        Route::post('/{slug}/set-default', [AppManagerController::class, 'setDefaultVersion']);
        Route::get('/{slug}/extensions', [AppManagerController::class, 'extensions']);
        Route::get('/{slug}/available-extensions', [AppManagerController::class, 'availableExtensions']);
        Route::post('/{slug}/extensions/install', [AppManagerController::class, 'installExtension']);
        Route::post('/{slug}/extensions/uninstall', [AppManagerController::class, 'uninstallExtension']);
        Route::get('/{slug}/config/{key}', [AppManagerController::class, 'getConfig']);
        Route::put('/{slug}/config/{key}', [AppManagerController::class, 'saveConfig']);
        Route::get('/{slug}/logs', [AppManagerController::class, 'logs']);
    });
