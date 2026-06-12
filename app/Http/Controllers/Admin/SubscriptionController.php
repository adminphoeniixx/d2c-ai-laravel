<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\PaymentSetting;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    // ── Dashboard ─────────────────────────────────────────
    public function dashboard()
    {
        $now = now();

        // Revenue stats
        $mrr = Subscription::where('status', 'active')
            ->where('billing_cycle', 'monthly')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->sum('subscription_plans.price');

        $arr = Subscription::where('status', 'active')
            ->where('billing_cycle', 'yearly')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->sum('subscription_plans.price_yearly');

        $totalRevenue = SubscriptionInvoice::where('status', 'paid')->sum('total');
        $totalTax     = SubscriptionInvoice::where('status', 'paid')
            ->selectRaw('SUM(cgst + sgst + igst) as tax')->value('tax') ?? 0;

        // This month
        $monthRevenue = SubscriptionInvoice::where('status', 'paid')
            ->whereBetween('paid_at', [$now->startOfMonth()->copy(), $now->endOfMonth()->copy()])
            ->sum('total');

        // Plan distribution
        $planDist = Subscription::where('status', 'active')
            ->selectRaw('plan_id, COUNT(*) as count')
            ->with('plan:id,name,slug')
            ->groupBy('plan_id')
            ->get()
            ->map(fn($s) => ['plan' => $s->plan?->name, 'count' => $s->count]);

        // Category analysis
        $categoryAnalysis = Company::whereNotNull('business_category')
            ->selectRaw('business_category, COUNT(*) as total, 
                SUM(CASE WHEN subscription_status = \'active\' THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN subscription_status = \'free\' THEN 1 ELSE 0 END) as free_count')
            ->groupBy('business_category')
            ->orderByDesc('total')
            ->get();

        // Monthly revenue trend (last 6 months)
        $monthlyRevenue = SubscriptionInvoice::where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(6))
            ->selectRaw("DATE_TRUNC('month', paid_at) as month, SUM(total) as revenue, SUM(cgst+sgst+igst) as tax, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Active/inactive counts
        $stats = [
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'total_companies'      => Company::count(),
            'free_companies'       => Company::where('subscription_status', 'free')->count(),
            'churn_this_month'     => Subscription::where('status', 'cancelled')
                ->whereMonth('cancelled_at', $now->month)->count(),
        ];

        return Inertia::render('Admin/Subscriptions/Dashboard', [
            'mrr'             => round((float) $mrr, 0),
            'arr'             => round((float) $arr + ((float) $mrr * 12), 0),
            'total_revenue'   => round((float) $totalRevenue, 0),
            'total_tax'       => round((float) $totalTax, 0),
            'month_revenue'   => round((float) $monthRevenue, 0),
            'plan_dist'       => $planDist,
            'category_analysis' => $categoryAnalysis,
            'monthly_revenue' => $monthlyRevenue,
            'stats'           => $stats,
        ]);
    }

    // ── Plans CRUD ────────────────────────────────────────
    public function plans()
    {
        return Inertia::render('Admin/Subscriptions/Plans', [
            'plans' => SubscriptionPlan::orderBy('sort_order')->withCount('subscriptions')->get(),
        ]);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:60',
            'description'       => 'nullable|string',
            'price'             => 'required|numeric|min:0',
            'price_yearly'      => 'required|numeric|min:0',
            'order_limit'       => 'required|integer|min:-1',
            'store_limit'       => 'required|integer|min:-1',
            'team_member_limit' => 'required|integer|min:-1',
            'data_history_days' => 'required|integer|min:-1',
            'per_order_charge'  => 'required|numeric|min:0',
            'is_active'         => 'boolean',
            'is_featured'       => 'boolean',
            'razorpay_plan_id'  => 'nullable|string',
            'razorpay_plan_id_test' => 'nullable|string',
        ]);

        $plan->update($data);
        return back()->with('success', 'Plan updated.');
    }

    // ── Active Subscriptions ──────────────────────────────
    public function subscriptions(Request $request)
    {
        $query = Subscription::with(['plan', 'company'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) => $q->whereHas('company', fn($q) =>
                $q->where('name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%")
            ))
            ->latest();

        return Inertia::render('Admin/Subscriptions/List', [
            'subscriptions' => $query->paginate(30)->withQueryString(),
            'filters'       => $request->only('status', 'search'),
            'stats' => [
                'active'    => Subscription::where('status', 'active')->count(),
                'trial'     => Subscription::where('status', 'trial')->count(),
                'cancelled' => Subscription::where('status', 'cancelled')->count(),
                'expired'   => Subscription::where('status', 'expired')->count(),
            ],
        ]);
    }

    public function cancelSubscription(Subscription $subscription)
    {
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $subscription->company?->update(['subscription_status' => 'free', 'plan' => 'free']);
        return back()->with('success', 'Subscription cancelled.');
    }

    // ── Coupons ───────────────────────────────────────────
    public function coupons()
    {
        return Inertia::render('Admin/Subscriptions/Coupons', [
            'coupons' => Coupon::withCount('usages')->latest()->paginate(30),
            'plans'   => SubscriptionPlan::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function storeCoupon(Request $request)
    {
        $data = $request->validate([
            'code'              => 'required|string|max:20|unique:coupons,code',
            'type'              => 'required|in:percent,flat',
            'value'             => 'required|numeric|min:1',
            'max_discount'      => 'nullable|numeric|min:0',
            'usage_limit'       => 'nullable|integer|min:1',
            'per_user_limit'    => 'required|integer|min:1',
            'is_active'         => 'boolean',
            'first_time_only'   => 'boolean',
            'applicable_plans'  => 'nullable|array',
            'valid_from'        => 'nullable|date',
            'valid_until'       => 'nullable|date|after:valid_from',
        ]);

        $data['code'] = strtoupper($data['code']);
        Coupon::create($data);
        return back()->with('success', 'Coupon created.');
    }

    public function updateCoupon(Request $request, Coupon $coupon)
    {
        $data = $request->validate([
            'is_active'   => 'boolean',
            'valid_until' => 'nullable|date',
            'usage_limit' => 'nullable|integer|min:1',
        ]);
        $coupon->update($data);
        return back()->with('success', 'Coupon updated.');
    }

    public function deleteCoupon(Coupon $coupon)
    {
        $coupon->delete();
        return back()->with('success', 'Coupon deleted.');
    }

    // ── Payment Settings ──────────────────────────────────
    public function settings()
    {
        return Inertia::render('Admin/Subscriptions/Settings', [
            'settings' => [
                'razorpay_mode'            => PaymentSetting::getValue('razorpay_mode', 'test'),
                'razorpay_key_id_test'     => PaymentSetting::getValue('razorpay_key_id_test', ''),
                'razorpay_key_secret_test' => PaymentSetting::getValue('razorpay_key_secret_test', ''),
                'razorpay_key_id_live'     => PaymentSetting::getValue('razorpay_key_id_live', ''),
                'razorpay_key_secret_live' => PaymentSetting::getValue('razorpay_key_secret_live', ''),
                'gst_rate'                 => PaymentSetting::getValue('gst_rate', '18'),
                'grace_period_days'        => PaymentSetting::getValue('grace_period_days', '7'),
                'limit_warning_pct'        => PaymentSetting::getValue('limit_warning_pct', '90'),
                'grace_email_day_1'        => PaymentSetting::getValue('grace_email_day_1', '1'),
                'grace_email_day_3'        => PaymentSetting::getValue('grace_email_day_3', '1'),
                'grace_email_day_7'        => PaymentSetting::getValue('grace_email_day_7', '1'),
                'kyc_required'             => PaymentSetting::getValue('kyc_required', '0'),
            ],
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'razorpay_mode'            => 'required|in:test,live',
            'razorpay_key_id_test'     => 'nullable|string',
            'razorpay_key_secret_test' => 'nullable|string',
            'razorpay_key_id_live'     => 'nullable|string',
            'razorpay_key_secret_live' => 'nullable|string',
            'gst_rate'                 => 'required|numeric|min:0|max:28',
            'grace_period_days'        => 'required|integer|min:0|max:90',
            'limit_warning_pct'        => 'required|integer|min:50|max:99',
            'grace_email_day_1'        => 'nullable',
            'grace_email_day_3'        => 'nullable',
            'grace_email_day_7'        => 'nullable',
            'kyc_required'             => 'nullable',
        ]);

        // Save each setting, converting booleans/nulls properly
        foreach ($data as $key => $value) {
            if (in_array($key, ['grace_email_day_1', 'grace_email_day_3', 'grace_email_day_7'])) {
                PaymentSetting::setValue($key, ($value && $value !== '0') ? '1' : '0');
            } else {
                PaymentSetting::setValue($key, (string) ($value ?? ''));
            }
        }

        // Clear file cache so new values take effect immediately
        \Illuminate\Support\Facades\Cache::store('file')->flush();

        return back()->with('success', 'Settings saved.');
    }
}
