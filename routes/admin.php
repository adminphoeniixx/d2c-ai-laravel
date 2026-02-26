<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;

Route::middleware('web')
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::middleware('guest')->group(function () {
            Route::get('/login', [LoginController::class, 'create'])->name('login');
            Route::post('/login', [LoginController::class, 'store'])->name('login.store');
        });

        Route::middleware(['auth', 'admin'])->group(function () {

            Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

            Route::get('/dashboard', [DashboardController::class, 'index'])
                ->name('dashboard');

        });

    });