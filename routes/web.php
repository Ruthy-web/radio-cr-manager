<?php

use App\Http\Controllers\Admin\ExamTemplateController;
use App\Http\Controllers\Admin\HospitalController;
use App\Http\Controllers\Admin\ReportController;
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

        Route::middleware('role:admin')->group(function () {
            Route::post('/hopitaux/{hospital}/reactiver', [HospitalController::class, 'restore'])->name('hospitals.restore');
            Route::resource('hopitaux', HospitalController::class)
                ->parameters(['hopitaux' => 'hospital'])
                ->names('hospitals')
                ->except(['show']);

            Route::post('/hopitaux/{hospital}/examens/{exam_template}/reactiver', [ExamTemplateController::class, 'restore'])
                ->name('hospitals.exam-templates.restore');
            Route::resource('hopitaux.examens', ExamTemplateController::class)
                ->parameters(['hopitaux' => 'hospital', 'examens' => 'exam_template'])
                ->names('hospitals.exam-templates')
                ->except(['show']);
        });

        Route::prefix('comptes-rendus')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/creer', [ReportController::class, 'create'])->name('create');
            Route::post('/', [ReportController::class, 'store'])->name('store');
            Route::get('/{report}/modifier', [ReportController::class, 'edit'])->name('edit');
            Route::put('/{report}', [ReportController::class, 'update'])->name('update');
            Route::delete('/{report}', [ReportController::class, 'destroy'])->name('destroy');
            Route::post('/{report}/finaliser', [ReportController::class, 'finalize'])->name('finalize');
            Route::post('/{report}/signer', [ReportController::class, 'sign'])->name('sign');
            Route::post('/{report}/versions/{version}/restaurer', [ReportController::class, 'restoreVersion'])->name('versions.restore');
        });
    });
});
