<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\Integrations\SyncGoogleAds;
use App\Models\IntegrationAccount;
use App\Services\Integrations\Google\GoogleAdsClient;
use App\Services\Integrations\Google\GoogleOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GoogleAdsController extends Controller
{
    public function __construct(protected GoogleOAuth $oauth) {}

    public function show(): Response
    {
        $company = app('current_company');
        $account = IntegrationAccount::where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_GOOGLE)
            ->first();

        return Inertia::render('Tenant/Integrations/GoogleAds', [
            'account' => $account ? [
                'status'          => $account->status,
                'customer_id'     => $account->getCredential('customer_id'),
                'connected_at'    => $account->connected_at,
                'last_synced_at'  => $account->last_synced_at,
                'error_message'   => $account->error_message,
            ] : null,
            'configured' => !empty(config('services.google_ads.client_id')),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $slug = $request->route('tenant');
        $state = Str::random(32);
        session(['google_ads_oauth_state' => $state]);

        $redirectUri = route('tenant.integrations.google-ads.callback', ['tenant' => $slug]);
        $url = $this->oauth->buildAuthorizeUrl($redirectUri, $state);

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $slug = $request->route('tenant');
        $company = app('current_company');

        if ($request->has('error')) {
            return redirect()->route('tenant.integrations.google-ads.show', ['tenant' => $slug])
                ->with('error', 'Google authorization denied: ' . $request->input('error_description', ''));
        }

        $code = $request->input('code');
        $redirectUri = route('tenant.integrations.google-ads.callback', ['tenant' => $slug]);

        try {
            $tokens = $this->oauth->exchangeCode($code, $redirectUri);
            $accessToken = $tokens['access_token'];
            $refreshToken = $tokens['refresh_token'] ?? null;

            // Fetch accessible customer IDs
            $developerToken = config('services.google_ads.developer_token', '');
            $client = new GoogleAdsClient($accessToken, '', $developerToken);
            $customerResources = $client->getAccessibleCustomers();

            if (empty($customerResources)) {
                return redirect()->route('tenant.integrations.google-ads.show', ['tenant' => $slug])
                    ->with('error', 'No Google Ads accounts found.');
            }

            // Extract first customer ID from resource name: customers/1234567890
            $customerId = str_replace('customers/', '', $customerResources[0] ?? '');

            IntegrationAccount::updateOrCreate(
                ['company_id' => $company->id, 'provider' => IntegrationAccount::PROVIDER_GOOGLE],
                [
                    'mode'         => IntegrationAccount::MODE_OAUTH,
                    'status'       => IntegrationAccount::STATUS_CONNECTED,
                    'credentials'  => [
                        'access_token'  => $accessToken,
                        'refresh_token' => $refreshToken,
                        'customer_id'   => $customerId,
                    ],
                    'connected_at'  => now(),
                    'error_message' => null,
                ]
            );

            SyncGoogleAds::dispatch($company->id);

            return redirect()->route('tenant.integrations.google-ads.show', ['tenant' => $slug])
                ->with('success', 'Google Ads connected! Syncing campaigns…');

        } catch (\Throwable $e) {
            return redirect()->route('tenant.integrations.google-ads.show', ['tenant' => $slug])
                ->with('error', 'Failed to connect: ' . $e->getMessage());
        }
    }

    public function manual(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'access_token'  => ['required', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'customer_id'   => ['required', 'string'],
        ]);

        $company = app('current_company');
        $slug = $request->route('tenant');

        IntegrationAccount::updateOrCreate(
            ['company_id' => $company->id, 'provider' => IntegrationAccount::PROVIDER_GOOGLE],
            [
                'mode'        => IntegrationAccount::MODE_MANUAL,
                'status'      => IntegrationAccount::STATUS_CONNECTED,
                'credentials' => $validated,
                'connected_at'  => now(),
                'error_message' => null,
            ]
        );

        SyncGoogleAds::dispatch($company->id);

        return redirect()->route('tenant.integrations.google-ads.show', ['tenant' => $slug])
            ->with('success', 'Google Ads connected manually! Syncing…');
    }

    public function sync(Request $request): RedirectResponse
    {
        $company = app('current_company');
        SyncGoogleAds::dispatch($company->id);
        return back()->with('success', 'Google Ads sync started.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $company = app('current_company');

        IntegrationAccount::where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_GOOGLE)
            ->update(['status' => IntegrationAccount::STATUS_DISCONNECTED]);

        return back()->with('success', 'Google Ads disconnected.');
    }
}
