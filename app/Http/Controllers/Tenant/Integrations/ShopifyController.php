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
                'error_message'  => $account->error_message,
                'has_refresh_token' => !empty($account->getCredential('refresh_token')),
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

    /**
     * Connect via client_credentials grant — for Dev Dashboard apps where
     * a static token can't be pasted (legacy custom apps only) and the
     * standard OAuth install redirect is blocked (e.g. app under review).
     * Requests a fresh ~24h token immediately using client_id+client_secret;
     * ShopifyClient will auto-regenerate it before each expiry.
     */
    public function connectClientCredentials(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_domain'   => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/i'],
            'client_id'     => ['required', 'string'],
            'client_secret' => ['required', 'string', 'starts_with:shpss_'],
        ]);

        $company = app('current_company');

        try {
            $data = $this->oauth->requestClientCredentialsToken(
                $validated['shop_domain'],
                $validated['client_id'],
                $validated['client_secret'],
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not get a token: ' . $e->getMessage());
        }

        $credentials = [
            'client_id'     => $validated['client_id'],
            'client_secret' => $validated['client_secret'],
            'access_token'  => $data['access_token'],
        ];
        if (isset($data['expires_in'])) {
            $credentials['expires_at'] = now()->addSeconds((int) $data['expires_in'])->toIso8601String();
        }

        $account = IntegrationAccount::updateOrCreate(
            ['company_id' => $company->id, 'provider' => IntegrationAccount::PROVIDER_SHOPIFY],
            [
                'mode'          => IntegrationAccount::MODE_CLIENT_CREDENTIALS,
                'status'        => IntegrationAccount::STATUS_CONNECTED,
                'shop_domain'   => $validated['shop_domain'],
                'credentials'   => $credentials,
                'scopes'        => explode(',', (string) ($data['scope'] ?? config('services.shopify.scopes'))),
                'connected_at'  => now(),
                'error_message' => null,
            ]
        );

        $company->update(['shopify_connected_at' => now()]);

        SyncShopifyOrders::dispatch($account->id, backfill: true)->onQueue('integrations');

        return back()->with('success', 'Shopify connected via client credentials. Sync started.');
    }

    /** Manual fallback: merchant pastes credentials directly. */
    public function manual(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_domain'  => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/i'],
            'access_token' => ['required', 'string', 'starts_with:shpat_,shppa_,shpca_'],
        ]);

        $company = app('current_company');
        $credentials = ['access_token' => $validated['access_token']];

        // Shopify has started rejecting non-expiring tokens for some custom
        // apps even though the documented policy exempts them. Rather than
        // make the merchant deal with that, proactively try the documented
        // migration to an expiring token right away — if it succeeds we
        // store the expiring token + refresh token instead, and the client
        // will auto-refresh from then on. If migration isn't needed/fails,
        // we just fall back to the pasted token as-is.
        try {
            $migrated = $this->oauth->migrateToExpiringToken($validated['shop_domain'], $validated['access_token']);
            if (!empty($migrated['access_token'])) {
                $credentials['access_token'] = $migrated['access_token'];
                if (!empty($migrated['refresh_token'])) {
                    $credentials['refresh_token'] = $migrated['refresh_token'];
                }
                if (isset($migrated['expires_in'])) {
                    $credentials['expires_at'] = now()->addSeconds((int) $migrated['expires_in'])->toIso8601String();
                }
                if (isset($migrated['refresh_token_expires_in'])) {
                    $credentials['refresh_token_expires_at'] = now()->addSeconds((int) $migrated['refresh_token_expires_in'])->toIso8601String();
                }
            }
        } catch (\Throwable $e) {
            // Migration not needed, not supported for this shop/app combo,
            // or the token was already expiring — keep the pasted token.
        }

        $account = IntegrationAccount::updateOrCreate(
            ['company_id' => $company->id, 'provider' => IntegrationAccount::PROVIDER_SHOPIFY],
            [
                'mode'          => IntegrationAccount::MODE_MANUAL,
                'status'        => IntegrationAccount::STATUS_CONNECTED,
                'shop_domain'   => $validated['shop_domain'],
                'credentials'   => $credentials,
                'scopes'        => explode(',', (string) config('services.shopify.scopes')),
                'connected_at'  => now(),
                'error_message' => null,
            ]
        );

        $company->update(['shopify_connected_at' => now()]);

        SyncShopifyOrders::dispatch($account->id, backfill: true)->onQueue('integrations');

        return back()->with('success', 'Shopify connected via API token. Sync started.');
    }

    /**
     * Attempt to migrate an already-connected account's non-expiring token
     * to an expiring one. Useful when a store was connected before this
     * migration logic existed, or when the earlier attempt failed.
     */
    public function migrateToken(Request $request): RedirectResponse
    {
        $company = app('current_company');
        $account = IntegrationAccount::query()
            ->where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_SHOPIFY)
            ->first();

        if (!$account) {
            return back()->with('error', 'Shopify not connected.');
        }

        $currentToken = $account->getCredential('access_token');
        if (empty($currentToken)) {
            return back()->with('error', 'No access token found to migrate.');
        }

        try {
            $migrated = $this->oauth->migrateToExpiringToken($account->shop_domain, $currentToken);

            $credentials = $account->credentials ?? [];
            $credentials['access_token'] = $migrated['access_token'];
            if (!empty($migrated['refresh_token'])) {
                $credentials['refresh_token'] = $migrated['refresh_token'];
            }
            if (isset($migrated['expires_in'])) {
                $credentials['expires_at'] = now()->addSeconds((int) $migrated['expires_in'])->toIso8601String();
            }
            if (isset($migrated['refresh_token_expires_in'])) {
                $credentials['refresh_token_expires_at'] = now()->addSeconds((int) $migrated['refresh_token_expires_in'])->toIso8601String();
            }

            $account->update([
                'credentials'   => $credentials,
                'status'        => IntegrationAccount::STATUS_CONNECTED,
                'error_message' => null,
            ]);

            return back()->with('success', 'Token migrated to the expiring format. Sync should work now.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Migration failed: ' . $e->getMessage() . '. The token may already be expiring, or this shop/app combination may need a fresh manual token.');
        }
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

    public function sync(Request $request): RedirectResponse
    {
        $company = app('current_company');
        $account = IntegrationAccount::query()
            ->where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_SHOPIFY)
            ->first();

        if (!$account) {
            return back()->with('error', 'Shopify not connected.');
        }

        $account->update(['status' => IntegrationAccount::STATUS_CONNECTED]);

        $beforeCount = \App\Models\Tenant\Order::where('provider', 'shopify')->count();

        try {
            SyncShopifyOrders::dispatchSync($account->id, backfill: true);
        } catch (\Throwable $e) {
            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }

        $afterCount = \App\Models\Tenant\Order::where('provider', 'shopify')->count();
        $newOrders = $afterCount - $beforeCount;

        return back()->with('success', "Shopify synced. Total: {$afterCount} orders" . ($newOrders > 0 ? " ({$newOrders} new)" : " (all up to date)"));
    }
}
