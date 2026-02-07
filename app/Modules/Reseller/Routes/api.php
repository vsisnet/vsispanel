<?php

use App\Modules\Reseller\Http\Controllers\ResellerBrandingController;
use App\Modules\Reseller\Http\Controllers\ResellerCustomerController;
use App\Modules\Reseller\Http\Controllers\ResellerReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/reseller')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        // Customers
        Route::get('/customers', [ResellerCustomerController::class, 'index']);
        Route::post('/customers', [ResellerCustomerController::class, 'store']);
        Route::get('/customers/{customer}', [ResellerCustomerController::class, 'show']);
        Route::post('/customers/{customer}/suspend', [ResellerCustomerController::class, 'suspend']);
        Route::post('/customers/{customer}/unsuspend', [ResellerCustomerController::class, 'unsuspend']);
        Route::post('/customers/{customer}/terminate', [ResellerCustomerController::class, 'terminate']);
        Route::post('/customers/{customer}/impersonate', [ResellerCustomerController::class, 'impersonate']);

        // Resource usage
        Route::get('/resource-usage', [ResellerCustomerController::class, 'resourceUsage']);

        // Branding
        Route::get('/branding', [ResellerBrandingController::class, 'show']);
        Route::put('/branding', [ResellerBrandingController::class, 'update']);
        Route::post('/branding/logo', [ResellerBrandingController::class, 'uploadLogo']);

        // Reports
        Route::get('/reports/overview', [ResellerReportController::class, 'overview']);
        Route::get('/reports/growth', [ResellerReportController::class, 'growth']);
        Route::get('/reports/customers', [ResellerReportController::class, 'customerBreakdown']);
    });
