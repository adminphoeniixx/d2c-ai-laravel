<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

use App\Models\ShopifyConnection;
use App\Models\ShopifyOrder;

class ShopifyController extends Controller
{

    /**
     * Redirect merchant to Shopify install page
     */
   public function connect(Request $request)
    {
        $shop = trim($request->shop);

        if (!$shop) {
            return back()->with('error', 'Shop domain required');
        }

        // normalize domain
        if (!str_contains($shop, '.myshopify.com')) {
            $shop = $shop . '.myshopify.com';
        }

        // validate domain format
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com$/', $shop)) {
            return back()->with('error', 'Invalid Shopify store domain');
        }

        $clientId = config('services.shopify.client_id');
        $redirect = config('services.shopify.redirect');
        $scopes = config('services.shopify.scopes');

        $state = bin2hex(random_bytes(16));

        session(['shopify_state' => $state]);

        $installUrl = "https://{$shop}/admin/oauth/authorize?" . http_build_query([
            'client_id' => $clientId,
            'scope' => $scopes,
            'redirect_uri' => $redirect,
            'state' => $state,
        ]);

        return redirect()->away($installUrl);
    }


    /**
     * Shopify OAuth callback
     */
    public function callback(Request $request)
    {
        if ($request->state !== session('shopify_state')) {
            abort(403, 'Invalid OAuth state');
        }

        $shop = $request->shop;
        $code = $request->code;

        $response = Http::post("https://{$shop}/admin/oauth/access_token", [

            'client_id' => config('services.shopify.client_id'),
            'client_secret' => config('services.shopify.client_secret'),
            'code' => $code

        ]);

        $data = $response->json();

        ShopifyConnection::updateOrCreate(

            ['tenant_id' => Auth::user()->tenant_id],

            [
                'shop' => $shop,
                'access_token' => $data['access_token'],
                'scope' => $data['scope'] ?? null,
                'installed_at' => now(),
                'is_active' => true
            ]

        );

        return redirect('/brand/integrations/shopify')
            ->with('success', 'Shopify connected successfully');
    }


    /**
     * Orders page
     */
    public function orders()
    {

        $orders = ShopifyOrder::latest()
            ->limit(50)
            ->get();

        return Inertia::render('Brand/ShopifyOrders', [
            'orders' => $orders
        ]);

    }


    /**
     * Fetch Shopify Orders
     */
    public function fetchOrders()
    {

        $connection = ShopifyConnection::where(
            'tenant_id',
            Auth::user()->tenant_id
        )->first();

        if (!$connection) {
            return back()->with('error', 'Shopify not connected');
        }

        $shop = $connection->shop;
        $token = $connection->access_token;

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token
        ])->get("https://{$shop}/admin/api/2024-01/orders.json", [
            'status' => 'any',
            'limit' => 250
        ]);

        $orders = $response->json()['orders'] ?? [];

        foreach ($orders as $order) {

            $customer = $order['customer'] ?? null;

            $customerName = null;

            if ($customer) {
                $first = $customer['first_name'] ?? '';
                $last = $customer['last_name'] ?? '';
                $customerName = trim($first . ' ' . $last);
            }

            $products = [];

            foreach ($order['line_items'] as $item) {

                $products[] = [
                    'product_id' => $item['product_id'] ?? null,
                    'variant_id' => $item['variant_id'] ?? null,
                    'title' => $item['title'] ?? null,
                    'price' => $item['price'] ?? 0,
                    'quantity' => $item['quantity'] ?? 0,
                ];
            }

            ShopifyOrder::updateOrCreate(

                ['shopify_id' => $order['id']],

                [

                    'tenant_id' => Auth::user()->tenant_id,

                    'order_number' => $order['order_number'],
                    'currency' => $order['currency'],

                    'financial_status' => $order['financial_status'],
                    'fulfillment_status' => $order['fulfillment_status'],

                    'total_price' => $order['total_price'],
                    'subtotal_price' => $order['subtotal_price'],
                    'total_discounts' => $order['total_discounts'],

                    'created_at_shopify' => $order['created_at'],

                    'customer' => [
                        'id' => $customer['id'] ?? null,
                        'email' => $customer['email'] ?? ($order['email'] ?? null),
                        'name' => $customerName ?: ($order['email'] ?? 'Guest'),
                    ],

                    'products' => $products,

                    'raw' => $order

                ]

            );

        }

        return redirect()->route('brand.shopify.orders')
            ->with('success', 'Orders synced successfully');

    }

}