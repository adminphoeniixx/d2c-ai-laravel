<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $companies = Company::query()
            ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where(function ($qq) use ($request) {
                $needle = '%'.$request->string('q').'%';
                $qq->where('name', 'ilike', $needle)->orWhere('slug', 'ilike', $needle)->orWhere('email', 'ilike', $needle);
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('plan'), fn ($q) => $q->where('plan', $request->input('plan')))
            ->withCount('users')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
            'filters'   => $request->only(['q', 'status', 'plan']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Companies/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'slug'     => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', Rule::unique('companies', 'slug')],
            'email'    => ['nullable', 'email'],
            'plan'     => ['required', Rule::in([Company::PLAN_FREE, Company::PLAN_PRO, Company::PLAN_ENTERPRISE])],
            'country'  => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $company = Company::create($validated);

        // Provision schema
        $company->run(function () {
            \Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ]);
        });

        return redirect()->route('admin.companies.show', $company)->with('success', 'Company created.');
    }

    public function show(Company $company): Response
    {
        $company->loadCount('users');

        return Inertia::render('Admin/Companies/Show', [
            'company' => $company,
            'users'   => $company->users()->latest()->limit(20)->get(),
        ]);
    }

    public function edit(Company $company): Response
    {
        return Inertia::render('Admin/Companies/Edit', ['company' => $company]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['nullable', 'email'],
            'plan'     => ['required', Rule::in([Company::PLAN_FREE, Company::PLAN_PRO, Company::PLAN_ENTERPRISE])],
            'country'  => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $company->update($validated);

        return back()->with('success', 'Company updated.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete(); // cascades: drops tenant DB via HasDatabase observer
        return redirect()->route('admin.companies.index')->with('success', 'Company deleted.');
    }

    public function suspend(Company $company): RedirectResponse
    {
        $company->update(['status' => Company::STATUS_SUSPENDED, 'suspended_at' => now()]);
        return back()->with('success', 'Company suspended.');
    }

    public function activate(Company $company): RedirectResponse
    {
        $company->update(['status' => Company::STATUS_ACTIVE, 'suspended_at' => null]);
        return back()->with('success', 'Company reactivated.');
    }

    /** Impersonate a company owner for support debugging. */
    public function impersonate(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $target = $company->users()->whereHas('roles', fn ($q) => $q->where('name', 'owner'))->first()
               ?? $company->users()->first();

        abort_unless($target, 404, 'Company has no users.');

        session(['impersonator_id' => $request->user()->id]);
        auth()->login($target);

        return redirect()->route('tenant.dashboard', ['tenant' => $company->slug]);
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $impersonatorId = session()->pull('impersonator_id');
        abort_unless($impersonatorId, 400);

        auth()->loginUsingId((int) $impersonatorId);

        return redirect()->route('admin.dashboard')->with('success', 'Stopped impersonating.');
    }
}
