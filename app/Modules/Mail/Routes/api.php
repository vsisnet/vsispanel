<?php

declare(strict_types=1);

use App\Modules\Mail\Http\Controllers\MailAccountController;
use App\Modules\Mail\Http\Controllers\MailAliasController;
use App\Modules\Mail\Http\Controllers\MailDomainController;
use App\Modules\Mail\Http\Controllers\MailForwardController;
use App\Modules\Mail\Http\Controllers\SpamController;
use App\Modules\Mail\Http\Controllers\WebmailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mail Module API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the MailServiceProvider and grouped under
| the 'api' middleware group with the 'api/v1' prefix.
|
*/

Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1')->group(function () {

    Route::prefix('mail')->name('mail.')->group(function () {

        // Mail Domains
        Route::apiResource('domains', MailDomainController::class)
            ->parameters(['domains' => 'mailDomain'])
            ->names([
                'index' => 'mail.domains.index',
                'store' => 'mail.domains.store',
                'show' => 'mail.domains.show',
                'update' => 'mail.domains.update',
                'destroy' => 'mail.domains.destroy',
            ]);

        // Mail Accounts
        Route::apiResource('accounts', MailAccountController::class)
            ->parameters(['accounts' => 'account'])
            ->names([
                'index' => 'mail.accounts.index',
                'store' => 'mail.accounts.store',
                'show' => 'mail.accounts.show',
                'update' => 'mail.accounts.update',
                'destroy' => 'mail.accounts.destroy',
            ]);

        // Additional account routes
        Route::put('accounts/{account}/password', [MailAccountController::class, 'changePassword'])
            ->name('mail.accounts.password');
        Route::put('accounts/{account}/auto-responder', [MailAccountController::class, 'setAutoResponder'])
            ->name('mail.accounts.auto-responder');
        Route::post('accounts/{account}/forwards', [MailAccountController::class, 'addForward'])
            ->name('mail.accounts.add-forward');
        Route::get('accounts/{account}/usage', [MailAccountController::class, 'usage'])
            ->name('mail.accounts.usage');

        // Webmail routes
        Route::get('accounts/{account}/webmail-url', [MailAccountController::class, 'webmailUrl'])
            ->name('mail.accounts.webmail-url');
        Route::get('accounts/{account}/client-config', [MailAccountController::class, 'mailClientConfig'])
            ->name('mail.accounts.client-config');
        Route::get('webmail/config', [MailAccountController::class, 'webmailConfig'])
            ->name('mail.webmail.config');

        // Mail Aliases
        Route::apiResource('aliases', MailAliasController::class)
            ->parameters(['aliases' => 'alias'])
            ->names([
                'index' => 'mail.aliases.index',
                'store' => 'mail.aliases.store',
                'show' => 'mail.aliases.show',
                'update' => 'mail.aliases.update',
                'destroy' => 'mail.aliases.destroy',
            ]);

        // Mail Forwards
        Route::delete('forwards/{forward}', [MailForwardController::class, 'destroy'])
            ->name('mail.forwards.destroy');
        Route::put('forwards/{forward}/toggle', [MailForwardController::class, 'toggle'])
            ->name('mail.forwards.toggle');

        // Spam Settings
        Route::prefix('spam')->group(function () {
            Route::get('settings', [SpamController::class, 'getSettings'])
                ->name('mail.spam.settings');
            Route::put('settings', [SpamController::class, 'updateSettings'])
                ->name('mail.spam.settings.update');

            Route::get('whitelist', [SpamController::class, 'getWhitelist'])
                ->name('mail.spam.whitelist');
            Route::post('whitelist', [SpamController::class, 'addToWhitelist'])
                ->name('mail.spam.whitelist.add');
            Route::delete('whitelist/{entry}', [SpamController::class, 'removeFromWhitelist'])
                ->name('mail.spam.whitelist.remove');

            Route::get('blacklist', [SpamController::class, 'getBlacklist'])
                ->name('mail.spam.blacklist');
            Route::post('blacklist', [SpamController::class, 'addToBlacklist'])
                ->name('mail.spam.blacklist.add');
            Route::delete('blacklist/{entry}', [SpamController::class, 'removeFromBlacklist'])
                ->name('mail.spam.blacklist.remove');

            Route::get('history', [SpamController::class, 'getHistory'])
                ->name('mail.spam.history');
            Route::post('train/ham', [SpamController::class, 'trainHam'])
                ->name('mail.spam.train.ham');
            Route::post('train/spam', [SpamController::class, 'trainSpam'])
                ->name('mail.spam.train.spam');
        });

        // Webmail SSO routes
        Route::prefix('webmail')->group(function () {
            Route::get('config', [WebmailController::class, 'config'])
                ->name('mail.webmail.config');
            Route::post('auto-login', [WebmailController::class, 'autoLogin'])
                ->name('mail.webmail.auto-login');
        });
    });
});

// Public route for SSO validation (called by Roundcube plugin)
Route::middleware(['api'])->prefix('api/v1/mail/webmail')->group(function () {
    Route::post('validate-sso', [WebmailController::class, 'validateSso'])
        ->name('mail.webmail.validate-sso');
});
