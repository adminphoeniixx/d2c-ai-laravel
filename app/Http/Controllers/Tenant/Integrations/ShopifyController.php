<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\Integrations\SyncShopifyOrders;
use App\Models\IntegrationAccount;
use App\Services\Integrations\Shopify\ShopifyOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopifyController extends Controller
{
    public function __construct(protected ShopifyOAuth $oauth) {}

    public function show(Request $request): Response
    {
        $company = app('current_company');
        $account = IntegrationAccount::query()
            ->where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_SHOPIFY)
            ->first();

        return Inertia::render('Tenant/Integrations/Shopify', [
            'account' => $account ? [
                'status'         => $account->status,
                'mode'           => $account->mode,
                'shop_domain'    => $account->shop_domain,
                'connected_at'   => $account->connected_at,
                'last_synced_at' => $account->last_synced_at,
                'scopes'         => $account->scopes,
            ] : null,
            'scopes' => explode(',', (string) config('services.shopify.scopes')),
        ]);
    }

    /** Start OAuth flow: redirects merchant to Shopify for approval. */
    public function connect(Request $request)
    {
        $validated = $request->validate([
            'shop_domain' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/i'],
        ]);

        $company = app('current_company');
        $url = $this->oauth->buildAuthorizeUrl(
            shop: $validated['shop_domain'],
            state: $this->oauth->encodeState($company->id),
        );

        // Must use Inertia::location() for external URLs — regular redirect()
        // gets intercepted as XHR by Inertia, which fails CORS on Shopify
        return Inertia::location($url);
    }

    /** OAuth callback from Shopify — exchanges code for permanent access token. */
    public function callback(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shop'      => ['required', 'string'],
            'code'      => ['required', 'string'],
            'state'     => ['required', 'string'],
            'hmac'      => ['required', 'string'],
            'timestamp' => ['required'],
        ]);

        try {
            $this->oauth->verifyHmac($request->query());
            $companyId = $this->oauth->decodeState($data['state']);

            abort_unless($companyId === app('current_company')->id, 403, 'State mismatch.');

            $token = $this->oauth->exchangeCode($data['shop'], $data['code']);

            $account = IntegrationAccount::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'provider'   => IntegrationAccount::PROVIDER_SHOPIFY,
                ],
                [
                    'mode'          => IntegrationAccount::MODE_OAUTH,
                    'status'        => IntegrationAccount::STATUS_CONNECTED,
                    'shop_domain'   => $data['shop'],
                    'credentials'   => ['access_token' => $token['access_token']],
                    'scopes'        => explode(',', $token['scope'] ?? ''),
                    'connected_at'  => now(),
                    'error_message' => null,
                ]
            );

            app('current_company')->update(['shopify_connected_at' => now()]);

            // Kick off initial backfill (last 1 year of orders) on the integrations queue
            SyncShopifyOrders::dispatch($account->id, backfill: true)
                ->onQueue('integrations');

            return redirect()
                ->route('tenant.integrations.shopify.show', ['tenant' => app('current_company')->slug])
                ->with('success', 'Shopify connected. Your first sync has started.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->route('tenant.integrations.shopify.show', ['tenant' => app('current_company')->slug])
                ->with('error', 'Shopify connection failed: '.$e->getMessage());
        }
    }

    /** Manual fallback: merchant pastes credentials directly. */
    public function manual(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_domain'  => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/i'],
            'access_token' => ['required', 'string', 'starts_with:shpat_,shppa_,shpca_'],
        ]);

        $company = app('current_company');

        $account = IntegrationAccount::updateOrCreate(
            ['company_id' => $company->id, 'provider' => IntegrationAccount::PROVIDER_SHOPIFY],
            [
                'mode'         => IntegrationAccount::MODE_MANUAL,
                'status'       => IntegrationAccount::STATUS_CONNECTED,
                'shop_domain'  => $validated['shop_domain'],
                'credentials'  => ['access_token' => $validated['access_token']],
                'scopes'       => explode(',', (string) config('services.shopify.scopes')),
                'connected_at' => now(),
            ]
        );

        $company->update(['shopify_connected_at' => now()]);

        SyncShopifyOrders::dispatch($account->id, backfill: true)->onQueue('integrations');

        return back()->with('success', 'Shopify connected via API token. Sync started.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $company = app('current_company');
        IntegrationAccount::query()
            ->where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_SHOPIFY)
            ->delete();
        $company->update(['shopify_connected_at' => null]);

        return back()->with('success', 'Shopify disconnected.');
    }
}
