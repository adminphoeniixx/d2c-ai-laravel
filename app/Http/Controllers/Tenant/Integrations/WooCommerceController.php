<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\Integrations\SyncWooOrders;
use App\Models\IntegrationAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WooCommerceController extends Controller
{
    public function show(Request $request): Response
    {
        $company = app('current_company');
        $account = IntegrationAccount::query()
            ->where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_WOO)
            ->first();

        return Inertia::render('Tenant/Integrations/WooCommerce', [
            'account' => $account ? [
                'status'         => $account->status,
                'mode'           => $account->mode,
                'shop_domain'    => $account->shop_domain,
                'connected_at'   => $account->connected_at,
                'last_synced_at' => $account->last_synced_at,
            ] : null,
        ]);
    }

    /**
     * WooCommerce REST "auth" flow: we redirect the user to their own WP admin
     * at /wc-auth/v1/authorize with scope + callback_url.
     * Woo then POSTs the keys back to our return URL.
     */
    public function connect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_url' => ['required', 'url', 'max:255'],
        ]);

        $company = app('current_company');
        $shopUrl = rtrim($validated['shop_url'], '/');
        $userId  = 'company-'.$company->id;

        // Stash the shop URL in the session so the callback can associate it
        session(['woo.connecting.shop' => $shopUrl, 'woo.connecting.state' => Str::random(32)]);

        $callback = route('tenant.integrations.woo.callback', ['tenant' => $company->slug]);
        $return   = route('tenant.integrations.woo.show',    ['tenant' => $company->slug]);

        $params = http_build_query([
            'app_name'     => (string) config('services.woo.app_name', 'heyd2c D2C Ops'),
            'scope'        => (string) config('services.woo.scope', 'read_write'),
            'user_id'      => $userId,
            'return_url'   => $return,
            'callback_url' => $callback,
        ]);

        return redirect()->away("{$shopUrl}/wc-auth/v1/authorize?{$params}");
    }

    /** Woo POSTs { consumer_key, consumer_secret, key_permissions, user_id } to this endpoint. */
    public function callback(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'consumer_key'    => ['required', 'string'],
            'consumer_secret' => ['required', 'string'],
            'key_permissions' => ['required', 'string'],
            'user_id'         => ['required', 'string'],
        ]);

        $company = app('current_company');
        $shopUrl = session('woo.connecting.shop');

        abort_unless($shopUrl, 400, 'Missing shop url in session.');
        abort_unless(str_ends_with((string) $data['user_id'], $company->id), 403, 'user_id mismatch.');

        $account = IntegrationAccount::updateOrCreate(
            ['company_id' => $company->id, 'provider' => IntegrationAccount::PROVIDER_WOO],
            [
                'mode'         => IntegrationAccount::MODE_OAUTH,
                'status'       => IntegrationAccount::STATUS_CONNECTED,
                'shop_domain'  => parse_url($shopUrl, PHP_URL_HOST),
                'credentials'  => [
                    'base_url'        => $shopUrl,
                    'consumer_key'    => $data['consumer_key'],
                    'consumer_secret' => $data['consumer_secret'],
                ],
                'scopes'       => [$data['key_permissions']],
                'connected_at' => now(),
            ]
        );

        $company->update(['woo_connected_at' => now()]);
        SyncWooOrders::dispatch($account->id, backfill: true)->onQueue('integrations');

        return redirect()
            ->route('tenant.integrations.woo.show', ['tenant' => $company->slug])
            ->with('success', 'WooCommerce connected. Sync started.');
    }

    /** Manual fallback: merchant pastes consumer key/secret. */
    public function manual(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_url'        => ['required', 'url', 'max:255'],
            'consumer_key'    => ['required', 'string', 'starts_with:ck_'],
            'consumer_secret' => ['required', 'string', 'starts_with:cs_'],
        ]);

        $company = app('current_company');
        $shopUrl = rtrim($validated['shop_url'], '/');

        $account = IntegrationAccount::updateOrCreate(
            ['company_id' => $company->id, 'provider' => IntegrationAccount::PROVIDER_WOO],
            [
                'mode'         => IntegrationAccount::MODE_MANUAL,
                'status'       => IntegrationAccount::STATUS_CONNECTED,
                'shop_domain'  => parse_url($shopUrl, PHP_URL_HOST),
                'credentials'  => [
                    'base_url'        => $shopUrl,
                    'consumer_key'    => $validated['consumer_key'],
                    'consumer_secret' => $validated['consumer_secret'],
                ],
                'scopes'       => ['read_write'],
                'connected_at' => now(),
            ]
        );

        $company->update(['woo_connected_at' => now()]);
        SyncWooOrders::dispatch($account->id, backfill: true)->onQueue('integrations');

        return back()->with('success', 'WooCommerce connected via REST API. Sync started.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $company = app('current_company');
        IntegrationAccount::query()
            ->where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_WOO)
            ->delete();
        $company->update(['woo_connected_at' => null]);

        return back()->with('success', 'WooCommerce disconnected.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $company = app('current_company');
        $account = IntegrationAccount::query()
            ->where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_WOO)
            ->first();

        if (!$account) {
            return back()->with('error', 'WooCommerce not connected.');
        }

        // Force status to connected before sync
        $account->update(['status' => IntegrationAccount::STATUS_CONNECTED]);

        $beforeCount = \App\Models\Tenant\Order::where('provider', 'woocommerce')->count();

        // Use backfill: true to pull all orders
        try {
            SyncWooOrders::dispatchSync($account->id, backfill: true);
        } catch (\Throwable $e) {
            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }

        $afterCount = \App\Models\Tenant\Order::where('provider', 'woocommerce')->count();
        $newOrders = $afterCount - $beforeCount;

        return back()->with('success', "WooCommerce synced. Total: {$afterCount} orders" . ($newOrders > 0 ? " ({$newOrders} new)" : " (all up to date)"));
    }
}
