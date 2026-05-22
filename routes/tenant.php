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
    Route::post('/settings/letterhead', [\App\Http\Controllers\Tenant\CompanySettingsController::class, 'uploadLetterhead'])->name('settings.letterhead.upload');
    Route::delete('/settings/letterhead', [\App\Http\Controllers\Tenant\CompanySettingsController::class, 'removeLetterhead'])->name('settings.letterhead.remove');

    /* ── Phase 2 (redirects for legacy sidebar links) ── */
    Route::get('/inventory',     fn () => redirect()->route('tenant.inventory-mgmt.index', ['tenant' => request()->route('tenant')]))->name('inventory');
    Route::get('/cashflow',      [CashFlowController::class, 'index'])->name('cashflow');

    /* ── HR Module ─────────────────────────────── */
    Route::prefix('hr')->name('hr.')->group(function () {
        // Employees
        Route::get('/employees',            [\App\Http\Controllers\Tenant\EmployeeController::class, 'index'])->name('employees');
        Route::get('/employees/create',     [\App\Http\Controllers\Tenant\EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees',           [\App\Http\Controllers\Tenant\EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{id}',       [\App\Http\Controllers\Tenant\EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/employees/{id}/edit',  [\App\Http\Controllers\Tenant\EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{id}',       [\App\Http\Controllers\Tenant\EmployeeController::class, 'update'])->name('employees.update');

        // Letter Templates
        Route::get('/templates',            [\App\Http\Controllers\Tenant\LetterController::class, 'templates'])->name('templates');
        Route::post('/templates',           [\App\Http\Controllers\Tenant\LetterController::class, 'storeTemplate'])->name('templates.store');
        Route::put('/templates/{id}',       [\App\Http\Controllers\Tenant\LetterController::class, 'updateTemplate'])->name('templates.update');
        Route::delete('/templates/{id}',    [\App\Http\Controllers\Tenant\LetterController::class, 'destroyTemplate'])->name('templates.destroy');

        // Letters
        Route::get('/letters/create',       [\App\Http\Controllers\Tenant\LetterController::class, 'create'])->name('letters.create');
        Route::post('/letters',             [\App\Http\Controllers\Tenant\LetterController::class, 'store'])->name('letters.store');
        Route::get('/letters/{id}',         [\App\Http\Controllers\Tenant\LetterController::class, 'show'])->name('letters.show');
        Route::put('/letters/{id}',         [\App\Http\Controllers\Tenant\LetterController::class, 'update'])->name('letters.update');
        Route::delete('/letters/{id}',      [\App\Http\Controllers\Tenant\LetterController::class, 'destroy'])->name('letters.destroy');

        // Attendance & Working Hours
        Route::get('/attendance',           [\App\Http\Controllers\Tenant\AttendanceController::class, 'index'])->name('attendance');
        Route::post('/attendance',          [\App\Http\Controllers\Tenant\AttendanceController::class, 'store'])->name('attendance.store');
        Route::post('/attendance/bulk',     [\App\Http\Controllers\Tenant\AttendanceController::class, 'bulkStore'])->name('attendance.bulk');

        // Employee Documents
        Route::post('/documents',           [\App\Http\Controllers\Tenant\EmployeeDocumentController::class, 'store'])->name('documents.store');
        Route::delete('/documents/{id}',    [\App\Http\Controllers\Tenant\EmployeeDocumentController::class, 'destroy'])->name('documents.destroy');

        // Workers (श्रमिक)
        Route::get('/workers',              [\App\Http\Controllers\Tenant\WorkerController::class, 'index'])->name('workers');
        Route::get('/workers/create',       [\App\Http\Controllers\Tenant\WorkerController::class, 'create'])->name('workers.create');
        Route::post('/workers',             [\App\Http\Controllers\Tenant\WorkerController::class, 'store'])->name('workers.store');
        Route::get('/workers/{worker}',     [\App\Http\Controllers\Tenant\WorkerController::class, 'show'])->name('workers.show');
        Route::get('/workers/{worker}/edit',[\App\Http\Controllers\Tenant\WorkerController::class, 'edit'])->name('workers.edit');
        Route::put('/workers/{worker}',     [\App\Http\Controllers\Tenant\WorkerController::class, 'update'])->name('workers.update');
        Route::delete('/workers/{worker}',  [\App\Http\Controllers\Tenant\WorkerController::class, 'destroy'])->name('workers.destroy');
    });

    /* ── Payroll ───────────────────────────────── */
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/',            [\App\Http\Controllers\Tenant\PayrollController::class, 'index'])->name('index');
        Route::get('/create',      [\App\Http\Controllers\Tenant\PayrollController::class, 'create'])->name('create');
        Route::post('/',           [\App\Http\Controllers\Tenant\PayrollController::class, 'store'])->name('store');
        Route::get('/{id}',        [\App\Http\Controllers\Tenant\PayrollController::class, 'show'])->name('show');
        Route::post('/{id}/paid',  [\App\Http\Controllers\Tenant\PayrollController::class, 'markPaid'])->name('paid');
    });

    /* ── Purchase Orders ───────────────────────── */
    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/',            [\App\Http\Controllers\Tenant\PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create',      [\App\Http\Controllers\Tenant\PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/',           [\App\Http\Controllers\Tenant\PurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{id}',        [\App\Http\Controllers\Tenant\PurchaseOrderController::class, 'show'])->name('show');
        Route::put('/{id}/status', [\App\Http\Controllers\Tenant\PurchaseOrderController::class, 'updateStatus'])->name('status');
    });

    /* ── Vendors ───────────────────────────────── */
    Route::prefix('vendors')->name('vendors.')->group(function () {
        Route::get('/',            [\App\Http\Controllers\Tenant\VendorController::class, 'index'])->name('index');
        Route::post('/',           [\App\Http\Controllers\Tenant\VendorController::class, 'store'])->name('store');
        Route::put('/{id}',        [\App\Http\Controllers\Tenant\VendorController::class, 'update'])->name('update');
        Route::delete('/{id}',     [\App\Http\Controllers\Tenant\VendorController::class, 'destroy'])->name('destroy');
    });

    /* ── Inventory ─────────────────────────────── */
    Route::prefix('inventory-mgmt')->name('inventory-mgmt.')->group(function () {
        Route::get('/',                [\App\Http\Controllers\Tenant\InventoryController::class, 'index'])->name('index');
        Route::post('/',               [\App\Http\Controllers\Tenant\InventoryController::class, 'store'])->name('store');
        Route::get('/{id}',            [\App\Http\Controllers\Tenant\InventoryController::class, 'show'])->name('show');
        Route::put('/{id}',            [\App\Http\Controllers\Tenant\InventoryController::class, 'update'])->name('update');
        Route::post('/{id}/adjust',    [\App\Http\Controllers\Tenant\InventoryController::class, 'adjustStock'])->name('adjust');
    });

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

        // Meta Ads
        Route::get('/meta',                 [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'show'])->name('meta.show');
        Route::post('/meta/connect',        [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'connect'])->name('meta.connect');
        Route::get('/meta/callback',        [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'callback'])->name('meta.callback');
        Route::post('/meta/manual',         [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'manual'])->name('meta.manual');
        Route::post('/meta/sync',           [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'sync'])->name('meta.sync');
        Route::delete('/meta',              [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'disconnect'])->name('meta.disconnect');

        // Google Ads
        Route::get('/google-ads',           [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'show'])->name('google-ads.show');
        Route::post('/google-ads/connect',  [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'connect'])->name('google-ads.connect');
        Route::get('/google-ads/callback',  [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'callback'])->name('google-ads.callback');
        Route::post('/google-ads/manual',   [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'manual'])->name('google-ads.manual');
        Route::post('/google-ads/sync',     [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'sync'])->name('google-ads.sync');
        Route::delete('/google-ads',        [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'disconnect'])->name('google-ads.disconnect');
    });
});
