<?php

use App\Modules\Migration\Http\Controllers\MigrationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum', 'account.active'])
    ->prefix('api/v1')
    ->group(function () {
        Route::post('migrations/test-connection', [MigrationController::class, 'testConnection']);
        Route::post('migrations/discover', [MigrationController::class, 'discover']);
        Route::post('migrations/{id}/cancel', [MigrationController::class, 'cancel']);

        Route::get('migrations', [MigrationController::class, 'index']);
        Route::post('migrations', [MigrationController::class, 'store']);
        Route::get('migrations/{id}', [MigrationController::class, 'show']);
        Route::delete('migrations/{id}', [MigrationController::class, 'destroy']);
    });
