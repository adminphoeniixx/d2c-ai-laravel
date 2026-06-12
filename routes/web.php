<?php

use App\Http\Controllers\Auth\CompanyRegistrationController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public / Central Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/pricing',  [LandingController::class, 'pricing'])->name('pricing');
Route::get('/features', [LandingController::class, 'features'])->name('features');

// ── Shopify Compliance Webhooks (outside auth/CSRF)
Route::post('/shopify/webhooks/compliance',             [\App\Http\Controllers\ShopifyComplianceController::class, 'handle'])->middleware(\App\Http\Middleware\VerifyShopifyWebhook::class);
Route::post('/shopify/webhooks/customers/data_request', [\App\Http\Controllers\ShopifyComplianceController::class, 'customerDataRequest'])->middleware(\App\Http\Middleware\VerifyShopifyWebhook::class);
Route::post('/shopify/webhooks/customers/redact',       [\App\Http\Controllers\ShopifyComplianceController::class, 'customerRedact'])->middleware(\App\Http\Middleware\VerifyShopifyWebhook::class);
Route::post('/shopify/webhooks/shop/redact',            [\App\Http\Controllers\ShopifyComplianceController::class, 'shopRedact'])->middleware(\App\Http\Middleware\VerifyShopifyWebhook::class);
Route::post('/shopify/webhooks/customers/data-request', [\App\Http\Controllers\ShopifyComplianceController::class, 'customerDataRequest'])->middleware(\App\Http\Middleware\VerifyShopifyWebhook::class);

// ── Admin Login (separate from Fortify)
Route::middleware('guest')->group(function () {
    Route::get('/admin/login',  [\App\Http\Controllers\Auth\AdminLoginController::class, 'show'])->name('admin.login');
    Route::post('/admin/login', [\App\Http\Controllers\Auth\AdminLoginController::class, 'store'])->name('admin.login.store');
});
Route::post('/admin/logout', [\App\Http\Controllers\Auth\AdminLoginController::class, 'destroy'])
    ->middleware('auth')->name('admin.logout');

// ── Company OTP Login (replaces Fortify /login for companies)
Route::middleware('guest')->group(function () {
    Route::get('/login',             [\App\Http\Controllers\Auth\OtpLoginController::class, 'show'])->name('login');
    Route::get('/login/otp',         [\App\Http\Controllers\Auth\OtpLoginController::class, 'show'])->name('otp.login');
    Route::post('/login/otp/send',   [\App\Http\Controllers\Auth\OtpLoginController::class, 'send'])->name('otp.send');
    Route::post('/login/otp/verify', [\App\Http\Controllers\Auth\OtpLoginController::class, 'verify'])->name('otp.verify');
    Route::post('/login/otp/resend', [\App\Http\Controllers\Auth\OtpLoginController::class, 'resend'])->name('otp.resend');
});

// ── Company registration
Route::middleware('guest')->group(function () {
    Route::get('/register/company',  [CompanyRegistrationController::class, 'create'])->name('company.register');
    Route::post('/register/company', [CompanyRegistrationController::class, 'store'])->name('company.register.store');
});

// After login, Jetstream redirects here — we resolve the user's company and
// send them to their tenant dashboard.
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->get('/dashboard', function () {
    $user = request()->user();

    // Admins go to admin panel
    if ($user->is_admin) {
        return redirect()->route('admin.dashboard');
    }

    // Company users go to their tenant dashboard
    abort_unless($user->company, 403, 'No company associated with this user.');
    return redirect()->route('tenant.dashboard', ['tenant' => $user->company->slug]);
})->name('dashboard');

/*
|--------------------------------------------------------------------------
| Central OAuth Callbacks (not behind /app/{tenant})
|--------------------------------------------------------------------------
| Shopify doesn't support wildcard redirect URLs, so the callback hits a
| central route. We decode the company from the state parameter and redirect
| to the tenant context.
*/
Route::get('/integrations/shopify/callback', [\App\Http\Controllers\Integrations\ShopifyCentralCallbackController::class, 'handle'])
    ->name('integrations.shopify.callback.central');

/*
|--------------------------------------------------------------------------
| Legal Pages
|--------------------------------------------------------------------------
*/
Route::get('/privacy', fn () => \Inertia\Inertia::render('Legal/Privacy'))->name('privacy');
Route::get('/terms', fn () => \Inertia\Inertia::render('Legal/Terms'))->name('terms');
Route::get('/refund', fn () => \Inertia\Inertia::render('Legal/Refund'))->name('refund');
Route::get('/contact', fn () => \Inertia\Inertia::render('Legal/Contact'))->name('contact');
Route::get('/screencast', fn () => \Inertia\Inertia::render('Legal/Screencast'))->name('screencast');
