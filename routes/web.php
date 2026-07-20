<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'admin.dashboard' : 'admin.login');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/connexion', [LoginController::class, 'create'])->name('login');
        Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
        Route::get('/connexion/2fa', [LoginController::class, 'showTwoFactor'])->name('login.two-factor');
        Route::post('/connexion/2fa', [LoginController::class, 'verifyTwoFactor'])->middleware('throttle:login')->name('login.two-factor.verify');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', fn () => view('admin.dashboard'))->name('dashboard');
    });
});
