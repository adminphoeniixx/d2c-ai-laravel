<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\Integrations\SyncMetaAds;
use App\Models\IntegrationAccount;
use App\Services\Integrations\Meta\MetaAdsClient;
use App\Services\Integrations\Meta\MetaOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MetaAdsController extends Controller
{
    public function __construct(protected MetaOAuth $oauth) {}

    public function show(): Response
    {
        $company = app('current_company');
        $account = IntegrationAccount::where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_META)
            ->first();

        return Inertia::render('Tenant/Integrations/MetaAds', [
            'account' => $account ? [
                'status'          => $account->status,
                'ad_account_id'   => $account->getCredential('ad_account_id'),
                'ad_account_name' => $account->getCredential('ad_account_name'),
                'connected_at'    => $account->connected_at,
                'last_synced_at'  => $account->last_synced_at,
                'error_message'   => $account->error_message,
            ] : null,
            'configured' => !empty(config('services.meta.app_id')),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $company = app('current_company');
        $slug = $request->route('tenant');
        $state = Str::random(32);
        session(['meta_oauth_state' => $state]);

        $redirectUri = route('tenant.integrations.meta.callback', ['tenant' => $slug]);
        $url = $this->oauth->buildAuthorizeUrl($redirectUri, $state);

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $slug = $request->route('tenant');
        $company = app('current_company');

        if ($request->has('error')) {
            return redirect()->route('tenant.integrations.meta.show', ['tenant' => $slug])
                ->with('error', 'Meta authorization was denied: ' . $request->input('error_description', ''));
        }

        $code = $request->input('code');
        $redirectUri = route('tenant.integrations.meta.callback', ['tenant' => $slug]);

        try {
            $tokens = $this->oauth->exchangeCode($code, $redirectUri);
            $accessToken = $tokens['access_token'];

            // Fetch ad accounts so user can pick one
            $client = new MetaAdsClient($accessToken, '');
            $adAccounts = $client->getAdAccounts();

            if (empty($adAccounts)) {
                return redirect()->route('tenant.integrations.meta.show', ['tenant' => $slug])
                    ->with('error', 'No ad accounts found for this Facebook user.');
            }

            // Use first ad account by default (user can change in settings later)
            $firstAccount = $adAccounts[0];
            $adAccountId = $firstAccount['account_id'] ?? str_replace('act_', '', $firstAccount['id'] ?? '');

            IntegrationAccount::updateOrCreate(
                ['company_id' => $company->id, 'provider' => IntegrationAccount::PROVIDER_META],
                [
                    'mode'         => IntegrationAccount::MODE_OAUTH,
                    'status'       => IntegrationAccount::STATUS_CONNECTED,
                    'credentials'  => [
                        'access_token'    => $accessToken,
                        'expires_in'      => $tokens['expires_in'] ?? 5184000,
                        'ad_account_id'   => $adAccountId,
                        'ad_account_name' => $firstAccount['name'] ?? 'Ad Account',
                    ],
                    'connected_at'  => now(),
                    'error_message' => null,
                ]
            );

            // Trigger initial sync
            SyncMetaAds::dispatch($company->id);

            return redirect()->route('tenant.integrations.meta.show', ['tenant' => $slug])
                ->with('success', 'Meta Ads connected! Syncing campaigns…');

        } catch (\Throwable $e) {
            return redirect()->route('tenant.integrations.meta.show', ['tenant' => $slug])
                ->with('error', 'Failed to connect: ' . $e->getMessage());
        }
    }

    public function manual(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'access_token'  => ['required', 'string'],
            'ad_account_id' => ['required', 'string'],
        ]);

        $company = app('current_company');
        $slug = $request->route('tenant');

        IntegrationAccount::updateOrCreate(
            ['company_id' => $company->id, 'provider' => IntegrationAccount::PROVIDER_META],
            [
                'mode'        => IntegrationAccount::MODE_MANUAL,
                'status'      => IntegrationAccount::STATUS_CONNECTED,
                'credentials' => [
                    'access_token'  => $validated['access_token'],
                    'ad_account_id' => $validated['ad_account_id'],
                ],
                'connected_at'  => now(),
                'error_message' => null,
            ]
        );

        SyncMetaAds::dispatch($company->id);

        return redirect()->route('tenant.integrations.meta.show', ['tenant' => $slug])
            ->with('success', 'Meta Ads connected manually! Syncing…');
    }

    public function sync(Request $request): RedirectResponse
    {
        $company = app('current_company');
        $slug = $request->route('tenant');
        SyncMetaAds::dispatch($company->id);
        return back()->with('success', 'Meta Ads sync started.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $company = app('current_company');
        $slug = $request->route('tenant');

        IntegrationAccount::where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_META)
            ->update(['status' => IntegrationAccount::STATUS_DISCONNECTED]);

        return back()->with('success', 'Meta Ads disconnected.');
    }
}
