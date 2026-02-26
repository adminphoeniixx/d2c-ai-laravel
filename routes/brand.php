<?php
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;





    Route::middleware(['auth', 'brand', 'tenant'])
    ->prefix('brand')
    ->name('brand.')
    ->group(function () {

        Route::get('/dashboard', function () {
            return Inertia::render('Brand/Dashboard');
        })->name('dashboard');

    });