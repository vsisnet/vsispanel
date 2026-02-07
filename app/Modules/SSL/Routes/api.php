<?php

declare(strict_types=1);

use App\Modules\SSL\Http\Controllers\SslController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', 'auth:sanctum'])->group(function () {
    // SSL Certificate routes
    Route::prefix('ssl')->group(function () {
        // List certificates (filtered by user's domains for non-admin)
        Route::get('/', [SslController::class, 'index'])->name('ssl.index');

        // Check expiring certificates
        Route::get('/check-expiry', [SslController::class, 'checkExpiry'])->name('ssl.check-expiry');

        // Issue Let's Encrypt certificate for a domain
        Route::post('/domains/{domain}/letsencrypt', [SslController::class, 'issueLetsEncrypt'])
            ->name('ssl.issue-letsencrypt');

        // Upload custom certificate for a domain
        Route::post('/domains/{domain}/custom', [SslController::class, 'uploadCustom'])
            ->name('ssl.upload-custom');

        // Single certificate operations
        Route::get('/{ssl}', [SslController::class, 'show'])->name('ssl.show');
        Route::get('/{ssl}/info', [SslController::class, 'info'])->name('ssl.info');
        Route::post('/{ssl}/renew', [SslController::class, 'renew'])->name('ssl.renew');
        Route::post('/{ssl}/toggle-auto-renew', [SslController::class, 'toggleAutoRenew'])
            ->name('ssl.toggle-auto-renew');
        Route::delete('/{ssl}', [SslController::class, 'destroy'])->name('ssl.destroy');
    });
});
