<?php

use App\Modules\Firewall\Http\Controllers\FirewallController;
use App\Modules\Firewall\Http\Controllers\Fail2BanController;
use App\Modules\Firewall\Http\Controllers\WafController;
use App\Modules\Firewall\Http\Controllers\MalwareScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::prefix('firewall')->group(function () {
        // Status
        Route::get('/status', [FirewallController::class, 'status']);

        // Enable/Disable
        Route::post('/enable', [FirewallController::class, 'enable']);
        Route::post('/disable', [FirewallController::class, 'disable']);

        // Reset
        Route::post('/reset', [FirewallController::class, 'reset']);

        // Default policy
        Route::post('/policy', [FirewallController::class, 'setDefaultPolicy']);

        // Sync with UFW
        Route::post('/sync', [FirewallController::class, 'sync']);

        // Quick actions
        Route::post('/block-ip', [FirewallController::class, 'blockIp']);
        Route::post('/allow-ip', [FirewallController::class, 'allowIp']);

        // Rules CRUD
        Route::get('/rules', [FirewallController::class, 'index']);
        Route::post('/rules', [FirewallController::class, 'store']);
        Route::get('/rules/{rule}', [FirewallController::class, 'show']);
        Route::put('/rules/{rule}', [FirewallController::class, 'update']);
        Route::delete('/rules/{rule}', [FirewallController::class, 'destroy']);
        Route::put('/rules/{rule}/toggle', [FirewallController::class, 'toggle']);
    });

    // Fail2Ban routes
    Route::prefix('security/fail2ban')->group(function () {
        Route::get('/status', [Fail2BanController::class, 'status']);
        Route::get('/jails', [Fail2BanController::class, 'jails']);
        Route::get('/jails/{jail}', [Fail2BanController::class, 'jail']);
        Route::put('/jails/{jail}/config', [Fail2BanController::class, 'updateJailConfig']);
        Route::get('/banned', [Fail2BanController::class, 'banned']);
        Route::post('/ban', [Fail2BanController::class, 'ban']);
        Route::post('/unban', [Fail2BanController::class, 'unban']);
        Route::post('/restart', [Fail2BanController::class, 'restart']);
    });

    // IP Management routes
    Route::prefix('security')->group(function () {
        Route::get('/ip-whitelist', [Fail2BanController::class, 'whitelist']);
        Route::post('/ip-whitelist', [Fail2BanController::class, 'addToWhitelist']);
        Route::delete('/ip-whitelist', [Fail2BanController::class, 'removeFromWhitelist']);
        Route::get('/ip-blacklist', [Fail2BanController::class, 'blacklist']);
        Route::post('/ip-blacklist', [Fail2BanController::class, 'addToBlacklist']);
        Route::delete('/ip-blacklist', [Fail2BanController::class, 'removeFromBlacklist']);
        Route::get('/ip-info/{ip}', [Fail2BanController::class, 'ipInfo']);
    });

    // WAF routes
    Route::prefix('security/waf')->group(function () {
        Route::get('/status', [WafController::class, 'status']);
        Route::post('/enable', [WafController::class, 'enable']);
        Route::post('/disable', [WafController::class, 'disable']);
        Route::post('/mode', [WafController::class, 'setMode']);
        Route::get('/audit-log', [WafController::class, 'auditLog']);
        Route::get('/rulesets', [WafController::class, 'rulesets']);
        Route::post('/rulesets/{ruleset}/enable', [WafController::class, 'enableRuleset']);
        Route::post('/rulesets/{ruleset}/disable', [WafController::class, 'disableRuleset']);
        Route::get('/whitelist', [WafController::class, 'whitelist']);
        Route::post('/whitelist', [WafController::class, 'addToWhitelist']);
        Route::delete('/whitelist/{ruleId}', [WafController::class, 'removeFromWhitelist']);
    });

    // Malware Scan routes
    Route::prefix('security/malware')->group(function () {
        Route::get('/status', [MalwareScanController::class, 'status']);
        Route::post('/scan', [MalwareScanController::class, 'scanPath']);
        Route::post('/scan/domain/{domain}', [MalwareScanController::class, 'scanDomain']);
        Route::get('/scans', [MalwareScanController::class, 'recentScans']);
        Route::get('/quarantine', [MalwareScanController::class, 'quarantine']);
        Route::post('/quarantine', [MalwareScanController::class, 'quarantineFile']);
        Route::post('/quarantine/{quarantineId}/restore', [MalwareScanController::class, 'restore']);
        Route::delete('/quarantine/{quarantineId}', [MalwareScanController::class, 'deleteQuarantined']);
        Route::post('/update-definitions', [MalwareScanController::class, 'updateDefinitions']);
    });
});
