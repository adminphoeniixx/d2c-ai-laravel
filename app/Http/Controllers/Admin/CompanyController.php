<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
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
                $qq->where('name', 'ilike', $needle)
                   ->orWhere('slug', 'ilike', $needle)
                   ->orWhere('email', 'ilike', $needle)
                   ->orWhere('phone', 'ilike', $needle);
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('plan'),   fn ($q) => $q->where('plan',   $request->input('plan')))
            ->when($request->filled('category'), fn ($q) => $q->where('business_category', $request->input('category')))
            ->withCount('users')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
            'filters'   => $request->only(['q', 'status', 'plan', 'category']),
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

        $company->run(function () {
            \Artisan::call('migrate', [
                '--path'     => 'database/migrations/tenant',
                '--realpath' => false,
                '--force'    => true,
            ]);
        });

        return redirect()->route('admin.companies.show', $company)->with('success', 'Company created.');
    }

    public function show(Company $company): Response
    {
        $company->loadCount('users');

        $subscription = Subscription::where('company_id', $company->id)
            ->whereIn('status', ['active', 'trial'])
            ->with('plan')
            ->latest()
            ->first();

        return Inertia::render('Admin/Companies/Show', [
            'company'      => $company,
            'users'        => $company->users()->latest()->get(['id', 'name', 'email', 'phone', 'created_at']),
            'subscription' => $subscription,
            'plans'        => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:120'],
            'email'             => ['nullable', 'email', 'max:180'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'plan'              => ['required', 'string'],
            'status'            => ['required', Rule::in(['active', 'suspended', 'pending'])],
            'country'           => ['required', 'string', 'size:2'],
            'currency'          => ['required', 'string', 'size:3'],
            'timezone'          => ['required', 'string', 'max:64'],
            'business_category' => ['nullable', 'string', 'max:60'],
            'gstin'             => ['nullable', 'string', 'max:15'],
            'subscription_status' => ['nullable', 'string'],
            'order_count'       => ['nullable', 'integer', 'min:0'],
        ]);

        // Handle suspension timestamp
        if ($validated['status'] === 'suspended' && $company->status !== 'suspended') {
            $validated['suspended_at'] = now();
        } elseif ($validated['status'] === 'active') {
            $validated['suspended_at'] = null;
        }

        $company->update($validated);

        return back()->with('success', 'Company updated.');
    }

    // Update owner user details (name, email, phone)
    public function updateOwner(Request $request, Company $company): RedirectResponse
    {
        $user = $company->users()->first();
        abort_unless($user, 404, 'No users found for this company.');

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->update($validated);

        return back()->with('success', 'Owner details updated.');
    }

    // Force set subscription plan
    public function setPlan(Request $request, Company $company): RedirectResponse
    {
        $request->validate(['plan_id' => 'required|exists:subscription_plans,id']);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        // Cancel existing
        Subscription::where('company_id', $company->id)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        if (!$plan->is_free) {
            Subscription::create([
                'company_id'      => $company->id,
                'plan_id'         => $plan->id,
                'status'          => 'active',
                'billing_cycle'   => 'monthly',
                'amount'          => $plan->price,
                'discount_amount' => 0,
                'tax_amount'      => 0,
                'final_amount'    => $plan->price,
                'starts_at'       => now(),
                'ends_at'         => now()->addMonth(),
                'metadata'        => ['set_by_admin' => true],
            ]);
        }

        $company->update([
            'plan'                => $plan->slug,
            'active_plan_id'      => $plan->id,
            'subscription_status' => $plan->is_free ? 'free' : 'active',
        ]);

        return back()->with('success', "Plan changed to {$plan->name}.");
    }

    public function destroy(Company $company): RedirectResponse
    {
        // Delete all users belonging to this company first
        \App\Models\User::where('company_id', $company->id)->delete();

        $company->delete();

        return redirect()->route('admin.companies.index')->with('success', 'Company and all associated users deleted.');
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
