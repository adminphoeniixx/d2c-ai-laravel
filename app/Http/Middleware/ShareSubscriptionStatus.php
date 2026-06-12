<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\CompanyKyc;
use App\Models\PaymentSetting;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShareSubscriptionStatus
{
    public function __construct(private SubscriptionService $svc) {}

    public function handle(Request $request, Closure $next)
    {
        if (!$request->route('tenant')) {
            return $next($request);
        }

        $company = $request->attributes->get('company')
            ?? ($request->user()?->company ?? null);

        if ($company instanceof Company) {
            try {
                $limitStatus = $this->svc->getLimitStatus($company);
                Inertia::share('limit_status', $limitStatus);
                Inertia::share('subscription_plan', $limitStatus['plan'] ?? 'free');
            } catch (\Exception $e) {
                Inertia::share('limit_status', null);
                Inertia::share('subscription_plan', 'free');
            }

            // Share KYC status
            try {
                $kycRequired = (bool) PaymentSetting::getValue('kyc_required', '0');
                $kyc         = CompanyKyc::where('company_id', $company->id)->first();

                Inertia::share('kyc_status', [
                    'required'         => $kycRequired,
                    'status'           => $kyc?->status ?? 'pending',
                    'approved'         => $kyc?->status === 'approved',
                    'rejection_reason' => $kyc?->rejection_reason,
                ]);
            } catch (\Exception $e) {
                Inertia::share('kyc_status', null);
            }
        }

        return $next($request);
    }
}
