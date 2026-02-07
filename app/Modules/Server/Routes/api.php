<?php

use App\Modules\Server\Http\Controllers\DashboardController;
use App\Modules\Server\Http\Controllers\SshTerminalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Server Module API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->prefix('dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/metrics', [DashboardController::class, 'metrics']);
    Route::get('/activity', [DashboardController::class, 'activity']);
    Route::get('/system-info', [DashboardController::class, 'systemInfo']);
    Route::get('/realtime', [DashboardController::class, 'realtime']);
});

Route::middleware(['auth:sanctum'])->prefix('terminal')->group(function () {
    Route::post('/sessions', [SshTerminalController::class, 'createSession']);
    Route::get('/sessions', [SshTerminalController::class, 'sessions']);
    Route::delete('/sessions/{sessionId}', [SshTerminalController::class, 'closeSession']);
});
