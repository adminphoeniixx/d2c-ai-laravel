<?php

declare(strict_types=1);

namespace App\Services\Logistics\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DelhiveryClient implements LogisticsClientInterface
{
    protected string $token;
    protected string $baseUrl;

    /**
     * @param array $credentials ['api_token' => '...', 'environment' => 'production|staging']
     */
    public function __construct(protected array $credentials)
    {
        $this->token = $credentials['api_token'] ?? '';
        $this->baseUrl = ($credentials['environment'] ?? 'production') === 'staging'
            ? 'https://staging-express.delhivery.com'
            : 'https://track.delhivery.com';
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders(['Authorization' => "Token {$this->token}"])
                ->timeout(10)
                ->get("{$this->baseUrl}/api/v1/packages/json/", [
                    'token' => $this->token,
                    'waybill' => '0000000000', // dummy
                ]);
            // 200 with empty results = valid token, 401 = invalid
            return $response->status() !== 401;
        } catch (\Throwable $e) {
            Log::error('Delhivery connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function track(string $waybill): ?array
    {
        try {
            $response = Http::withHeaders(['Authorization' => "Token {$this->token}"])
                ->timeout(15)
                ->get("{$this->baseUrl}/api/v1/packages/json/", [
                    'token' => $this->token,
                    'waybill' => $waybill,
                    'verbose' => 2,
                ]);

            if (!$response->successful()) return null;

            $data = $response->json();
            $shipment = $data['ShipmentData'][0]['Shipment'] ?? null;
            if (!$shipment) return null;

            return $this->normalizeTracking($shipment);
        } catch (\Throwable $e) {
            Log::warning('Delhivery track failed', ['waybill' => $waybill, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function trackBulk(array $waybills): array
    {
        // Delhivery supports comma-separated waybills (max 25 per call)
        $results = [];
        $chunks = array_chunk($waybills, 25);

        foreach ($chunks as $chunk) {
            try {
                $response = Http::withHeaders(['Authorization' => "Token {$this->token}"])
                    ->timeout(30)
                    ->get("{$this->baseUrl}/api/v1/packages/json/", [
                        'token' => $this->token,
                        'waybill' => implode(',', $chunk),
                        'verbose' => 2,
                    ]);

                if (!$response->successful()) continue;

                $data = $response->json();
                foreach (($data['ShipmentData'] ?? []) as $item) {
                    $shipment = $item['Shipment'] ?? null;
                    if ($shipment) {
                        $awb = $shipment['AWB'] ?? '';
                        $results[$awb] = $this->normalizeTracking($shipment);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Delhivery bulk track failed', ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    public function fetchShipments(string $fromDate, string $toDate): array
    {
        // Delhivery doesn't have a "list all shipments" API
        // Shipments come from CSV uploads or order creation
        return [];
    }

    /**
     * Check pincode serviceability
     */
    public function checkPincode(string $pincode): ?array
    {
        try {
            $response = Http::withHeaders(['Authorization' => "Token {$this->token}"])
                ->timeout(10)
                ->get("{$this->baseUrl}/c/api/pin-codes/json/", [
                    'token' => $this->token,
                    'filter_codes' => $pincode,
                ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function normalizeTracking(array $shipment): array
    {
        $status = $shipment['Status']['Status'] ?? 'Unknown';
        $scans = $shipment['Scans'] ?? [];

        // Map Delhivery statuses to our normalized statuses
        $statusMap = [
            'Delivered' => 'Delivered', 'Dispatched' => 'In Transit', 'In Transit' => 'In Transit',
            'Pending' => 'Pending', 'Manifested' => 'Manifested', 'Picked Up' => 'Picked Up',
            'RTO' => 'RTO', 'Returned' => 'RTO', 'RTO Delivered' => 'RTO',
            'Out for Delivery' => 'Out for Delivery', 'Cancellation Requested' => 'Cancelled',
        ];

        return [
            'waybill'          => $shipment['AWB'] ?? '',
            'order_id'         => $shipment['ReferenceNo'] ?? '',
            'status'           => $statusMap[$status] ?? $status,
            'raw_status'       => $status,
            'status_detail'    => $shipment['Status']['StatusType'] ?? '',
            'payment_mode'     => $shipment['OrderType'] ?? '',
            'origin'           => $shipment['Origin'] ?? '',
            'destination'      => $shipment['Destination'] ?? '',
            'charged_weight'   => (float) ($shipment['ChargedWeight'] ?? 0),
            'pickup_date'      => $shipment['PickUpDate'] ?? null,
            'delivered_date'   => $shipment['Status']['StatusDateTime'] ?? null,
            'expected_date'    => $shipment['ExpectedDeliveryDate'] ?? null,
            'attempt_count'    => (int) ($shipment['NoOfAttempts'] ?? 0),
            'cod_amount'       => (float) ($shipment['CODAmount'] ?? 0),
            'scans'            => array_map(fn ($s) => [
                'location'  => $s['ScanDetail']['ScannedLocation'] ?? '',
                'status'    => $s['ScanDetail']['Scan'] ?? '',
                'timestamp' => $s['ScanDetail']['ScanDateTime'] ?? '',
                'remarks'   => $s['ScanDetail']['Instructions'] ?? '',
            ], $scans),
        ];
    }
}
