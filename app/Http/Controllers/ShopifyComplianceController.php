<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ShopifyComplianceController extends Controller
{
    /**
     * Single endpoint for all compliance topics — used with shopify.app.toml
     */
    public function handle(Request $request)
    {
        $topic = $request->header('X-Shopify-Topic');
        return match($topic) {
            'customers/data_request' => $this->customerDataRequest($request),
            'customers/redact'       => $this->customerRedact($request),
            'shop/redact'            => $this->shopRedact($request),
            default                  => response('Unknown topic', 400),
        };
    }

    /**
     * customers/data_request
     * Shopify sends this when a customer requests their data.
     * Must respond with 200 within 48 hours.
     */
    public function customerDataRequest(Request $request)
    {
        $payload    = $request->json()->all();
        $shopDomain = $payload['shop_domain'] ?? null;
        $customerId = $payload['customer']['id'] ?? null;
        $email      = $payload['customer']['email'] ?? null;

        // heyd2c syncs order data from merchant stores — we don't store
        // end-customer PII directly. Data lives in the merchant's own DB.
        // If storing customer data, collect and email it here within 30 days.

        return response('OK', 200);
    }

    /**
     * customers/redact
     * Shopify sends this when a customer requests deletion of their data.
     * Must respond with 200 and delete/anonymise their data within 30 days.
     */
    public function customerRedact(Request $request)
    {
        $payload    = $request->json()->all();
        $shopDomain = $payload['shop_domain'] ?? null;
        $customerId = $payload['customer']['id'] ?? null;

        // Find and anonymise any stored customer records.
        // heyd2c stores orders synced from merchant stores —
        // anonymise name/email in those orders if required.

        return response('OK', 200);
    }

    /**
     * shop/redact
     * Shopify sends this 48 hours after app uninstall.
     * Must delete all shop data and respond with 200.
     */
    public function shopRedact(Request $request)
    {
        $payload    = $request->json()->all();
        $shopDomain = $payload['shop_domain'] ?? null;
        $shopId     = $payload['shop_id'] ?? null;

        // Find the tenant connected to this Shopify shop and
        // delete or archive all their synced data.
        if ($shopDomain) {
            // Example:
            // $integration = \App\Models\Tenant\Integration::where('type', 'shopify')
            //     ->where('config->shop_domain', $shopDomain)->first();
            // if ($integration) tenancy()->initialize($integration->company)->run(fn() => \App\Models\Tenant\Order::truncate());
        }

        return response('OK', 200);
    }
}
