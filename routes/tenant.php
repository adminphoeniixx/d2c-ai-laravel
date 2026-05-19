<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AdAnalyticsController;
use App\Http\Controllers\Tenant\AiCopilotController;
use App\Http\Controllers\Tenant\CashFlowController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\ExpensesController;
use App\Http\Controllers\Tenant\Integrations\ShopifyController;
use App\Http\Controllers\Tenant\Integrations\WooCommerceController;
use App\Http\Controllers\Tenant\InventoryForecastController;
use App\Http\Controllers\Tenant\OrdersController;
use App\Http\Controllers\Tenant\PayrollIntelligenceController;
use App\Http\Controllers\Tenant\PnLReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes — /app/{tenant}/...
|--------------------------------------------------------------------------
| The `tenant` middleware resolves the Company from the {tenant} slug and
| switches the Postgres search_path to the tenant schema.
*/

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {

    /* ── Phase 1 ─────────────────────────────────── */
    Route::get('/',              [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/p-and-l',       [PnLReportController::class, 'index'])->name('pnl');
    Route::get('/expenses',      [ExpensesController::class, 'index'])->name('expenses');
    Route::post('/expenses',     [ExpensesController::class, 'store'])->name('expenses.store');
    Route::get('/orders',        [OrdersController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('orders.show');
    Route::get('/ad-analytics',  [AdAnalyticsController::class, 'index'])->name('ads');

    /* ── GST Reports ────────────────────────────────── */
    Route::get('/gst',              [\App\Http\Controllers\Tenant\GSTController::class, 'index'])->name('gst');
    Route::get('/gst/export',       [\App\Http\Controllers\Tenant\GSTExportController::class, 'gstr1'])->name('gst.export');
    Route::get('/orders/export',    [\App\Http\Controllers\Tenant\GSTExportController::class, 'ordersExport'])->name('orders.export');

    /* ── Company Settings ───────────────────────────── */
    Route::get('/settings',      [\App\Http\Controllers\Tenant\CompanySettingsController::class, 'index'])->name('settings');
    Route::put('/settings',      [\App\Http\Controllers\Tenant\CompanySettingsController::class, 'update'])->name('settings.update');

    /* ── Phase 2 ─────────────────────────────────── */
    Route::get('/inventory',     [InventoryForecastController::class, 'index'])->name('inventory');
    Route::get('/payroll',       [PayrollIntelligenceController::class, 'index'])->name('payroll');
    Route::get('/cashflow',      [CashFlowController::class, 'index'])->name('cashflow');

    /* ── AI ──────────────────────────────────────── */
    Route::get('/ai',            [AiCopilotController::class, 'index'])->name('ai');
    Route::post('/ai/prompt',    [AiCopilotController::class, 'prompt'])->name('ai.prompt');

    /* ── Integrations ────────────────────────────── */
    Route::prefix('integrations')->name('integrations.')->group(function () {
        // Shopify OAuth flow + manual
        Route::get('/shopify',              [ShopifyController::class, 'show'])->name('shopify.show');
        Route::post('/shopify/connect',     [ShopifyController::class, 'connect'])->name('shopify.connect');
        Route::get('/shopify/callback',     [ShopifyController::class, 'callback'])->name('shopify.callback');
        Route::post('/shopify/manual',      [ShopifyController::class, 'manual'])->name('shopify.manual');
        Route::delete('/shopify',           [ShopifyController::class, 'disconnect'])->name('shopify.disconnect');

        // WooCommerce REST auth flow + manual
        Route::get('/woo',                  [WooCommerceController::class, 'show'])->name('woo.show');
        Route::post('/woo/connect',         [WooCommerceController::class, 'connect'])->name('woo.connect');
        Route::get('/woo/callback',         [WooCommerceController::class, 'callback'])->name('woo.callback');
        Route::post('/woo/manual',          [WooCommerceController::class, 'manual'])->name('woo.manual');
        Route::delete('/woo',               [WooCommerceController::class, 'disconnect'])->name('woo.disconnect');
    });
});
