<?php

declare(strict_types=1);

use App\Modules\Hosting\Http\Controllers\PlanController;
use App\Modules\Hosting\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Hosting Module API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1')->group(function () {
    // =========================================================================
    // Plans
    // =========================================================================

    // Public routes (view available plans)
    Route::get('plans/available', [PlanController::class, 'available']);

    // Plan management (admin-only, enforced via controller)
    Route::apiResource('plans', PlanController::class);
    Route::post('plans/{plan}/activate', [PlanController::class, 'activate']);
    Route::post('plans/{plan}/deactivate', [PlanController::class, 'deactivate']);
    Route::post('plans/{plan}/clone', [PlanController::class, 'clone']);

    // =========================================================================
    // Subscriptions
    // =========================================================================

    // User routes
    Route::get('subscriptions/current', [SubscriptionController::class, 'current']);
    Route::get('subscriptions/quota', [SubscriptionController::class, 'quota']);

    // Subscription management (admin-only, enforced via controller)
    Route::get('subscriptions', [SubscriptionController::class, 'index']);
    Route::post('subscriptions', [SubscriptionController::class, 'store']);
    Route::get('subscriptions/statistics', [SubscriptionController::class, 'statistics']);
    Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show']);
    Route::post('subscriptions/{subscription}/change-plan', [SubscriptionController::class, 'changePlan']);
    Route::post('subscriptions/{subscription}/suspend', [SubscriptionController::class, 'suspend']);
    Route::post('subscriptions/{subscription}/unsuspend', [SubscriptionController::class, 'unsuspend']);
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew']);
});
