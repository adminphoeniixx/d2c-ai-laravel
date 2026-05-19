<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\GST\GSTCalculator;
use App\Services\GST\StateCodeMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function index(): Response
    {
        $company = app('current_company');

        return Inertia::render('Tenant/Settings', [
            'settings' => [
                'name'                  => $company->name,
                'email'                 => $company->email,
                'gstin'                 => $company->gstin,
                'registered_state_code' => $company->registered_state_code,
                'registered_state_name' => $company->registered_state_code
                    ? StateCodeMap::stateName($company->registered_state_code)
                    : null,
                'business_category'     => $company->business_category ?? 'other',
                'default_gst_rate'      => $company->default_gst_rate ?? 18.0,
                'country'               => $company->country,
                'currency'              => $company->currency,
                'timezone'              => $company->timezone,
            ],
            'categories'  => array_keys(GSTCalculator::CATEGORY_RATES),
            'stateMap'    => collect(StateCodeMap::STATES)->map(fn ($s, $code) => [
                'code' => $code,
                'name' => $s['name'],
            ])->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = app('current_company');

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:120'],
            'email'             => ['required', 'email', 'max:180'],
            'gstin'             => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'business_category' => ['nullable', 'string', 'in:apparel,footwear,electronics,beauty,food,luxury,other'],
            'default_gst_rate'  => ['nullable', 'numeric', 'min:0', 'max:40'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'timezone'          => ['nullable', 'string', 'max:64'],
        ]);

        // Auto-detect state from GSTIN
        $registeredStateCode = null;
        if (!empty($validated['gstin'])) {
            $registeredStateCode = StateCodeMap::stateCodeFromGstin($validated['gstin']);
        }

        // Auto-set GST rate from category if not explicitly provided
        $category = $validated['business_category'] ?? $company->business_category ?? 'other';
        $categoryRates = GSTCalculator::CATEGORY_RATES[$category] ?? null;
        $defaultRate = $validated['default_gst_rate']
            ?? ($categoryRates ? ($categoryRates['flat'] ?? $categoryRates['below'] ?? 18.0) : 18.0);

        $company->update([
            'name'                  => $validated['name'],
            'email'                 => $validated['email'],
            'gstin'                 => $validated['gstin'] ?? null,
            'registered_state_code' => $registeredStateCode,
            'business_category'     => $category,
            'default_gst_rate'      => $defaultRate,
            'currency'              => $validated['currency'] ?? $company->currency,
            'timezone'              => $validated['timezone'] ?? $company->timezone,
        ]);

        return back()->with('success', 'Company settings updated.');
    }
}
