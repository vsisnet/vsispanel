<?php

use App\Modules\Cron\Http\Controllers\CronController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/cron-jobs')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        Route::get('/', [CronController::class, 'index']);
        Route::post('/', [CronController::class, 'store']);
        Route::post('/validate', [CronController::class, 'validateExpression']);
        Route::get('/{cronJob}', [CronController::class, 'show']);
        Route::put('/{cronJob}', [CronController::class, 'update']);
        Route::delete('/{cronJob}', [CronController::class, 'destroy']);
        Route::post('/{cronJob}/toggle', [CronController::class, 'toggle']);
        Route::post('/{cronJob}/run-now', [CronController::class, 'runNow']);
        Route::get('/{cronJob}/output', [CronController::class, 'output']);
    });
