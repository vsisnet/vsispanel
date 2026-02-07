<?php

declare(strict_types=1);

use App\Modules\Security\Http\Controllers\SecurityController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::prefix('security')->group(function () {
        // Dashboard
        Route::get('/overview', [SecurityController::class, 'overview']);
        Route::get('/score', [SecurityController::class, 'score']);
        Route::post('/score/recalculate', [SecurityController::class, 'recalculateScore']);

        // Audit logs
        Route::get('/audit-logs', [SecurityController::class, 'auditLogs']);
        Route::get('/audit-logs/modules', [SecurityController::class, 'getModules']);
        Route::get('/audit-logs/actions', [SecurityController::class, 'getActions']);

        // Activity stats
        Route::get('/activity/stats', [SecurityController::class, 'activityStats']);
        Route::get('/activity/failed-logins', [SecurityController::class, 'failedLogins']);

        // Fail2Ban
        Route::prefix('fail2ban')->group(function () {
            Route::get('/status', [SecurityController::class, 'fail2banStatus']);
            Route::post('/install', [SecurityController::class, 'installFail2ban']);

            // Jails management
            Route::get('/jails', [SecurityController::class, 'fail2banJails']);
            Route::get('/jails/available', [SecurityController::class, 'fail2banAvailableJails']);
            Route::post('/jails', [SecurityController::class, 'fail2banCreateJail']);
            Route::get('/jails/{jail}', [SecurityController::class, 'fail2banJailStatus']);
            Route::put('/jails/{jail}/config', [SecurityController::class, 'updateJailConfig']);
            Route::post('/jails/{jail}/enable', [SecurityController::class, 'fail2banEnableJail']);
            Route::post('/jails/{jail}/disable', [SecurityController::class, 'fail2banDisableJail']);
            Route::delete('/jails/{jail}', [SecurityController::class, 'fail2banDeleteJail']);

            // Banned IPs
            Route::get('/banned-ips', [SecurityController::class, 'fail2banBannedIps']);
            Route::post('/ban', [SecurityController::class, 'fail2banBanIp']);
            Route::post('/unban', [SecurityController::class, 'fail2banUnbanIp']);

            // Whitelist management
            Route::get('/whitelist', [SecurityController::class, 'fail2banWhitelist']);
            Route::post('/whitelist', [SecurityController::class, 'fail2banAddToWhitelist']);
            Route::delete('/whitelist', [SecurityController::class, 'fail2banRemoveFromWhitelist']);

            // Service control
            Route::post('/restart', [SecurityController::class, 'fail2banRestart']);
            Route::post('/start', [SecurityController::class, 'fail2banStart']);
            Route::post('/stop', [SecurityController::class, 'fail2banStop']);
        });
    });
});
