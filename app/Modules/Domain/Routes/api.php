<?php

use App\Modules\Domain\Http\Controllers\DomainController;
use App\Modules\Domain\Http\Controllers\SubdomainController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Domain Module API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api', 'auth:sanctum', 'account.active'])
    ->prefix('api/v1')
    ->group(function () {
        // Domain CRUD
        Route::apiResource('domains', DomainController::class);

        // Domain actions
        Route::post('domains/{domain}/suspend', [DomainController::class, 'suspend']);
        Route::post('domains/{domain}/unsuspend', [DomainController::class, 'unsuspend']);
        Route::get('domains/{domain}/disk-usage', [DomainController::class, 'diskUsage']);

        // Subdomains (nested under domain)
        Route::apiResource('domains.subdomains', SubdomainController::class)
            ->except(['update']);
    });
