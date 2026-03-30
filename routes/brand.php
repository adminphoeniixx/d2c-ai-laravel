<?php
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Brand\ShopifyController;
use App\Models\ShopifyConnection;
use Illuminate\Support\Facades\Auth;



Route::middleware(['auth','brand','tenant'])
    ->prefix('brand')
    ->name('brand.')
    ->group(function () {

        Route::get('/dashboard', fn() => Inertia::render('Brand/Dashboard'))->name('dashboard');

        Route::get('/pnl', fn() => Inertia::render('Brand/PnL'))->name('pnl');

        Route::get('/expenses', fn() => Inertia::render('Brand/Expenses'))->name('expenses');

        Route::get('/orders', fn() => Inertia::render('Brand/Orders'))->name('orders');

        Route::get('/ads', fn() => Inertia::render('Brand/Ads'))->name('ads');

        Route::get('/inventory', fn() => Inertia::render('Brand/Inventory'))->name('inventory');

        Route::get('/payroll', fn() => Inertia::render('Brand/Payroll'))->name('payroll');

        Route::get('/cashflow', fn() => Inertia::render('Brand/Cashflow'))->name('cashflow');

        Route::get('/ai', fn() => Inertia::render('Brand/AICopilot'))->name('ai');

        Route::get('/integrations/shopify', function () {

            $connection = ShopifyConnection::where('tenant_id', Auth::user()->tenant_id)
                ->where('is_active', true)
                ->first();

            return Inertia::render('Brand/Integrations/Shopify', [
                'connection' => $connection
            ]);

        });

        // FIXED ROUTES
        Route::get('/shopify/connect', [ShopifyController::class, 'connect']);
        Route::get('/shopify/callback', [ShopifyController::class, 'callback']);

        Route::get('/shopify/orders', [ShopifyController::class, 'orders'])
        ->name('shopify.orders');

        Route::get('/shopify/fetch-orders', [ShopifyController::class, 'fetchOrders'])
            ->name('shopify.fetchOrders');    

    });