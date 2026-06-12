<?php

declare(strict_types=1);

namespace App\Services\Logistics\Clients;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketClient implements LogisticsClientInterface
{
    protected string $email;
    protected string $password;
    protected string $baseUrl = 'https://apiv2.shiprocket.in/v1/external';

    public function __construct(protected array $credentials)
    {
        $this->email = $credentials['email'] ?? '';
        $this->password = $credentials['password'] ?? '';
    }

    protected function getToken(): ?string
    {
        $cacheKey = 'shiprocket_token:' . md5($this->email);
        $cached = Cache::get($cacheKey);
        if ($cached) return $cached;

        try {
            $response = Http::post("{$this->baseUrl}/auth/login", [
                'email' => $this->email,
                'password' => $this->password,
            ]);

            if ($response->successful()) {
                $token = $response->json('token');
                Cache::put($cacheKey, $token, 86400 * 9); // 9 days (token valid 10 days)
                return $token;
            }
        } catch (\Throwable $e) {
            Log::error('Shiprocket auth failed', ['error' => $e->getMessage()]);
        }
        return null;
    }

    protected function request(): \Illuminate\Http\Client\PendingRequest
    {
        $token = $this->getToken();
        return Http::baseUrl($this->baseUrl)
            ->withToken($token ?? '')
            ->acceptJson()
            ->timeout(30);
    }

    public function testConnection(): bool
    {
        return $this->getToken() !== null;
    }

    public function track(string $waybill): ?array
    {
        try {
            $response = $this->request()->get("/courier/track/awb/{$waybill}");
            if (!$response->successful()) return null;

            $data = $response->json();
            $tracking = $data['tracking_data'] ?? null;
            if (!$tracking) return null;

            return $this->normalizeTracking($tracking, $waybill);
        } catch (\Throwable $e) {
            Log::warning('Shiprocket track failed', ['waybill' => $waybill, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function trackBulk(array $waybills): array
    {
        $results = [];
        // Shiprocket tracks one at a time
        foreach ($waybills as $awb) {
            $result = $this->track($awb);
            if ($result) $results[$awb] = $result;
        }
        return $results;
    }

    public function fetchShipments(string $fromDate, string $toDate): array
    {
        $shipments = [];
        $page = 1;

        try {
            do {
                $response = $this->request()->get('/orders', [
                    'page' => $page,
                    'per_page' => 50,
                    'from' => $fromDate,
                    'to' => $toDate,
                ]);

                if (!$response->successful()) break;

                $data = $response->json();
                $orders = $data['data'] ?? [];

                foreach ($orders as $order) {
                    $shipments[] = $this->normalizeShipment($order);
                }

                $meta = $data['meta'] ?? [];
                $page++;
            } while ($page <= ($meta['last_page'] ?? 1));
        } catch (\Throwable $e) {
            Log::error('Shiprocket fetch shipments failed', ['error' => $e->getMessage()]);
        }

        return $shipments;
    }

    /**
     * Get all available courier partners for a shipment
     */
    public function getCourierServiceability(int $pickupPincode, int $deliveryPincode, float $weight, bool $cod = false): array
    {
        try {
            $response = $this->request()->get('/courier/serviceability/', [
                'pickup_postcode' => $pickupPincode,
                'delivery_postcode' => $deliveryPincode,
                'weight' => $weight,
                'cod' => $cod ? 1 : 0,
            ]);

            return $response->successful() ? ($response->json('data.available_courier_companies') ?? []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function normalizeTracking(array $tracking, string $waybill): array
    {
        $shipment = $tracking['shipment_track'][0] ?? [];
        $activities = $tracking['shipment_track_activities'] ?? [];

        $statusMap = [
            1 => 'Pending', 2 => 'Picked Up', 3 => 'In Transit', 4 => 'Out for Delivery',
            5 => 'Delivered', 6 => 'RTO', 7 => 'RTO', 8 => 'Cancelled', 9 => 'Lost',
        ];

        $statusCode = (int) ($shipment['current_status_id'] ?? 0);

        return [
            'waybill'        => $waybill,
            'order_id'       => $shipment['order_id'] ?? '',
            'status'         => $statusMap[$statusCode] ?? ($shipment['current_status'] ?? 'Unknown'),
            'raw_status'     => $shipment['current_status'] ?? '',
            'origin'         => $shipment['pickup_city'] ?? '',
            'destination'    => $shipment['delivered_to'] ?? '',
            'courier'        => $shipment['courier_name'] ?? '',
            'pickup_date'    => $shipment['pickup_date'] ?? null,
            'delivered_date' => $shipment['delivered_date'] ?? null,
            'expected_date'  => $shipment['edd'] ?? null,
            'cod_amount'     => (float) ($shipment['cod'] ?? 0),
            'scans'          => array_map(fn ($a) => [
                'location'  => $a['location'] ?? '',
                'status'    => $a['activity'] ?? '',
                'timestamp' => $a['date'] ?? '',
                'remarks'   => $a['sr-status-label'] ?? '',
            ], $activities),
        ];
    }

    protected function normalizeShipment(array $order): array
    {
        return [
            'waybill'       => $order['shipments'][0]['awb_code'] ?? '',
            'order_id'      => (string) ($order['channel_order_id'] ?? $order['id'] ?? ''),
            'status'        => $order['status'] ?? 'Unknown',
            'payment_mode'  => ($order['payment_method'] ?? '') === 'COD' ? 'COD' : 'Pre-paid',
            'product_value' => (float) ($order['sub_total'] ?? 0),
            'cod_amount'    => (float) ($order['payment_method'] === 'COD' ? ($order['sub_total'] ?? 0) : 0),
            'courier'       => $order['shipments'][0]['courier_name'] ?? '',
            'pickup_date'   => $order['shipments'][0]['pickup_scheduled_date'] ?? null,
            'item_shipped'  => collect($order['order_items'] ?? [])->pluck('name')->implode(', '),
            'qty'           => (int) collect($order['order_items'] ?? [])->sum('qty'),
            'destination_pin' => $order['customer_pincode'] ?? '',
        ];
    }
}
