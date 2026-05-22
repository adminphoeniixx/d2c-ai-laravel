<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\Integrations\SyncShopifyOrders;
use App\Models\Company;
use App\Models\IntegrationAccount;
use App\Services\Integrations\Shopify\ShopifyOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Central (non-tenant) Shopify OAuth callback.
 *
 * Shopify doesn't support wildcard redirect URLs, so all OAuth callbacks
 * hit this single route. We decode the company ID from the state parameter,
 * save the token, and redirect the user to their tenant's Shopify page.
 */
class ShopifyCentralCallbackController extends Controller
{
    public function handle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shop'      => ['required', 'string'],
            'code'      => ['required', 'string'],
            'state'     => ['required', 'string'],
            'hmac'      => ['required', 'string'],
            'timestamp' => ['required'],
        ]);

        $oauth = ShopifyOAuth::make();

        try {
            // Verify HMAC signature
            $oauth->verifyHmac($request->query());

            // Decode company ID from state
            $companyId = $oauth->decodeState($data['state']);
            $company = Company::findOrFail($companyId);

            // Exchange code for permanent access token
            $token = $oauth->exchangeCode($data['shop'], $data['code']);

            // Save integration account (central table)
            $account = IntegrationAccount::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'provider'   => IntegrationAccount::PROVIDER_SHOPIFY,
                ],
                [
                    'mode'          => IntegrationAccount::MODE_OAUTH,
                    'status'        => IntegrationAccount::STATUS_CONNECTED,
                    'shop_domain'   => $data['shop'],
                    'credentials'   => array_filter([
                        'access_token'  => $token['access_token'],
                        'refresh_token' => $token['refresh_token'] ?? null,
                        'expires_in'    => $token['expires_in'] ?? null,
                        'token_type'    => $token['token_type'] ?? null,
                    ]),
                    'scopes'        => explode(',', $token['scope'] ?? ''),
                    'connected_at'  => now(),
                    'error_message' => null,
                ]
            );

            $company->update(['shopify_connected_at' => now()]);

            // Kick off initial backfill
            SyncShopifyOrders::dispatch($account->id, backfill: true)
                ->onQueue('integrations');

            return redirect()
                ->route('tenant.integrations.shopify.show', ['tenant' => $company->slug])
                ->with('success', 'Shopify connected! Your first sync has started.');

        } catch (\Throwable $e) {
            report($e);

            // Try to redirect to the company's Shopify page with error
            try {
                $companyId = $oauth->decodeState($data['state']);
                $company = Company::findOrFail($companyId);
                return redirect()
                    ->route('tenant.integrations.shopify.show', ['tenant' => $company->slug])
                    ->with('error', 'Shopify connection failed: ' . $e->getMessage());
            } catch (\Throwable) {
                // Can't decode state — redirect to home
                return redirect('/')->with('error', 'Shopify connection failed.');
            }
        }
    }
}
