<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HeartbeatController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');
    Route::post('/auth/2fa/verify', [AuthController::class, 'verifyTwoFactor'])
        ->middleware('throttle:login')
        ->name('auth.two-factor.verify');

    Route::middleware(['auth:sanctum', 'token.active'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/heartbeat', HeartbeatController::class)->name('heartbeat');
    });
});
