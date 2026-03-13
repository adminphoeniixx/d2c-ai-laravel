<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth','brand','tenant'])
    ->prefix('brand')
    ->name('brand.')
    ->group(function () {

        Route::get('/dashboard', function () {
            return Inertia::render('Brand/Dashboard');
        })->name('dashboard');

        Route::get('/pnl', function () {
            return Inertia::render('Brand/PnL');
        })->name('pnl');

        Route::get('/expenses', function () {
            return Inertia::render('Brand/Expenses');
        })->name('expenses');

        Route::get('/orders', function () {
            return Inertia::render('Brand/Orders');
        })->name('orders');

        Route::get('/ads', function () {
            return Inertia::render('Brand/Ads');
        })->name('ads');

        Route::get('/inventory', fn() => Inertia::render('Brand/Inventory'))->name('inventory');
        Route::get('/payroll', fn() => Inertia::render('Brand/Payroll'))->name('payroll');
        Route::get('/cashflow', fn() => Inertia::render('Brand/Cashflow'))->name('cashflow');
        Route::get('/ai', fn() => Inertia::render('Brand/AICopilot'))->name('ai');

    });