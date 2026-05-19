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
});
