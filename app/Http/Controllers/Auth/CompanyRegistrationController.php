<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanyRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/RegisterCompany');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name'      => ['required', 'string', 'max:120'],
            'slug'              => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', Rule::unique('companies', 'slug')],
            'name'              => ['required', 'string', 'max:120'],
            'email'             => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
            'phone'             => ['required', 'string', 'min:10'],
            'password'          => ['required', 'string', 'min:8', 'confirmed'],
            'gstin'             => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'business_category' => ['nullable', 'string', 'in:apparel,footwear,electronics,beauty,food,luxury,other'],
            'country'           => ['nullable', 'string', 'size:2'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'timezone'          => ['nullable', 'string', 'max:64'],
            'terms'             => ['accepted'],
        ]);

        // Verify phone was OTP-verified
        $phone = preg_replace('/[^0-9]/', '', $validated['phone']);
        if (strlen($phone) > 10 && str_starts_with($phone, '91')) {
            $phone = substr($phone, -10);
        }
        // Normalize to last 10 digits for cache lookup
        $phone10 = substr($phone, -10);
        if (!\Illuminate\Support\Facades\Cache::get("reg_verified:{$phone10}") &&
            !\Illuminate\Support\Facades\Cache::get("reg_verified:{$phone}")) {
            return back()->withErrors(['phone' => 'Phone number not verified. Please verify with OTP first.']);
        }
        \Illuminate\Support\Facades\Cache::forget("reg_verified:{$phone10}");
        \Illuminate\Support\Facades\Cache::forget("reg_verified:{$phone}");
        // Store with +91 prefix for consistency
        $phone = '+91' . $phone10;

        // Auto-detect state from GSTIN and set default GST rate from category
        $registeredStateCode = null;
        $defaultGstRate = 18.0;
        $category = $validated['business_category'] ?? 'other';

        if (!empty($validated['gstin'])) {
            $registeredStateCode = \App\Services\GST\StateCodeMap::stateCodeFromGstin($validated['gstin']);
        }

        $categoryRates = \App\Services\GST\GSTCalculator::CATEGORY_RATES[$category] ?? null;
        if ($categoryRates) {
            $defaultGstRate = $categoryRates['flat'] ?? $categoryRates['below'] ?? 18.0;
        }

        /** @var Company $company */
        $company = Company::create([
            'slug'                  => $validated['slug'],
            'name'                  => $validated['company_name'],
            'email'                 => $validated['email'],
            'gstin'                 => $validated['gstin'] ?? null,
            'registered_state_code' => $registeredStateCode,
            'business_category'     => $category,
            'default_gst_rate'      => $defaultGstRate,
            'country'               => $validated['country'] ?? 'IN',
            'currency'              => $validated['currency'] ?? 'INR',
            'timezone'              => $validated['timezone'] ?? 'Asia/Kolkata',
            'status'                => Company::STATUS_ACTIVE,
            'plan'                  => Company::PLAN_FREE,
            'trial_ends_at'         => now()->addDays(14),
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'phone'      => $phone,
            'password'   => Hash::make($validated['password']),
        ]);
        $user->assignRole('owner');

        // Manually provision tenant schema + run migrations
        // This is more reliable than the event pipeline for synchronous execution
        try {
            $company->database()->manager()->createDatabase($company);
        } catch (\Throwable $e) {
            // Schema may already exist from event pipeline — that's fine
        }

        try {
            tenancy()->initialize($company);
            Artisan::call('migrate', [
                '--path'     => database_path('migrations/tenant'),
                '--force'    => true,
                '--realpath' => true,
            ]);
            tenancy()->end();
        } catch (\Throwable $e) {
            // Log the error but don't block registration
            logger()->error('Tenant migration failed for ' . $company->slug . ': ' . $e->getMessage());
            try { tenancy()->end(); } catch (\Throwable $x) {}
        }

        Auth::login($user);

        return redirect()
            ->route('tenant.dashboard', ['tenant' => $company->slug])
            ->with('success', 'Workspace created. Welcome to heyd2c 👋');
    }
}
