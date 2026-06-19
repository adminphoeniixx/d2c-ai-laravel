<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AdAnalyticsController;
use App\Http\Controllers\Tenant\AiCopilotController;
use App\Http\Controllers\Tenant\AiInsightsController;
use App\Http\Controllers\Tenant\CashFlowController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\ExpensesController;
use App\Http\Controllers\Tenant\Integrations\ShopifyController;
use App\Http\Controllers\Tenant\Integrations\WooCommerceController;
use App\Http\Controllers\Tenant\OrdersController;
use App\Http\Controllers\Tenant\PnLReportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\ExpenseUploadController;

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {

    Route::get('/',              [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/sync-all',     [DashboardController::class, 'syncAll'])->name('sync-all');
    Route::post('/extract-pdf',  [DashboardController::class, 'extractPdf'])->name('extract-pdf');
    Route::get('/p-and-l',       [PnLReportController::class, 'index'])->name('pnl');
    Route::get('/expenses/settings',  [\App\Http\Controllers\Tenant\ExpenseUploadController::class, 'getSettings'])->name('expenses.settings');
    Route::post('/expenses/settings', [\App\Http\Controllers\Tenant\ExpenseUploadController::class, 'updateSettings'])->name('expenses.settings.update');
    Route::get('/logistics/{slug}',    [\App\Http\Controllers\Tenant\LogisticsController::class, 'show'])->name('logistics.show');
    Route::post('/logistics/invoices', [\App\Http\Controllers\Tenant\LogisticsController::class, 'storeInvoice'])->name('logistics.invoices.store');
    Route::get('/expenses',              [ExpensesController::class, 'index'])->name('expenses');
    Route::post('/expenses',             [ExpensesController::class, 'store'])->name('expenses.store');
    Route::post('/expenses/extract',     [ExpenseUploadController::class, 'extract'])->name('expenses.extract');
    Route::post('/expenses/upload',      [ExpenseUploadController::class, 'store'])->name('expenses.upload.store');
    Route::delete('/expenses/{id}',      [ExpensesController::class, 'destroy'])->name('expenses.destroy');
    Route::get('/orders',              [OrdersController::class, 'index'])->name('orders');
    Route::post('/orders/auto-sync',   [OrdersController::class, 'autoSync'])->name('orders.auto-sync');
    Route::post('/orders/bulk-delete', [OrdersController::class, 'bulkDelete'])->name('orders.bulk-delete');
    Route::delete('/orders/{orderId}', [OrdersController::class, 'destroy'])->name('orders.destroy');
    Route::get('/orders/{order}',      [OrdersController::class, 'show'])->name('orders.show');
    Route::get('/ad-analytics',           [AdAnalyticsController::class, 'index'])->name('ads');
    Route::post('/ad-analytics/extract',  [AdAnalyticsController::class, 'extractPdf'])->name('ads.extract-pdf');
    Route::post('/ad-analytics/upload',   [AdAnalyticsController::class, 'uploadInvoice'])->name('ads.upload-invoice');
    Route::post('/ad-analytics/manual',   [AdAnalyticsController::class, 'addManualSpend'])->name('ads.manual-spend');
    Route::delete('/ad-analytics/{invoiceId}', [AdAnalyticsController::class, 'deleteInvoice'])->name('ads.delete-invoice');
    Route::get('/ad-analytics/invoice/{invoiceId}', [AdAnalyticsController::class, 'invoiceDetail'])->name('ads.invoice-detail');

    Route::post('/logistics/delhivery/connect',        [\App\Http\Controllers\Tenant\DelhiverySyncController::class, 'connect'])->name('logistics.delhivery.connect');
    Route::post('/logistics/delhivery/disconnect',     [\App\Http\Controllers\Tenant\DelhiverySyncController::class, 'disconnect'])->name('logistics.delhivery.disconnect');
    Route::get('/logistics/delhivery/status',          [\App\Http\Controllers\Tenant\DelhiverySyncController::class, 'status'])->name('logistics.delhivery.status');
    Route::post('/logistics/delhivery/sync',           [\App\Http\Controllers\Tenant\DelhiverySyncController::class, 'sync'])->name('logistics.delhivery.sync');
    Route::get('/logistics/delhivery/track/{waybill}', [\App\Http\Controllers\Tenant\DelhiverySyncController::class, 'track'])->name('logistics.delhivery.track');
    Route::post('/logistics/delhivery/pincode',        [\App\Http\Controllers\Tenant\DelhiverySyncController::class, 'checkPincode'])->name('logistics.delhivery.pincode');
    Route::post('/logistics/delhivery/cost',           [\App\Http\Controllers\Tenant\DelhiverySyncController::class, 'calculateCost'])->name('logistics.delhivery.cost');
    Route::post('/logistics/delhivery/import',         [\App\Http\Controllers\Tenant\DelhiverySyncController::class, 'importShipments'])->name('logistics.delhivery.import');
    Route::get('/logistics/delhivery/analytics',       [\App\Http\Controllers\Tenant\DelhiverySyncController::class, 'analytics'])->name('logistics.delhivery.analytics');
    Route::get('/logistics/delhivery/order-analytics', [\App\Http\Controllers\Tenant\DelhiveryOrderAnalyticsController::class, 'analytics'])->name('logistics.delhivery.order-analytics');

    Route::get('/gst',              [\App\Http\Controllers\Tenant\GSTController::class, 'index'])->name('gst');
    Route::get('/gst/export',       [\App\Http\Controllers\Tenant\GSTExportController::class, 'gstr1'])->name('gst.export');
    Route::post('/gst/recalculate', [\App\Http\Controllers\Tenant\GSTController::class, 'recalculate'])->name('gst.recalculate');
    Route::get('/orders/export',    [\App\Http\Controllers\Tenant\GSTExportController::class, 'ordersExport'])->name('orders.export');

    Route::get('/settings',      [\App\Http\Controllers\Tenant\CompanySettingsController::class, 'index'])->name('settings');
    Route::put('/settings',      [\App\Http\Controllers\Tenant\CompanySettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/letterhead',   [\App\Http\Controllers\Tenant\CompanySettingsController::class, 'uploadLetterhead'])->name('settings.letterhead.upload');
    Route::delete('/settings/letterhead', [\App\Http\Controllers\Tenant\CompanySettingsController::class, 'removeLetterhead'])->name('settings.letterhead.remove');

    Route::get('/inventory', fn () => redirect()->route('tenant.inventory-mgmt.index', ['tenant' => request()->route('tenant')]))->name('inventory');
    Route::get('/cashflow',  [CashFlowController::class, 'index'])->name('cashflow');

    Route::prefix('hr')->name('hr.')->group(function () {
        Route::get('/employees',             [\App\Http\Controllers\Tenant\EmployeeController::class, 'index'])->name('employees');
        Route::get('/employees/create',      [\App\Http\Controllers\Tenant\EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees',            [\App\Http\Controllers\Tenant\EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{id}',        [\App\Http\Controllers\Tenant\EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/employees/{id}/edit',   [\App\Http\Controllers\Tenant\EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{id}',        [\App\Http\Controllers\Tenant\EmployeeController::class, 'update'])->name('employees.update');
        Route::get('/templates',             [\App\Http\Controllers\Tenant\LetterController::class, 'templates'])->name('templates');
        Route::post('/templates',            [\App\Http\Controllers\Tenant\LetterController::class, 'storeTemplate'])->name('templates.store');
        Route::put('/templates/{id}',        [\App\Http\Controllers\Tenant\LetterController::class, 'updateTemplate'])->name('templates.update');
        Route::delete('/templates/{id}',     [\App\Http\Controllers\Tenant\LetterController::class, 'destroyTemplate'])->name('templates.destroy');
        Route::get('/letters/create',        [\App\Http\Controllers\Tenant\LetterController::class, 'create'])->name('letters.create');
        Route::post('/letters',              [\App\Http\Controllers\Tenant\LetterController::class, 'store'])->name('letters.store');
        Route::get('/letters/{id}',          [\App\Http\Controllers\Tenant\LetterController::class, 'show'])->name('letters.show');
        Route::put('/letters/{id}',          [\App\Http\Controllers\Tenant\LetterController::class, 'update'])->name('letters.update');
        Route::delete('/letters/{id}',       [\App\Http\Controllers\Tenant\LetterController::class, 'destroy'])->name('letters.destroy');
        Route::get('/attendance',                  [\App\Http\Controllers\Tenant\AttendanceController::class, 'index'])->name('attendance');
        Route::post('/attendance',                 [\App\Http\Controllers\Tenant\AttendanceController::class, 'store'])->name('attendance.store');
        Route::post('/attendance/bulk',            [\App\Http\Controllers\Tenant\AttendanceController::class, 'bulkStore'])->name('attendance.bulk');
        Route::put('/attendance/{attendance}',     [\App\Http\Controllers\Tenant\AttendanceController::class, 'update'])->name('attendance.update');
        Route::get('/attendance/late-report',      [\App\Http\Controllers\Tenant\AttendanceController::class, 'lateReport'])->name('attendance.late-report');
        Route::get('/attendance/settings',         [\App\Http\Controllers\Tenant\AttendanceSettingsController::class, 'index'])->name('attendance.settings');
        Route::put('/attendance/settings',         [\App\Http\Controllers\Tenant\AttendanceSettingsController::class, 'update'])->name('attendance.settings.update');
        Route::put('/attendance/schedule',         [\App\Http\Controllers\Tenant\AttendanceSettingsController::class, 'updateSchedule'])->name('attendance.schedule.update');
        Route::get('/holidays',                    [\App\Http\Controllers\Tenant\HolidayController::class, 'index'])->name('holidays');
        Route::post('/holidays',                   [\App\Http\Controllers\Tenant\HolidayController::class, 'store'])->name('holidays.store');
        Route::put('/holidays/{holiday}',          [\App\Http\Controllers\Tenant\HolidayController::class, 'update'])->name('holidays.update');
        Route::delete('/holidays/{holiday}',       [\App\Http\Controllers\Tenant\HolidayController::class, 'destroy'])->name('holidays.destroy');
        Route::get('/leaves/types',                [\App\Http\Controllers\Tenant\LeaveController::class, 'types'])->name('leaves.types');
        Route::post('/leaves/types',               [\App\Http\Controllers\Tenant\LeaveController::class, 'storeType'])->name('leaves.types.store');
        Route::put('/leaves/types/{leaveType}',    [\App\Http\Controllers\Tenant\LeaveController::class, 'updateType'])->name('leaves.types.update');
        Route::get('/leaves/requests',             [\App\Http\Controllers\Tenant\LeaveController::class, 'requests'])->name('leaves.requests');
        Route::post('/leaves/{leaveRequest}/approve', [\App\Http\Controllers\Tenant\LeaveController::class, 'approve'])->name('leaves.approve');
        Route::post('/leaves/{leaveRequest}/reject',  [\App\Http\Controllers\Tenant\LeaveController::class, 'reject'])->name('leaves.reject');
        Route::get('/leaves/balances',             [\App\Http\Controllers\Tenant\LeaveController::class, 'balances'])->name('leaves.balances');
        Route::post('/documents',                  [\App\Http\Controllers\Tenant\EmployeeDocumentController::class, 'store'])->name('documents.store');
        Route::delete('/documents/{id}',           [\App\Http\Controllers\Tenant\EmployeeDocumentController::class, 'destroy'])->name('documents.destroy');
        Route::get('/workers',               [\App\Http\Controllers\Tenant\WorkerController::class, 'index'])->name('workers');
        Route::get('/workers/create',        [\App\Http\Controllers\Tenant\WorkerController::class, 'create'])->name('workers.create');
        Route::post('/workers',              [\App\Http\Controllers\Tenant\WorkerController::class, 'store'])->name('workers.store');
        Route::get('/workers/{worker}',      [\App\Http\Controllers\Tenant\WorkerController::class, 'show'])->name('workers.show');
        Route::get('/workers/{worker}/edit', [\App\Http\Controllers\Tenant\WorkerController::class, 'edit'])->name('workers.edit');
        Route::put('/workers/{worker}',      [\App\Http\Controllers\Tenant\WorkerController::class, 'update'])->name('workers.update');
        Route::delete('/workers/{worker}',   [\App\Http\Controllers\Tenant\WorkerController::class, 'destroy'])->name('workers.destroy');
    });

    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/',           [\App\Http\Controllers\Tenant\PayrollController::class, 'index'])->name('index');
        Route::get('/create',     [\App\Http\Controllers\Tenant\PayrollController::class, 'create'])->name('create');
        Route::post('/',          [\App\Http\Controllers\Tenant\PayrollController::class, 'store'])->name('store');
        Route::get('/{id}',       [\App\Http\Controllers\Tenant\PayrollController::class, 'show'])->name('show');
        Route::post('/{id}/paid', [\App\Http\Controllers\Tenant\PayrollController::class, 'markPaid'])->name('paid');
    });

    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/',            [\App\Http\Controllers\Tenant\PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create',      [\App\Http\Controllers\Tenant\PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/',           [\App\Http\Controllers\Tenant\PurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{id}',        [\App\Http\Controllers\Tenant\PurchaseOrderController::class, 'show'])->name('show');
        Route::put('/{id}/status', [\App\Http\Controllers\Tenant\PurchaseOrderController::class, 'updateStatus'])->name('status');
    });

    Route::prefix('vendors')->name('vendors.')->group(function () {
        Route::get('/',        [\App\Http\Controllers\Tenant\VendorController::class, 'index'])->name('index');
        Route::post('/',       [\App\Http\Controllers\Tenant\VendorController::class, 'store'])->name('store');
        Route::put('/{id}',    [\App\Http\Controllers\Tenant\VendorController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Tenant\VendorController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('inventory-mgmt')->name('inventory-mgmt.')->group(function () {
        Route::get('/',             [\App\Http\Controllers\Tenant\InventoryController::class, 'index'])->name('index');
        Route::post('/',            [\App\Http\Controllers\Tenant\InventoryController::class, 'store'])->name('store');
        Route::get('/{id}',         [\App\Http\Controllers\Tenant\InventoryController::class, 'show'])->name('show');
        Route::put('/{id}',         [\App\Http\Controllers\Tenant\InventoryController::class, 'update'])->name('update');
        Route::post('/{id}/adjust', [\App\Http\Controllers\Tenant\InventoryController::class, 'adjustStock'])->name('adjust');
    });

    Route::get('/ai',         [AiCopilotController::class, 'index'])->name('ai');
    Route::post('/ai/prompt', [AiCopilotController::class, 'prompt'])->name('ai.prompt');
    Route::get('/ai/conversations/{id}',    [AiCopilotController::class, 'showConversation'])->name('ai.conversations.show');
    Route::delete('/ai/conversations/{id}', [AiCopilotController::class, 'destroyConversation'])->name('ai.conversations.destroy');

    Route::get('/ai-insights',          [AiInsightsController::class, 'index'])->name('ai-insights');
    Route::post('/ai-insights/refresh', [AiInsightsController::class, 'refresh'])->name('ai-insights.refresh');

    Route::prefix('saas-subscriptions')->name('saas-subscriptions.')->group(function () {
        Route::get('/',        [\App\Http\Controllers\Tenant\SaasSubscriptionsController::class, 'index'])->name('index');
        Route::post('/',       [\App\Http\Controllers\Tenant\SaasSubscriptionsController::class, 'store'])->name('store');
        Route::put('/{id}',    [\App\Http\Controllers\Tenant\SaasSubscriptionsController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Tenant\SaasSubscriptionsController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('integrations')->name('integrations.')->group(function () {
        Route::get('/shopify',             [ShopifyController::class, 'show'])->name('shopify.show');
        Route::post('/shopify/connect',    [ShopifyController::class, 'connect'])->name('shopify.connect');
        Route::get('/shopify/callback',    [ShopifyController::class, 'callback'])->name('shopify.callback');
        Route::post('/shopify/manual',     [ShopifyController::class, 'manual'])->name('shopify.manual');
        Route::post('/shopify/connect-client-credentials', [ShopifyController::class, 'connectClientCredentials'])->name('shopify.connect-client-credentials');
        Route::post('/shopify/migrate-token', [ShopifyController::class, 'migrateToken'])->name('shopify.migrate-token');
        Route::post('/shopify/sync',       [ShopifyController::class, 'sync'])->name('shopify.sync');
        Route::delete('/shopify',          [ShopifyController::class, 'disconnect'])->name('shopify.disconnect');
        Route::get('/woo',                 [WooCommerceController::class, 'show'])->name('woo.show');
        Route::post('/woo/connect',        [WooCommerceController::class, 'connect'])->name('woo.connect');
        Route::get('/woo/callback',        [WooCommerceController::class, 'callback'])->name('woo.callback');
        Route::post('/woo/manual',         [WooCommerceController::class, 'manual'])->name('woo.manual');
        Route::post('/woo/sync',           [WooCommerceController::class, 'sync'])->name('woo.sync');
        Route::delete('/woo',              [WooCommerceController::class, 'disconnect'])->name('woo.disconnect');
        Route::get('/meta',                [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'show'])->name('meta.show');
        Route::post('/meta/connect',       [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'connect'])->name('meta.connect');
        Route::get('/meta/callback',       [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'callback'])->name('meta.callback');
        Route::post('/meta/manual',        [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'manual'])->name('meta.manual');
        Route::post('/meta/sync',          [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'sync'])->name('meta.sync');
        Route::delete('/meta',             [\App\Http\Controllers\Tenant\Integrations\MetaAdsController::class, 'disconnect'])->name('meta.disconnect');
        Route::get('/google-ads',          [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'show'])->name('google-ads.show');
        Route::post('/google-ads/connect', [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'connect'])->name('google-ads.connect');
        Route::get('/google-ads/callback', [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'callback'])->name('google-ads.callback');
        Route::post('/google-ads/manual',  [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'manual'])->name('google-ads.manual');
        Route::post('/google-ads/sync',    [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'sync'])->name('google-ads.sync');
        Route::delete('/google-ads',       [\App\Http\Controllers\Tenant\Integrations\GoogleAdsController::class, 'disconnect'])->name('google-ads.disconnect');
    });

    Route::prefix('marketplaces')->name('marketplaces.')->group(function () {
        Route::get('/',                     [\App\Http\Controllers\Tenant\MarketplaceController::class, 'index'])->name('index');
        Route::post('/connect',             [\App\Http\Controllers\Tenant\MarketplaceController::class, 'connect'])->name('connect');
        Route::delete('/{marketplace}',     [\App\Http\Controllers\Tenant\MarketplaceController::class, 'disconnect'])->name('disconnect');
        Route::post('/{marketplace}/sync',  [\App\Http\Controllers\Tenant\MarketplaceController::class, 'sync'])->name('sync');
        Route::post('/import-csv',          [\App\Http\Controllers\Tenant\MarketplaceController::class, 'importCsv'])->name('import-csv');
        Route::get('/{marketplace}/orders', [\App\Http\Controllers\Tenant\MarketplaceController::class, 'orders'])->name('orders');
    });

    Route::prefix('logistics')->name('logistics.')->group(function () {
        Route::get('/',                                 [\App\Http\Controllers\Tenant\LogisticsController::class, 'index'])->name('index');
        Route::post('/upload',                          [\App\Http\Controllers\Tenant\LogisticsController::class, 'smartUpload'])->name('smart-upload');
        Route::get('/partner/{partnerId}',              [\App\Http\Controllers\Tenant\LogisticsController::class, 'show'])->name('partner');
        Route::post('/partner/{partnerId}/upload',      [\App\Http\Controllers\Tenant\LogisticsController::class, 'uploadInvoice'])->name('upload-invoice');
        Route::post('/partner/{partnerId}/connect-api', [\App\Http\Controllers\Tenant\LogisticsController::class, 'connectApi'])->name('connect-api');
        Route::delete('/partner/{partnerId}/api',       [\App\Http\Controllers\Tenant\LogisticsController::class, 'disconnectApi'])->name('disconnect-api');
        Route::post('/partner/{partnerId}/track',       [\App\Http\Controllers\Tenant\LogisticsController::class, 'trackShipment'])->name('track');
        Route::post('/partner/{partnerId}/sync',        [\App\Http\Controllers\Tenant\LogisticsController::class, 'syncTracking'])->name('sync-tracking');
        Route::post('/partner/{partnerId}/fetch',       [\App\Http\Controllers\Tenant\LogisticsController::class, 'fetchShipments'])->name('fetch-shipments');
        Route::get('/invoice/{invoiceId}',              [\App\Http\Controllers\Tenant\LogisticsController::class, 'invoiceDetail'])->name('invoice-detail');
        Route::delete('/invoice/{invoiceId}',           [\App\Http\Controllers\Tenant\LogisticsController::class, 'deleteInvoice'])->name('delete-invoice');
    });

    Route::prefix('banking')->name('banking.')->group(function () {
        Route::get('/',                             [\App\Http\Controllers\Tenant\BankingController::class, 'index'])->name('index');
        Route::post('/upload',                      [\App\Http\Controllers\Tenant\BankingController::class, 'smartUpload'])->name('smart-upload');
        Route::post('/accounts',                    [\App\Http\Controllers\Tenant\BankingController::class, 'createAccount'])->name('create-account');
        Route::delete('/accounts/{accountId}',      [\App\Http\Controllers\Tenant\BankingController::class, 'deleteAccount'])->name('delete-account');
        Route::get('/accounts/{accountId}',         [\App\Http\Controllers\Tenant\BankingController::class, 'ledger'])->name('ledger');
        Route::post('/accounts/{accountId}/upload', [\App\Http\Controllers\Tenant\BankingController::class, 'uploadStatement'])->name('upload-statement');
        Route::put('/transactions/{transactionId}', [\App\Http\Controllers\Tenant\BankingController::class, 'updateCategory'])->name('update-category');
    });

    Route::prefix('support')->name('support.')->group(function () {
        Route::get('/',                      [\App\Http\Controllers\Tenant\SupportController::class, 'index'])->name('index');
        Route::post('/',                     [\App\Http\Controllers\Tenant\SupportController::class, 'store'])->name('store');
        Route::get('/categories',            [\App\Http\Controllers\Tenant\SupportController::class, 'categories'])->name('categories');
        Route::post('/categories',           [\App\Http\Controllers\Tenant\SupportController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [\App\Http\Controllers\Tenant\SupportController::class, 'updateCategory'])->name('categories.update');
        Route::get('/faqs',                  [\App\Http\Controllers\Tenant\SupportController::class, 'faqs'])->name('faqs');
        Route::post('/faqs',                 [\App\Http\Controllers\Tenant\SupportController::class, 'storeFaq'])->name('faqs.store');
        Route::put('/faqs/{faq}',            [\App\Http\Controllers\Tenant\SupportController::class, 'updateFaq'])->name('faqs.update');
        Route::delete('/faqs/{faq}',         [\App\Http\Controllers\Tenant\SupportController::class, 'destroyFaq'])->name('faqs.destroy');
        Route::get('/{ticket}',              [\App\Http\Controllers\Tenant\SupportController::class, 'show'])->name('show');
        Route::post('/{ticket}/reply',       [\App\Http\Controllers\Tenant\SupportController::class, 'reply'])->name('reply');
        Route::put('/{ticket}/status',       [\App\Http\Controllers\Tenant\SupportController::class, 'updateStatus'])->name('update-status');
    });

    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/plans',                 [\App\Http\Controllers\Tenant\SubscriptionController::class, 'plans'])->name('plans');
        Route::post('/order',                [\App\Http\Controllers\Tenant\SubscriptionController::class, 'createOrder'])->name('order');
        Route::post('/verify',               [\App\Http\Controllers\Tenant\SubscriptionController::class, 'verifyPayment'])->name('verify');
        Route::post('/coupon',               [\App\Http\Controllers\Tenant\SubscriptionController::class, 'applyCoupon'])->name('coupon');
        Route::get('/status',                [\App\Http\Controllers\Tenant\SubscriptionController::class, 'limitStatus'])->name('status');
        Route::get('/invoice/{id}/download', [\App\Http\Controllers\Tenant\SubscriptionController::class, 'downloadInvoice'])->name('invoice.download');
    });

    Route::get('/kyc',         [\App\Http\Controllers\Tenant\KycController::class, 'index'])->name('kyc');
    Route::post('/kyc/submit', [\App\Http\Controllers\Tenant\KycController::class, 'submit'])->name('kyc.submit');

    Route::prefix('payment-gateway')->name('pg.')->group(function () {
        Route::get('/',        [\App\Http\Controllers\Tenant\PaymentGatewayController::class, 'index'])->name('index');
        Route::post('/upload', [\App\Http\Controllers\Tenant\PaymentGatewayController::class, 'upload'])->name('upload');
        Route::get('/{id}',    [\App\Http\Controllers\Tenant\PaymentGatewayController::class, 'show'])->name('show');
        Route::delete('/{id}', [\App\Http\Controllers\Tenant\PaymentGatewayController::class, 'destroy'])->name('destroy');
    });

    Route::get('/profile', fn() => inertia('Tenant/Profile/Show'))->name('profile');

    Route::prefix('team')->name('team.')->group(function () {
        Route::get('/',                            [\App\Http\Controllers\Tenant\TeamController::class, 'index'])->name('index');
        Route::post('/invite',                     [\App\Http\Controllers\Tenant\TeamController::class, 'invite'])->name('invite');
        Route::delete('/invitations/{invitation}', [\App\Http\Controllers\Tenant\TeamController::class, 'cancelInvite'])->name('cancel-invite');
        Route::put('/members/{userId}/role',       [\App\Http\Controllers\Tenant\TeamController::class, 'updateRole'])->name('update-role');
        Route::delete('/members/{userId}',         [\App\Http\Controllers\Tenant\TeamController::class, 'removeMember'])->name('remove');
    });

});
