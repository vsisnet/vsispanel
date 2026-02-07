<?php

declare(strict_types=1);

use App\Modules\Task\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::prefix('tasks')->group(function () {
        // List and filter tasks
        Route::get('/', [TaskController::class, 'index']);

        // Get active tasks
        Route::get('/active', [TaskController::class, 'active']);

        // Get recent tasks
        Route::get('/recent', [TaskController::class, 'recent']);

        // Get task statistics
        Route::get('/stats', [TaskController::class, 'stats']);

        // Get available task types
        Route::get('/types', [TaskController::class, 'types']);

        // Bulk delete
        Route::post('/bulk-delete', [TaskController::class, 'bulkDelete']);

        // Cleanup old tasks
        Route::post('/cleanup', [TaskController::class, 'cleanup']);

        // Single task operations
        Route::get('/{task}', [TaskController::class, 'show']);
        Route::delete('/{task}', [TaskController::class, 'destroy']);
        Route::post('/{task}/cancel', [TaskController::class, 'cancel']);
        Route::post('/{task}/retry', [TaskController::class, 'retry']);
        Route::get('/{task}/output', [TaskController::class, 'output']);
    });
});
