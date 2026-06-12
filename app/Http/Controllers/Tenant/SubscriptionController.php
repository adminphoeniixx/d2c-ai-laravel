<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $svc) {}

    // Plans page — shown to tenant
    public function plans(Request $request, string $tenant)
    {
        $company     = Auth::user()->company;
        $current     = $this->svc->getCurrentPlan($company);
        $limitStatus = $this->svc->getLimitStatus($company);

        $activeSub = $this->svc->getActiveSubscription($company);

        $invoices = \App\Models\SubscriptionInvoice::where('company_id', $company->id)
            ->orderByDesc('paid_at')
            ->limit(12)
            ->get(['id', 'invoice_number', 'subtotal', 'cgst', 'sgst', 'igst', 'total', 'status', 'paid_at'])
            ->map(fn($i) => [
                'id'             => $i->id,
                'invoice_number' => $i->invoice_number,
                'subtotal'       => (float) ($i->subtotal ?? 0),
                'cgst'           => (float) ($i->cgst ?? 0),
                'sgst'           => (float) ($i->sgst ?? 0),
                'igst'           => (float) ($i->igst ?? 0),
                'total'          => (float) ($i->total ?? 0),
                'status'         => $i->status,
                'paid_at'        => $i->paid_at,
            ]);

        return Inertia::render('Tenant/Subscription/Plans', [
            'plans'        => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(),
            'current_plan' => $current,
            'active_sub'   => $activeSub,
            'limit_status' => $limitStatus,
            'invoices'     => $invoices,
            'razorpay_key' => PaymentSetting::getValue(
                'razorpay_key_id_' . PaymentSetting::getValue('razorpay_mode', 'test'), ''
            ),
        ]);
    }

    // Create Razorpay order before checkout
    public function createOrder(Request $request, string $tenant)
    {
        $request->validate([
            'plan_id'      => 'required|integer',
            'billing_cycle'=> 'required|in:monthly,yearly',
            'coupon_code'  => 'nullable|string',
        ]);

        $company = Auth::user()->company;
        $plan    = SubscriptionPlan::findOrFail($request->plan_id);

        // Prevent duplicate — already active on same plan
        $existing = \App\Models\Subscription::where('company_id', $company->id)
            ->where('plan_id', $plan->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'You are already on the ' . $plan->name . ' plan. It renews on ' . $existing->ends_at->format('d M Y') . '.'
            ], 422);
        }

        $amount  = $request->billing_cycle === 'yearly' ? $plan->price_yearly : $plan->price;
        $discount= 0;
        $couponCode = null;

        if ($request->coupon_code) {
            $result = $this->svc->applyCoupon($request->coupon_code, $amount, $company, $plan->id);
            if ($result['valid']) {
                $discount   = $result['discount'];
                $amount     = $result['final'];
                $couponCode = strtoupper($request->coupon_code);
            }
        }

        $gstRate  = (float) PaymentSetting::getValue('gst_rate', '18');
        $tax      = round($amount * $gstRate / 100, 2);
        $total    = $amount + $tax;

        if ($total <= 0) {
            // Free plan — activate directly
            $sub = $this->svc->activateSubscription(
                $company, $plan, $request->billing_cycle,
                $amount, $discount, 'free', 'free', $couponCode
            );
            return response()->json(['free' => true]);
        }

        try {
            $rzOrder = $this->svc->createRazorpayOrder($total, 'sub_' . substr($company->id, 0, 8) . '_' . time(), [
                'company' => $company->name,
                'plan'    => $plan->name,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'order_id'     => $rzOrder['id'],
            'amount'       => $rzOrder['amount'],
            'currency'     => 'INR',
            'plan_id'      => $plan->id,
            'plan_name'    => $plan->name,
            'billing_cycle'=> $request->billing_cycle,
            'subtotal'     => $amount,
            'discount'     => $discount,
            'tax'          => $tax,
            'total'        => $total,
            'coupon_code'  => $couponCode,
        ]);
    }

    // Download invoice as PDF
    public function downloadInvoice(Request $request, string $tenant, int $invoiceId)
    {
        $company = Auth::user()->company;
        $invoice = \App\Models\SubscriptionInvoice::where('id', $invoiceId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $plan = \App\Models\Subscription::where('id', $invoice->subscription_id)
            ->with('plan')
            ->first();

        $html = view('invoices.subscription', [
            'invoice' => $invoice,
            'company' => $company,
            'plan'    => $plan?->plan,
        ])->render();

        // Use DomPDF if available, else return HTML
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            return $pdf->download($invoice->invoice_number . '.pdf');
        }

        return response($html, 200)->header('Content-Type', 'text/html');
    }
    public function verifyPayment(Request $request, string $tenant)
    {
        \Illuminate\Support\Facades\Log::info('Razorpay verify called', $request->only([
            'razorpay_order_id', 'razorpay_payment_id', 'plan_id', 'billing_cycle', 'amount'
        ]));

        $request->validate([
            'razorpay_payment_id' => 'required|string',
            'plan_id'             => 'required|integer',
            'billing_cycle'       => 'required|string',
            'amount'              => 'required|numeric',
            'discount'            => 'nullable|numeric',
            'coupon_code'         => 'nullable|string',
        ]);

        // Verify signature only when order_id and signature are present
        $orderId   = $request->razorpay_order_id;
        $signature = $request->razorpay_signature;

        if ($orderId && $signature) {
            $valid = $this->svc->verifyPayment($orderId, $request->razorpay_payment_id, $signature);
            if (!$valid) {
                \Illuminate\Support\Facades\Log::error('Razorpay signature failed', [
                    'order_id' => $orderId, 'payment_id' => $request->razorpay_payment_id,
                ]);
                return redirect()->route('tenant.subscription.plans', ['tenant' => $tenant])
                    ->with('error', 'Payment verification failed. Contact support with ID: ' . $request->razorpay_payment_id);
            }
        }

        try {
            $company = Auth::user()->company;
            $plan    = SubscriptionPlan::findOrFail($request->plan_id);

            $this->svc->activateSubscription(
                $company, $plan, $request->billing_cycle,
                (float) $request->amount,
                (float) ($request->discount ?? 0),
                $request->razorpay_payment_id,
                $request->razorpay_order_id,
                $request->coupon_code ?: null
            );

            \Illuminate\Support\Facades\Log::info('Subscription activated', [
                'company' => $company->slug,
                'plan'    => $plan->slug,
            ]);

            return redirect()->route('tenant.subscription.plans', ['tenant' => $tenant])
                ->with('success', "🎉 Welcome to {$plan->name}! Your subscription is now active.");

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Subscription activation failed', [
                'error'      => $e->getMessage(),
                'payment_id' => $request->razorpay_payment_id,
            ]);
            return redirect()->route('tenant.subscription.plans', ['tenant' => $tenant])
                ->with('error', 'Payment received but activation failed. Contact support with payment ID: ' . $request->razorpay_payment_id);
        }
    }

    // Apply coupon (AJAX)
    public function applyCoupon(Request $request, string $tenant)
    {
        $request->validate([
            'code'    => 'required|string',
            'plan_id' => 'required|integer',
            'amount'  => 'required|numeric',
        ]);

        $company = Auth::user()->company;
        $result  = $this->svc->applyCoupon($request->code, $request->amount, $company, $request->plan_id);

        return response()->json($result);
    }

    // Limit status (called from frontend to show upgrade modal)
    public function limitStatus(Request $request, string $tenant)
    {
        $company = Auth::user()->company;
        return response()->json($this->svc->getLimitStatus($company));
    }
}
