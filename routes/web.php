<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Requestor\LabRequestController;
use App\Http\Controllers\TechnicalController;
use App\Http\Controllers\ValidatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
    });

    Route::middleware('role:requestor')->prefix('requestor')->name('requestor.')->group(function () {
        Route::get('/requests', [LabRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/create', [LabRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests', [LabRequestController::class, 'store'])->name('requests.store');
        Route::get('/requests/{labRequest}', [LabRequestController::class, 'show'])->name('requests.show');
    });

    Route::middleware('role:cs')->prefix('cs')->name('cs.')->group(function () {
        Route::get('/requests', [CsController::class, 'index'])->name('requests.index');
        Route::get('/requests/{labRequest}', [CsController::class, 'show'])->name('requests.show');
        Route::put('/requests/{labRequest}', [CsController::class, 'update'])->name('requests.update');
        Route::post('/requests/{labRequest}/photos', [CsController::class, 'storePhotos'])
            ->name('requests.photos.store');
        Route::delete('/requests/{labRequest}/photos/{specimenPhoto}', [CsController::class, 'destroyPhoto'])
            ->name('requests.photos.destroy');
        Route::get('/photos/{specimenPhoto}', [CsController::class, 'showPhoto'])->name('photos.show');
        Route::post('/requests/{labRequest}/validate', [CsController::class, 'validateRequest'])
            ->name('requests.validate');
    });

    Route::middleware('role:technician')->prefix('technical')->name('technical.')->group(function () {
        Route::get('/requests', [TechnicalController::class, 'index'])->name('requests.index');
        Route::get('/requests/{labRequest}', [TechnicalController::class, 'show'])->name('requests.show');
        Route::put('/requests/{labRequest}/items/{testItem}', [TechnicalController::class, 'updateResult'])
            ->name('requests.items.update');
    });

    Route::middleware('role:validator')->prefix('validator')->name('validator.')->group(function () {
        Route::get('/requests', [ValidatorController::class, 'index'])->name('requests.index');
        Route::get('/requests/{labRequest}', [ValidatorController::class, 'show'])->name('requests.show');
        Route::put('/requests/{labRequest}', [ValidatorController::class, 'updateRequest'])
            ->name('requests.update');
        Route::put('/requests/{labRequest}/final-results', [ValidatorController::class, 'updateFinalResults'])
            ->name('requests.results.update');
        Route::put('/requests/{labRequest}/photos/{sortOrder}', [ValidatorController::class, 'updateAfterPhoto'])
            ->whereNumber('sortOrder')
            ->name('requests.photos.update');
        Route::get('/photos/{specimenPhoto}', [ValidatorController::class, 'showPhoto'])
            ->name('photos.show');
        Route::post('/requests/{labRequest}/finalize', [ValidatorController::class, 'finalize'])
            ->name('requests.finalize');
    });

    Route::get('/reports/{finalReport}/download', [ValidatorController::class, 'download'])
        ->name('reports.download');
});
