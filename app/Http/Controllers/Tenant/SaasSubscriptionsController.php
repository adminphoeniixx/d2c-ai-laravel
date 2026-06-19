<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SaasSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaasSubscriptionsController extends Controller
{
    public const CATEGORIES = [
        'hosting'   => 'Hosting & Servers',
        'messaging' => 'WhatsApp / Messaging',
        'email'     => 'Email Services',
        'sms'       => 'SMS Services',
        'software'  => 'Software & Tools',
        'analytics' => 'Analytics & Monitoring',
        'other'     => 'Other',
    ];

    public function index(): Response
    {
        $subscriptions = SaasSubscription::query()
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
            ->orderBy('next_billing_date')
            ->get();

        $active = $subscriptions->where('status', 'active');

        $totals = [
            'monthly_total' => round((float) $active->sum(fn ($s) => $s->monthly_equivalent), 2),
            'yearly_total'  => round((float) $active->sum(fn ($s) => $s->monthly_equivalent) * 12, 2),
            'active_count'  => $active->count(),
            'by_category'   => $active->groupBy('category')
                ->map(fn ($group) => round((float) $group->sum(fn ($s) => $s->monthly_equivalent), 2))
                ->all(),
        ];

        return Inertia::render('Tenant/SaasSubscriptions', [
            'subscriptions' => $subscriptions,
            'totals'        => $totals,
            'categories'    => self::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);

        SaasSubscription::create($validated);

        return back()->with('success', 'Subscription added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $subscription = SaasSubscription::findOrFail($id);

        $validated = $this->validateData($request);

        $subscription->update($validated);

        return back()->with('success', 'Subscription updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        SaasSubscription::findOrFail($id)->delete();

        return back()->with('success', 'Subscription removed.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name'              => ['required', 'string', 'max:150'],
            'provider'          => ['nullable', 'string', 'max:150'],
            'category'          => ['required', 'string', 'in:' . implode(',', array_keys(self::CATEGORIES))],
            'amount'            => ['required', 'numeric', 'min:0'],
            'billing_cycle'     => ['required', 'string', 'in:monthly,yearly,one_time'],
            'next_billing_date' => ['nullable', 'date'],
            'status'            => ['required', 'string', 'in:active,paused,cancelled'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
