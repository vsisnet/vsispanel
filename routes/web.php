<?php

use App\Http\Controllers\SetupController;
use App\Http\Middleware\EnsureSetupNotComplete;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Setup Wizard API (only accessible before installation is complete)
Route::prefix('api/setup')->middleware(EnsureSetupNotComplete::class)->group(function () {
    Route::post('/check-requirements', [SetupController::class, 'checkRequirements']);
    Route::post('/test-database', [SetupController::class, 'testDatabase']);
    Route::post('/configure', [SetupController::class, 'configure']);
    Route::post('/create-admin', [SetupController::class, 'createAdmin']);
    Route::post('/finalize', [SetupController::class, 'finalize']);
});

// Check installation status
Route::get('/api/setup/status', function () {
    return response()->json([
        'success' => true,
        'data' => ['installed' => file_exists(storage_path('installed'))],
    ]);
});

// SSO Auto-Login Route
Route::get("/sso", function () {
    return view("sso");
});

// Serve Vue SPA for all routes
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
