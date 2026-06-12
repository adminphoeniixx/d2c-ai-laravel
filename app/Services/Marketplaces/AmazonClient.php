<?php

declare(strict_types=1);

namespace App\Services\Marketplaces;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonClient
{
    protected string $region = 'eu'; // India is EU region for SP-API
    protected string $endpoint = 'https://sellingpartnerapi-eu.amazon.com';
    protected string $tokenUrl = 'https://api.amazon.com/auth/o2/token';

    protected string $clientId;
    protected string $clientSecret;
    protected string $refreshToken;
    protected ?string $accessToken = null;

    public function __construct(array $credentials)
    {
        $this->clientId = $credentials['client_id'] ?? '';
        $this->clientSecret = $credentials['client_secret'] ?? '';
        $this->refreshToken = $credentials['refresh_token'] ?? '';
    }

    /**
     * Get access token using refresh token (LWA).
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) return $this->accessToken;

        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->refreshToken,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Amazon LWA token refresh failed: ' . $response->body());
        }

        $this->accessToken = $response->json('access_token');
        return $this->accessToken;
    }

    /**
     * Fetch orders from Amazon SP-API.
     * @param string $createdAfter ISO 8601 date
     * @param string $marketplaceId A21TJRUUN4KGV for India
     */
    public function getOrders(string $createdAfter, string $marketplaceId = 'A21TJRUUN4KGV', ?string $nextToken = null): array
    {
        $token = $this->getAccessToken();

        $params = [
            'MarketplaceIds' => $marketplaceId,
            'CreatedAfter'   => $createdAfter,
            'MaxResultsPerPage' => 50,
        ];

        if ($nextToken) {
            $params = ['NextToken' => $nextToken];
        }

        $response = Http::withHeaders([
            'x-amz-access-token' => $token,
            'Content-Type'       => 'application/json',
        ])->get("{$this->endpoint}/orders/v0/orders", $params);

        if (!$response->successful()) {
            Log::error('Amazon SP-API getOrders failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Amazon API error: ' . $response->status());
        }

        return $response->json();
    }

    /**
     * Get order items for a specific order.
     */
    public function getOrderItems(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withHeaders([
            'x-amz-access-token' => $token,
        ])->get("{$this->endpoint}/orders/v0/orders/{$orderId}/orderItems");

        if (!$response->successful()) {
            Log::error('Amazon SP-API getOrderItems failed', ['orderId' => $orderId, 'body' => $response->body()]);
            return [];
        }

        return $response->json('payload.OrderItems', []);
    }

    /**
     * Normalize Amazon order to heyd2c format.
     */
    public static function normalizeOrder(array $order, array $items = []): array
    {
        $subtotal = 0;
        $lineItems = [];

        foreach ($items as $item) {
            $price = (float) ($item['ItemPrice']['Amount'] ?? 0);
            $qty = (int) ($item['QuantityOrdered'] ?? 1);
            $subtotal += $price;

            $lineItems[] = [
                'sku'       => $item['SellerSKU'] ?? '',
                'title'     => $item['Title'] ?? '',
                'quantity'  => $qty,
                'price'     => $price / max($qty, 1),
                'total'     => $price,
            ];
        }

        $totalAmount = (float) ($order['OrderTotal']['Amount'] ?? $subtotal);

        return [
            'external_id'         => $order['AmazonOrderId'],
            'provider'            => 'amazon',
            'marketplace'         => 'amazon',
            'marketplace_order_id'=> $order['AmazonOrderId'],
            'order_number'        => $order['AmazonOrderId'],
            'status'              => strtolower($order['OrderStatus'] ?? 'pending'),
            'financial_status'    => strtolower($order['OrderStatus'] ?? 'pending'),
            'fulfillment_status'  => $order['FulfillmentChannel'] ?? null,
            'currency'            => $order['OrderTotal']['CurrencyCode'] ?? 'INR',
            'subtotal'            => $subtotal,
            'total_tax'           => 0,
            'total_discount'      => 0,
            'total_shipping'      => 0,
            'total_amount'        => $totalAmount,
            'customer_name'       => $order['BuyerInfo']['BuyerName'] ?? null,
            'customer_email'      => $order['BuyerInfo']['BuyerEmail'] ?? null,
            'shipping_address'    => $order['ShippingAddress'] ?? null,
            'line_item_count'     => count($items),
            'placed_at'           => $order['PurchaseDate'] ?? null,
            'raw_payload'         => $order,
        ];
    }
}
