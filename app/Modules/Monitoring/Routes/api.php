<?php

use App\Modules\Monitoring\Http\Controllers\AlertController;
use App\Modules\Monitoring\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/monitoring')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        // Metrics
        Route::get('/current', [MonitoringController::class, 'current']);
        Route::get('/history', [MonitoringController::class, 'history']);

        // Services
        Route::get('/services', [MonitoringController::class, 'services']);
        Route::post('/services/{service}/restart', [MonitoringController::class, 'restartService']);
        Route::post('/services/{service}/stop', [MonitoringController::class, 'stopService']);
        Route::post('/services/{service}/start', [MonitoringController::class, 'startService']);
        Route::get('/services/{service}/logs', [MonitoringController::class, 'serviceLogs']);

        // Processes
        Route::get('/processes', [MonitoringController::class, 'processes']);
        Route::post('/processes/{pid}/kill', [MonitoringController::class, 'killProcess']);

        // Alerts - named routes before wildcard
        Route::get('/alerts/summary', [AlertController::class, 'summary']);
        Route::get('/alerts/templates', [AlertController::class, 'templates']);
        Route::post('/alerts/from-template/{template}', [AlertController::class, 'createFromTemplate']);
        Route::get('/alerts/history', [AlertController::class, 'history']);
        Route::post('/alerts/history/{history}/acknowledge', [AlertController::class, 'acknowledge']);
        Route::post('/alerts/history/{history}/resolve', [AlertController::class, 'resolve']);
        Route::post('/alerts/test', [AlertController::class, 'test']);

        // Alerts - CRUD (wildcard routes last)
        Route::get('/alerts', [AlertController::class, 'index']);
        Route::post('/alerts', [AlertController::class, 'store']);
        Route::get('/alerts/{alert}', [AlertController::class, 'show']);
        Route::put('/alerts/{alert}', [AlertController::class, 'update']);
        Route::delete('/alerts/{alert}', [AlertController::class, 'destroy']);
        Route::post('/alerts/{alert}/toggle', [AlertController::class, 'toggle']);
    });
