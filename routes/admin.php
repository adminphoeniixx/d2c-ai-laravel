<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IntegrationLogController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Panel Routes — /admin/...
|--------------------------------------------------------------------------
| Separate guard, separate Inertia layout. Protected by `admin` middleware
| which requires the authenticated user to have `is_admin = true`.
*/

Route::middleware(['auth', 'verified', 'admin'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Companies (tenants) management
    Route::resource('companies', CompanyController::class);
    Route::post('companies/{company}/destroy',        [CompanyController::class, 'destroy'])->name('companies.destroy.post');
    Route::post('companies/{company}/suspend',   [CompanyController::class, 'suspend'])->name('companies.suspend');
    Route::post('companies/{company}/activate',  [CompanyController::class, 'activate'])->name('companies.activate');
    Route::post('companies/{company}/impersonate', [CompanyController::class, 'impersonate'])->name('companies.impersonate');
    Route::post('companies/stop-impersonating',  [CompanyController::class, 'stopImpersonating'])->name('companies.stop-impersonating');

    // Users / admins
    Route::resource('users', UserController::class);

    // Roles & Permissions (Spatie)
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);

    // System health, queues, logs
    Route::get('system/health',        [SystemHealthController::class, 'index'])->name('system.health');
    Route::get('system/integrations',  [IntegrationLogController::class, 'index'])->name('system.integrations');
    Route::get('system/audit',         [AuditLogController::class, 'index'])->name('system.audit');

    // Admin profile / 2FA
    Route::get('/profile', fn() => inertia('Admin/Profile/Show'))->name('profile');

    // KYC Management
    Route::prefix('kyc')->name('kyc.')->group(function () {
        Route::get('/',              [\App\Http\Controllers\Admin\KycController::class, 'index'])->name('index');
        Route::post('/{id}/approve', [\App\Http\Controllers\Admin\KycController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject',  [\App\Http\Controllers\Admin\KycController::class, 'reject'])->name('reject');
    });
});

// ── Subscription Management
Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
    Route::get('/',                            [\App\Http\Controllers\Admin\SubscriptionController::class, 'dashboard'])->name('dashboard');
    Route::get('/plans',                       [\App\Http\Controllers\Admin\SubscriptionController::class, 'plans'])->name('plans');
    Route::put('/plans/{plan}',                [\App\Http\Controllers\Admin\SubscriptionController::class, 'updatePlan'])->name('plans.update');
    Route::get('/list',                        [\App\Http\Controllers\Admin\SubscriptionController::class, 'subscriptions'])->name('list');
    Route::post('/list/{subscription}/cancel', [\App\Http\Controllers\Admin\SubscriptionController::class, 'cancelSubscription'])->name('cancel');
    Route::get('/coupons',                     [\App\Http\Controllers\Admin\SubscriptionController::class, 'coupons'])->name('coupons');
    Route::post('/coupons',                    [\App\Http\Controllers\Admin\SubscriptionController::class, 'storeCoupon'])->name('coupons.store');
    Route::patch('/coupons/{coupon}',          [\App\Http\Controllers\Admin\SubscriptionController::class, 'updateCoupon'])->name('coupons.update');
    Route::delete('/coupons/{coupon}',         [\App\Http\Controllers\Admin\SubscriptionController::class, 'deleteCoupon'])->name('coupons.destroy');
    Route::get('/settings',                    [\App\Http\Controllers\Admin\SubscriptionController::class, 'settings'])->name('settings');
    Route::post('/settings',                   [\App\Http\Controllers\Admin\SubscriptionController::class, 'updateSettings'])->name('settings.update');
});

// ── Email Templates
Route::prefix('emails')->name('emails.')->group(function () {
    Route::get('/',                           [\App\Http\Controllers\Admin\EmailTemplateController::class, 'index'])->name('index');
    Route::put('/{emailTemplate}',            [\App\Http\Controllers\Admin\EmailTemplateController::class, 'update'])->name('update');
    Route::post('/settings',                  [\App\Http\Controllers\Admin\EmailTemplateController::class, 'updateSettings'])->name('settings');
    Route::get('/test-connection',            [\App\Http\Controllers\Admin\EmailTemplateController::class, 'testConnection'])->name('test-connection');
    Route::post('/{emailTemplate}/send-test', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'sendTest'])->name('send-test');
});

// ── Company extra routes
Route::post('companies/{company}/update-owner', [\App\Http\Controllers\Admin\CompanyController::class, 'updateOwner'])->name('companies.update-owner');
Route::post('companies/{company}/set-plan',     [\App\Http\Controllers\Admin\CompanyController::class, 'setPlan'])->name('companies.set-plan');
