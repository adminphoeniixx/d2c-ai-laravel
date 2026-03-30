<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\ShopifyConnection;

Route::get('/', function () {
    return Inertia::render('Landing');
});




Route::get('/brand/shopify/test', function () {

    $connection = ShopifyConnection::where('tenant_id', Auth::user()->tenant_id)
        ->where('is_active', true)
        ->first();

    if (!$connection) {
        return 'Shopify not connected';
    }

    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $connection->access_token,
        'Content-Type' => 'application/json'
    ])->get("https://{$connection->shop}/admin/api/2024-04/shop.json");

    return $response->json();
});



Route::get('/brand/shopify/orders-test', function () {

    $connection = ShopifyConnection::where('tenant_id', Auth::user()->tenant_id)
        ->where('is_active', true)
        ->first();

    if (!$connection) {
        return 'Shopify not connected';
    }

   $response = Http::withHeaders([
    'X-Shopify-Access-Token' => $connection->access_token,
])->get("https://{$connection->shop}/admin/api/2024-04/orders.json", [
    'status' => 'any',
    'limit' => 10,
    'fields' => 'id,order_number,total_price,currency,financial_status,fulfillment_status,created_at'
]);

    return $response->json();
});


