<?php

declare(strict_types=1);

namespace App\Services\Logistics\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EcomExpressClient implements LogisticsClientInterface
{
    protected string $username;
    protected string $password;
    protected string $baseUrl;

    public function __construct(protected array $credentials)
    {
        $this->username = $credentials['username'] ?? '';
        $this->password = $credentials['password'] ?? '';
        $this->baseUrl = ($credentials['environment'] ?? 'production') === 'staging'
            ? 'https://clbeta.ecomexpress.in'
            : 'https://api.ecomexpress.in';
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::asForm()->timeout(10)->post("{$this->baseUrl}/apiv2/track_me/", [
                'username' => $this->username,
                'password' => $this->password,
                'awb' => '0000000000',
            ]);
            return $response->status() !== 401 && $response->status() !== 403;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function track(string $waybill): ?array
    {
        try {
            $response = Http::asForm()->timeout(15)->post("{$this->baseUrl}/apiv2/track_me/", [
                'username' => $this->username,
                'password' => $this->password,
                'awb' => $waybill,
            ]);

            if (!$response->successful()) return null;
            $data = $response->json();
            if (empty($data)) return null;

            $shipment = is_array($data) ? (reset($data) ?: null) : null;
            if (!$shipment) return null;

            return $this->normalizeTracking($shipment, $waybill);
        } catch (\Throwable $e) {
            Log::warning('EcomExpress track failed', ['waybill' => $waybill, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function trackBulk(array $waybills): array
    {
        // Ecom Express supports comma-separated AWBs
        $results = [];
        $chunks = array_chunk($waybills, 25);

        foreach ($chunks as $chunk) {
            try {
                $response = Http::asForm()->timeout(30)->post("{$this->baseUrl}/apiv2/track_me/", [
                    'username' => $this->username,
                    'password' => $this->password,
                    'awb' => implode(',', $chunk),
                ]);

                if (!$response->successful()) continue;
                $data = $response->json();

                foreach ($data as $awb => $shipment) {
                    if (is_array($shipment)) {
                        $results[$awb] = $this->normalizeTracking($shipment, (string) $awb);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('EcomExpress bulk track failed', ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    public function fetchShipments(string $fromDate, string $toDate): array
    {
        return []; // Not supported via API — use CSV uploads
    }

    protected function normalizeTracking(array $shipment, string $waybill): array
    {
        $scans = $shipment['scans'] ?? [];
        $lastScan = end($scans) ?: [];
        $status = $shipment['status'] ?? $lastScan['status'] ?? 'Unknown';

        $statusMap = [
            'Delivered' => 'Delivered', 'RTO' => 'RTO', 'RTO-Delivered' => 'RTO',
            'Pending Pickup' => 'Pending', 'Picked Up' => 'Picked Up',
            'In Transit' => 'In Transit', 'Out for Delivery' => 'Out for Delivery',
            'Undelivered' => 'Failed Delivery', 'Cancelled' => 'Cancelled',
        ];

        return [
            'waybill'        => $waybill,
            'order_id'       => $shipment['order_number'] ?? '',
            'status'         => $statusMap[$status] ?? $status,
            'raw_status'     => $status,
            'payment_mode'   => $shipment['payment_mode'] ?? '',
            'origin'         => $shipment['from'] ?? '',
            'destination'    => $shipment['to'] ?? '',
            'delivered_date' => $shipment['delivery_date'] ?? null,
            'expected_date'  => $shipment['expected_date'] ?? null,
            'cod_amount'     => (float) ($shipment['cod_value'] ?? 0),
            'scans'          => array_map(fn ($s) => [
                'location'  => $s['location'] ?? '',
                'status'    => $s['status'] ?? '',
                'timestamp' => $s['updated_on'] ?? $s['date'] ?? '',
                'remarks'   => $s['reason_code_description'] ?? '',
            ], $scans),
        ];
    }
}
