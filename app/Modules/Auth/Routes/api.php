<?php

use App\Modules\Auth\Http\Controllers\ForgotPasswordController;
use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Controllers\ProfileController;
use App\Modules\Auth\Http\Controllers\ResetPasswordController;
use App\Modules\Auth\Http\Controllers\TwoFactorController;
use App\Modules\Auth\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('login', [LoginController::class, 'login']);
    Route::post('login/2fa', [LoginController::class, 'verifyTwoFactor']);
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
    Route::post('reset-password', [ResetPasswordController::class, 'reset']);

    // Protected routes (require authentication)
    Route::middleware(['auth:sanctum', 'account.active', 'track.login'])->group(function () {
        // Logout
        Route::post('logout', [LoginController::class, 'logout']);

        // Profile
        Route::get('me', [ProfileController::class, 'me']);
        Route::put('profile', [ProfileController::class, 'updateProfile']);
        Route::put('password', [ProfileController::class, 'updatePassword']);

        // Two-Factor Authentication
        Route::prefix('2fa')->group(function () {
            Route::post('enable', [TwoFactorController::class, 'enable']);
            Route::post('confirm', [TwoFactorController::class, 'confirm']);
            Route::post('disable', [TwoFactorController::class, 'disable']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| User Management API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('users')->middleware(['auth:sanctum', 'account.active'])->group(function () {
    // Get users for select dropdown (minimal permissions required)
    Route::get('/select', [UserController::class, 'listForSelect']);

    // Stats
    Route::get('/stats', [UserController::class, 'stats']);

    // CRUD operations (admin only - checked in FormRequest)
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/{user}', [UserController::class, 'show']);
    Route::put('/{user}', [UserController::class, 'update']);
    Route::delete('/{user}', [UserController::class, 'destroy']);

    // User actions
    Route::post('/{user}/suspend', [UserController::class, 'suspend']);
    Route::post('/{user}/unsuspend', [UserController::class, 'unsuspend']);
    Route::post('/{user}/impersonate', [UserController::class, 'impersonate']);
});
