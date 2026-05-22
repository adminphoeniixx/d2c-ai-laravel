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
                'letterhead_url'        => $company->letterhead_url ?? null,
                // PF & ESI
                'pf_enabled'               => $company->pf_enabled ?? true,
                'pf_employee_rate'         => $company->pf_employee_rate ?? 12.00,
                'pf_employer_rate'         => $company->pf_employer_rate ?? 12.00,
                'pf_basic_cap'             => $company->pf_basic_cap ?? 15000.00,
                'pf_establishment_code'    => $company->pf_establishment_code,
                'esi_enabled'              => $company->esi_enabled ?? true,
                'esi_employee_rate'        => $company->esi_employee_rate ?? 0.75,
                'esi_employer_rate'        => $company->esi_employer_rate ?? 3.25,
                'esi_gross_threshold'      => $company->esi_gross_threshold ?? 21000.00,
                'esi_establishment_code'   => $company->esi_establishment_code,
                'pt_amount'                => $company->pt_amount ?? 200.00,
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
            // PF
            'pf_enabled'              => ['nullable', 'boolean'],
            'pf_employee_rate'        => ['nullable', 'numeric', 'min:0', 'max:25'],
            'pf_employer_rate'        => ['nullable', 'numeric', 'min:0', 'max:25'],
            'pf_basic_cap'            => ['nullable', 'numeric', 'min:0'],
            'pf_establishment_code'   => ['nullable', 'string', 'max:30'],
            // ESI
            'esi_enabled'             => ['nullable', 'boolean'],
            'esi_employee_rate'       => ['nullable', 'numeric', 'min:0', 'max:10'],
            'esi_employer_rate'       => ['nullable', 'numeric', 'min:0', 'max:10'],
            'esi_gross_threshold'     => ['nullable', 'numeric', 'min:0'],
            'esi_establishment_code'  => ['nullable', 'string', 'max:30'],
            // PT
            'pt_amount'               => ['nullable', 'numeric', 'min:0'],
        ]);

        // Auto-detect state from GSTIN
        $registeredStateCode = null;
        if (!empty($validated['gstin'])) {
            $registeredStateCode = StateCodeMap::stateCodeFromGstin($validated['gstin']);
        }

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
            // PF & ESI
            'pf_enabled'              => $validated['pf_enabled'] ?? $company->pf_enabled,
            'pf_employee_rate'        => $validated['pf_employee_rate'] ?? $company->pf_employee_rate,
            'pf_employer_rate'        => $validated['pf_employer_rate'] ?? $company->pf_employer_rate,
            'pf_basic_cap'            => $validated['pf_basic_cap'] ?? $company->pf_basic_cap,
            'pf_establishment_code'   => $validated['pf_establishment_code'] ?? $company->pf_establishment_code,
            'esi_enabled'             => $validated['esi_enabled'] ?? $company->esi_enabled,
            'esi_employee_rate'       => $validated['esi_employee_rate'] ?? $company->esi_employee_rate,
            'esi_employer_rate'       => $validated['esi_employer_rate'] ?? $company->esi_employer_rate,
            'esi_gross_threshold'     => $validated['esi_gross_threshold'] ?? $company->esi_gross_threshold,
            'esi_establishment_code'  => $validated['esi_establishment_code'] ?? $company->esi_establishment_code,
            'pt_amount'               => $validated['pt_amount'] ?? $company->pt_amount,
        ]);

        return back()->with('success', 'Company settings updated.');
    }

    public function uploadLetterhead(Request $request): RedirectResponse
    {
        $request->validate([
            'letterhead' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $company = app('current_company');
        $bunny = new \App\Services\BunnyCDN();

        // Delete old letterhead if exists
        if ($company->letterhead_url) {
            $bunny->delete($company->letterhead_url);
        }

        $url = $bunny->upload(
            $request->file('letterhead'),
            'letterheads/' . $company->id
        );

        $company->update(['letterhead_url' => $url]);

        return back()->with('success', 'Letterhead uploaded.');
    }

    public function removeLetterhead(): RedirectResponse
    {
        $company = app('current_company');
        $bunny = new \App\Services\BunnyCDN();
        $bunny->delete($company->letterhead_url ?? '');
        $company->update(['letterhead_url' => null]);
        return back()->with('success', 'Letterhead removed.');
    }
}
