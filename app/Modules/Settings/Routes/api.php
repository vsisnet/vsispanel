<?php

use App\Modules\Settings\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/v1')
    ->group(function () {
        Route::get('settings', [SettingsController::class, 'index']);
        Route::put('settings', [SettingsController::class, 'update']);
        Route::get('settings/timezones', [SettingsController::class, 'timezones']);
        Route::post('settings/notifications/test', [SettingsController::class, 'testNotification']);
        Route::post('settings/time/sync', [SettingsController::class, 'syncTime']);

        // Gmail OAuth2
        Route::get('settings/mail/gmail/status', [SettingsController::class, 'gmailOAuthStatus']);
        Route::post('settings/mail/gmail/authorize', [SettingsController::class, 'gmailAuthorize']);
        Route::post('settings/mail/gmail/revoke', [SettingsController::class, 'gmailRevoke']);
    });

// Public OAuth callback route (no auth - user returns from Google via OAuth Proxy)
Route::prefix('api/v1/settings/mail/gmail')
    ->middleware(['api'])
    ->group(function () {
        Route::get('callback', [SettingsController::class, 'gmailCallback'])->name('settings.gmail.callback');
    });
