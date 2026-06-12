<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Coupon;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\PaymentSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function getActiveSubscription(Company $company): ?Subscription
    {
        return Subscription::where('company_id', $company->id)
            ->whereIn('status', ['active', 'trial'])
            ->with('plan')
            ->latest()
            ->first();
    }

    public function getCurrentPlan(Company $company): SubscriptionPlan
    {
        $sub = $this->getActiveSubscription($company);
        if ($sub) return $sub->plan;
        return SubscriptionPlan::where('slug', 'free')->first()
            ?? SubscriptionPlan::orderBy('sort_order')->first();
    }

    public function isOverLimit(Company $company, string $type = 'orders'): bool
    {
        $plan = $this->getCurrentPlan($company);

        if ($type === 'orders') {
            $limit = $plan->order_limit;
            if ($limit === -1) return false;
            return $company->order_count >= $limit;
        }

        return false;
    }

    public function getLimitStatus(Company $company): array
    {
        $plan  = $this->getCurrentPlan($company);

        // Get real order count from orders table
        try {
            $count = \App\Models\Tenant\Order::count();
            if ($company->order_count !== $count) {
                $company->updateQuietly(['order_count' => $count]);
            }
        } catch (\Exception $e) {
            $count = $company->order_count ?? 0;
        }

        $limit         = $plan->order_limit;
        $graceDays     = (int) PaymentSetting::getValue('grace_period_days', '7');
        $warningPct    = (int) PaymentSetting::getValue('limit_warning_pct', '90');
        $isUnlimited   = $limit === -1;
        $isOver        = !$isUnlimited && $count >= $limit;
        $isNear        = !$isUnlimited && !$isOver && $limit > 0 && ($count / $limit * 100) >= $warningPct;

        // Grace period logic
        $inGrace       = false;
        $graceEndsAt   = null;
        $daysInGrace   = 0;
        $daysLeft      = 0;
        $hardBlocked   = false;

        if ($isOver && $plan->is_free) {
            if ($graceDays === 0) {
                // No grace period — immediate hard block
                $hardBlocked = true;
                $inGrace     = false;
            } else {
                // Start grace period if not already started
                if (!$company->grace_period_started_at) {
                    $company->updateQuietly(['grace_period_started_at' => now()]);
                    $company->refresh();
                }

                $graceStarted = \Carbon\Carbon::parse($company->grace_period_started_at);
                $graceEndsAt  = $graceStarted->copy()->addDays($graceDays);
                $daysLeft     = max(0, (int) now()->diffInDays($graceEndsAt, false));
                $inGrace      = now()->lt($graceEndsAt);
                $hardBlocked  = !$inGrace;
            }
        }

        return [
            'plan'                    => $plan->name,
            'plan_slug'               => $plan->slug,
            'is_free'                 => $plan->is_free,
            'order_count'             => $count,
            'order_limit'             => $limit,
            'is_over'                 => $isOver,
            'is_near'                 => $isNear,
            'pct_used'                => $isUnlimited ? 0 : ($limit > 0 ? min(100, round($count / $limit * 100)) : 0),
            'in_grace'                => $inGrace,
            'grace_days_left'         => $daysLeft,
            'grace_ends_at'           => $graceEndsAt?->toDateString(),
            'hard_blocked'            => $hardBlocked,
            'grace_period_days'       => $graceDays,
        ];
    }

    public function sendGracePeriodEmails(Company $company): void
    {
        $status = $this->getLimitStatus($company);
        if (!$status['in_grace']) return;

        $daysLeft = $status['grace_days_left'];
        $graceDays = $status['grace_period_days'];

        // Check which day emails are configured to send
        $sendOnDays = [];
        if (PaymentSetting::getValue('grace_email_day_1', '1')) $sendOnDays[] = $graceDays - 0; // Day 1 (first day)
        if (PaymentSetting::getValue('grace_email_day_3', '1')) $sendOnDays[] = $graceDays - 2; // Day 3
        if (PaymentSetting::getValue('grace_email_day_7', '1')) $sendOnDays[] = 1;               // Last day

        if (!in_array($daysLeft, $sendOnDays)) return;

        $owner = $company->users()->first();
        if (!$owner?->email) return;

        app(\App\Services\BrevoService::class)->sendTemplate('grace_period_warning', $owner->email, $owner->name, [
            'owner_name'     => $owner->name,
            'company_name'   => $company->name,
            'days_left'      => $daysLeft,
            'order_count'    => number_format($status['order_count']),
            'order_limit'    => number_format($status['order_limit']),
            'grace_ends_date'=> \Carbon\Carbon::parse($status['grace_ends_at'])->format('d M Y'),
            'upgrade_url'    => url("/app/{$company->slug}/subscription/plans"),
        ]);
    }

    public function applyCoupon(string $code, float $amount, Company $company, int $planId): array
    {
        $coupon = Coupon::where('code', strtoupper($code))
            ->where('is_active', true)
            ->first();

        if (!$coupon) return ['valid' => false, 'error' => 'Invalid coupon code.'];

        // Validity window
        if ($coupon->valid_from && now()->lt($coupon->valid_from))
            return ['valid' => false, 'error' => 'Coupon not yet valid.'];
        if ($coupon->valid_until && now()->gt($coupon->valid_until))
            return ['valid' => false, 'error' => 'Coupon has expired.'];

        // Usage limit
        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit)
            return ['valid' => false, 'error' => 'Coupon usage limit reached.'];

        // Per-user limit
        $used = \App\Models\CouponUsage::where('coupon_id', $coupon->id)
            ->where('company_id', $company->id)->count();
        if ($used >= $coupon->per_user_limit)
            return ['valid' => false, 'error' => 'You have already used this coupon.'];

        // First time only
        if ($coupon->first_time_only) {
            $hasPaid = Subscription::where('company_id', $company->id)
                ->where('status', 'active')->exists();
            if ($hasPaid) return ['valid' => false, 'error' => 'Coupon valid for first subscription only.'];
        }

        // Applicable plans
        if ($coupon->applicable_plans) {
            $plans = is_array($coupon->applicable_plans) ? $coupon->applicable_plans : json_decode($coupon->applicable_plans, true);
            if (!empty($plans) && !in_array($planId, $plans))
                return ['valid' => false, 'error' => 'Coupon not valid for this plan.'];
        }

        // Calculate discount
        $discount = $coupon->type === 'percent'
            ? ($amount * $coupon->value / 100)
            : $coupon->value;

        if ($coupon->max_discount) $discount = min($discount, $coupon->max_discount);
        $discount = min($discount, $amount);

        return [
            'valid'       => true,
            'discount'    => round($discount, 2),
            'final'       => round($amount - $discount, 2),
            'coupon_id'   => $coupon->id,
            'description' => $coupon->type === 'percent'
                ? "{$coupon->value}% off"
                : "₹{$coupon->value} off",
        ];
    }

    public function getRazorpayConfig(): array
    {
        $mode    = PaymentSetting::getValue('razorpay_mode', 'test');
        $keyId   = PaymentSetting::getValue("razorpay_key_id_{$mode}", '');
        $secret  = PaymentSetting::getValue("razorpay_key_secret_{$mode}", '');
        return compact('mode', 'keyId', 'secret');
    }

    public function createRazorpayOrder(float $amount, string $receipt, array $notes = []): array
    {
        $config = $this->getRazorpayConfig();
        if (!$config['keyId'] || !$config['secret']) {
            throw new \Exception('Razorpay not configured. Add keys in Admin → Subscriptions → Settings.');
        }

        $response = \Illuminate\Support\Facades\Http::withBasicAuth($config['keyId'], $config['secret'])
            ->post('https://api.razorpay.com/v1/orders', [
                'amount'   => (int) ($amount * 100), // paise
                'currency' => 'INR',
                'receipt'  => $receipt,
                'notes'    => $notes,
            ]);

        if ($response->failed()) {
            Log::error('Razorpay order creation failed', $response->json());
            throw new \Exception('Payment gateway error. Please try again.');
        }

        return $response->json();
    }

    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        $config    = $this->getRazorpayConfig();
        $expected  = hash_hmac('sha256', $orderId . '|' . $paymentId, $config['secret']);
        return hash_equals($expected, $signature);
    }

    public function activateSubscription(
        Company $company,
        SubscriptionPlan $plan,
        string $billingCycle,
        float $amount,
        float $discountAmount,
        string $razorpayPaymentId,
        string $razorpayOrderId,
        ?string $couponCode = null
    ): Subscription {
        return DB::transaction(function () use (
            $company, $plan, $billingCycle, $amount, $discountAmount,
            $razorpayPaymentId, $razorpayOrderId, $couponCode
        ) {
            // Cancel existing active subscriptions
            Subscription::where('company_id', $company->id)
                ->whereIn('status', ['active', 'trial'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $gstRate   = (float) PaymentSetting::getValue('gst_rate', '18');
            $taxAmount = round($amount * $gstRate / 100, 2);
            $final     = $amount + $taxAmount - $discountAmount;

            $endsAt = $billingCycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth();

            $sub = Subscription::create([
                'company_id'             => $company->id,
                'plan_id'                => $plan->id,
                'status'                 => 'active',
                'billing_cycle'          => $billingCycle,
                'amount'                 => $amount,
                'discount_amount'        => $discountAmount,
                'tax_amount'             => $taxAmount,
                'final_amount'           => $final,
                'coupon_code'            => $couponCode,
                'razorpay_payment_id'    => $razorpayPaymentId,
                'starts_at'              => now(),
                'ends_at'                => $endsAt,
            ]);

            // Update company plan
            $company->update([
                'plan'                => $plan->slug,
                'active_plan_id'      => $plan->id,
                'subscription_status' => 'active',
            ]);

            // Create invoice
            $invoiceNumber = 'INV-SUB-' . strtoupper(base_convert((string) time(), 10, 36)) . '-' . $sub->id;
            \App\Models\SubscriptionInvoice::create([
                'subscription_id'     => $sub->id,
                'company_id'          => $company->id,
                'invoice_number'      => $invoiceNumber,
                'subtotal'            => round($amount, 2),
                'cgst'                => round($taxAmount / 2, 2),
                'sgst'                => round($taxAmount / 2, 2),
                'igst'                => 0,
                'total'               => round($final, 2),
                'status'              => 'paid',
                'razorpay_payment_id' => $razorpayPaymentId,
                'paid_at'             => now(),
            ]);

            // Update coupon usage
            if ($couponCode) {
                $coupon = Coupon::where('code', $couponCode)->first();
                if ($coupon) {
                    $coupon->increment('usage_count');
                    \App\Models\CouponUsage::create([
                        'coupon_id'        => $coupon->id,
                        'company_id'       => $company->id,
                        'discount_applied' => $discountAmount,
                        'used_at'          => now(),
                    ]);
                }
            }

            // Send confirmation + invoice email
            try {
                $owner = $company->users()->first();
                if ($owner && $owner->email) {
                    $invoiceUrl = url("/app/{$company->slug}/subscription/invoice/{$sub->invoice?->id}/download");
                    app(\App\Services\BrevoService::class)->sendSubscriptionActivated(
                        $owner->email,
                        $owner->name ?? $company->name,
                        [
                            'owner_name'     => $owner->name ?? $company->name,
                            'company_name'   => $company->name,
                            'plan_name'      => $plan->name,
                            'billing_cycle'  => ucfirst($billingCycle),
                            'amount'         => '₹' . number_format($final, 0),
                            'next_renewal'   => $endsAt->format('d M Y'),
                            'invoice_number' => $invoiceNumber ?? '—',
                            'dashboard_url'  => url("/app/{$company->slug}/dashboard"),
                        ]
                    );
                }
            } catch (\Exception $e) {
                // Email failed silently — don't block subscription activation
            }

            return $sub;
        });
    }
}
