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

// Company registration — creates a Company (tenant) + owner user
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
