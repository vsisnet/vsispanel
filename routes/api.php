<?php

use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Health Check endpoints (no auth required)
Route::prefix('health')->group(function () {
    Route::get('/', [HealthController::class, 'index']);
    Route::get('/detailed', [HealthController::class, 'detailed']);
    Route::get('/system', [HealthController::class, 'system'])->middleware('auth:sanctum');
});

// API Version 1 routes
Route::prefix('v1')->group(function () {
    // Health check for v1
    Route::get('/health', [HealthController::class, 'index']);

    // Module routes will be loaded via ModuleServiceProvider
    // and will automatically be available under /api/v1/*
});

// API routes loaded from modules via ModuleServiceProvider
// Module routes are available directly under /api/*
