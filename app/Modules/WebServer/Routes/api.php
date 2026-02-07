<?php

use App\Modules\WebServer\Http\Controllers\PhpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WebServer Module API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1')->middleware(['api', 'auth:sanctum'])->group(function () {
    // PHP Version Management
    Route::get('/php/versions', [PhpController::class, 'versions']);
    Route::get('/php/{version}/info', [PhpController::class, 'info']);
    Route::get('/php/{version}/status', [PhpController::class, 'status']);

    // Domain PHP Settings
    Route::put('/domains/{domain}/php-version', [PhpController::class, 'switchVersion']);
    Route::get('/domains/{domain}/php-settings', [PhpController::class, 'getSettings']);
    Route::put('/domains/{domain}/php-settings', [PhpController::class, 'updateSettings']);
});
