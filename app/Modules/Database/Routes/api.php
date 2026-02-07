<?php

use App\Modules\Database\Http\Controllers\DatabaseController;
use App\Modules\Database\Http\Controllers\DatabaseUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Database Module API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1')->middleware(['api', 'auth:sanctum'])->group(function () {
    // Database Manager Actions (must be before apiResource to avoid conflicts)
    Route::post('databases/root-password', [DatabaseController::class, 'changeRootPassword']);
    Route::get('databases/server-databases', [DatabaseController::class, 'serverDatabases']);
    Route::post('databases/sync-from-server', [DatabaseController::class, 'syncFromServer']);
    Route::get('databases/phpmyadmin-url', [DatabaseController::class, 'phpMyAdminUrl']);

    // Databases
    Route::apiResource('databases', DatabaseController::class)->except(['update']);
    Route::get('databases/{database}/size', [DatabaseController::class, 'size']);
    Route::get('databases/{database}/tables', [DatabaseController::class, 'tables']);
    Route::post('databases/{database}/backup', [DatabaseController::class, 'backup']);
    Route::post('databases/{database}/import', [DatabaseController::class, 'import']);
    Route::get('databases/{database}/phpmyadmin-sso', [DatabaseController::class, 'phpMyAdminSso']);

    // Database Users
    Route::get('database-users/privileges', [DatabaseUserController::class, 'privileges']);
    Route::apiResource('database-users', DatabaseUserController::class)->except(['update']);
    Route::put('database-users/{databaseUser}/password', [DatabaseUserController::class, 'changePassword']);
    Route::post('database-users/{databaseUser}/grant', [DatabaseUserController::class, 'grant']);
    Route::post('database-users/{databaseUser}/revoke', [DatabaseUserController::class, 'revoke']);
});
