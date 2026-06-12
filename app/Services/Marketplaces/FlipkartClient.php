<?php

declare(strict_types=1);

namespace App\Services\Marketplaces;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlipkartClient
{
    protected string $apiBase = 'https://api.flipkart.net/sellers';
    protected string $tokenUrl = 'https://api.flipkart.net/oauth-service/oauth/token';

    protected string $appId;
    protected string $appSecret;
    protected ?string $accessToken = null;

    public function __construct(array $credentials)
    {
        $this->appId = $credentials['app_id'] ?? '';
        $this->appSecret = $credentials['app_secret'] ?? '';
        $this->accessToken = $credentials['access_token'] ?? null;
    }

    /**
     * Get access token via Client Credentials flow (for self-access apps).
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) return $this->accessToken;

        $response = Http::withBasicAuth($this->appId, $this->appSecret)
            ->asForm()
            ->post($this->tokenUrl, [
                'grant_type' => 'client_credentials',
                'scope'      => 'Seller_Api',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Flipkart token fetch failed: ' . $response->body());
        }

        $this->accessToken = $response->json('access_token');
        return $this->accessToken;
    }

    /**
     * Search orders by date range.
     */
    public function getOrders(string $fromDate, ?string $toDate = null): array
    {
        $token = $this->getAccessToken();

        $body = [
            'filter' => [
                'type'   => 'preDispatch', // preDispatch, postDispatch
                'states' => ['APPROVED', 'PACKED', 'READY_TO_DISPATCH', 'SHIPPED', 'DELIVERED'],
            ],
        ];

        if ($fromDate) {
            $body['filter']['orderDate'] = [
                'fromDate' => $fromDate,
                'toDate'   => $toDate ?? now()->toISOString(),
            ];
        }

        $response = Http::withToken($token)
            ->post("{$this->apiBase}/orders/search", $body);

        if (!$response->successful()) {
            Log::error('Flipkart getOrders failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Flipkart API error: ' . $response->status());
        }

        return $response->json();
    }

    /**
     * Get order details by order item IDs.
     */
    public function getOrderDetails(array $orderItemIds): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post("{$this->apiBase}/orders/shipments", [
                'orderItemIds' => $orderItemIds,
            ]);

        return $response->successful() ? $response->json() : [];
    }

    /**
     * Normalize Flipkart order to heyd2c format.
     */
    public static function normalizeOrder(array $shipment): array
    {
        $items = $shipment['orderItems'] ?? [];
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += (float) ($item['priceComponents']['sellingPrice'] ?? 0);
        }

        $firstItem = $items[0] ?? [];
        $orderId = $firstItem['orderId'] ?? $shipment['orderId'] ?? uniqid('FK-');

        return [
            'external_id'         => $orderId,
            'provider'            => 'flipkart',
            'marketplace'         => 'flipkart',
            'marketplace_order_id'=> $orderId,
            'order_number'        => $orderId,
            'status'              => strtolower($shipment['status'] ?? 'pending'),
            'financial_status'    => 'paid',
            'fulfillment_status'  => strtolower($shipment['status'] ?? 'pending'),
            'currency'            => 'INR',
            'subtotal'            => $subtotal,
            'total_tax'           => 0,
            'total_discount'      => 0,
            'total_shipping'      => (float) ($shipment['shippingCharge'] ?? 0),
            'total_amount'        => $subtotal,
            'marketplace_commission' => (float) ($shipment['commission'] ?? 0),
            'marketplace_fees'    => (float) ($shipment['marketplaceFee'] ?? 0),
            'customer_name'       => $shipment['buyerDetails']['name'] ?? null,
            'shipping_address'    => $shipment['deliveryAddress'] ?? null,
            'line_item_count'     => count($items),
            'placed_at'           => $firstItem['orderDate'] ?? null,
            'raw_payload'         => $shipment,
        ];
    }
}
