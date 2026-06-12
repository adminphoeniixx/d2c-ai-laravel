<?php

declare(strict_types=1);

namespace App\Services\Logistics\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlueDartClient implements LogisticsClientInterface
{
    protected string $apiKey;
    protected string $licenseKey;
    protected string $baseUrl = 'https://api.bluedart.com/servlet/RoutingServlet';

    public function __construct(protected array $credentials)
    {
        $this->apiKey = $credentials['api_key'] ?? '';
        $this->licenseKey = $credentials['license_key'] ?? '';
    }

    public function testConnection(): bool
    {
        return !empty($this->apiKey) && !empty($this->licenseKey);
    }

    public function track(string $waybill): ?array
    {
        try {
            // BlueDart has a REST tracking API
            $response = Http::timeout(15)->get('https://api.bluedart.com/servlet/RoutingServlet', [
                'handler' => 'tnt',
                'action' => 'cuaborand',
                'loginid' => $this->licenseKey,
                'awession' => $this->apiKey,
                'numbers' => $waybill,
                'format' => 'json',
                'lickey' => $this->licenseKey,
                'vession' => '1.3',
                'scan' => 1,
            ]);

            if (!$response->successful()) return null;
            $data = $response->json();

            return $this->normalizeTracking($data, $waybill);
        } catch (\Throwable $e) {
            Log::warning('BlueDart track failed', ['waybill' => $waybill, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function trackBulk(array $waybills): array
    {
        // BlueDart supports comma-separated (max 5)
        $results = [];
        foreach (array_chunk($waybills, 5) as $chunk) {
            try {
                $response = Http::timeout(30)->get($this->baseUrl, [
                    'handler' => 'tnt', 'action' => 'cuaborand',
                    'loginid' => $this->licenseKey, 'awession' => $this->apiKey,
                    'numbers' => implode(',', $chunk), 'format' => 'json',
                    'lickey' => $this->licenseKey, 'vession' => '1.3', 'scan' => 1,
                ]);
                if (!$response->successful()) continue;
                $data = $response->json();
                // Parse response per waybill
                foreach ($chunk as $awb) {
                    $results[$awb] = $this->normalizeTracking($data, $awb);
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return $results;
    }

    public function fetchShipments(string $fromDate, string $toDate): array { return []; }

    protected function normalizeTracking(array $data, string $waybill): array
    {
        return [
            'waybill' => $waybill, 'order_id' => '', 'status' => $data['Status'] ?? 'Unknown',
            'raw_status' => $data['StatusType'] ?? '', 'scans' => [],
        ];
    }
}

// ─────────────────────────────────────────────

class DtdcClient implements LogisticsClientInterface
{
    protected string $apiKey;
    protected string $baseUrl = 'https://blaborama.dtdc.com/dtdc-api/rest/JSONCnTrk';

    public function __construct(protected array $credentials)
    {
        $this->apiKey = $credentials['api_key'] ?? '';
    }

    public function testConnection(): bool { return !empty($this->apiKey); }

    public function track(string $waybill): ?array
    {
        try {
            $response = Http::withHeaders(['x-access-token' => $this->apiKey])
                ->timeout(15)
                ->post($this->baseUrl . '/getTrackDetails', [
                    'TrkType' => 'cnno', 'strcnno' => $waybill, 'addtnlDtl' => 'Y',
                ]);

            if (!$response->successful()) return null;
            $data = $response->json();
            $tracking = $data['trackDetails'][0] ?? null;
            if (!$tracking) return null;

            $statusMap = ['Delivered' => 'Delivered', 'In Transit' => 'In Transit', 'Booked' => 'Manifested',
                'Out for Delivery' => 'Out for Delivery', 'RTO' => 'RTO'];
            $status = $tracking['strStatus'] ?? 'Unknown';

            return [
                'waybill' => $waybill, 'order_id' => $tracking['strRefNo'] ?? '',
                'status' => $statusMap[$status] ?? $status, 'raw_status' => $status,
                'origin' => $tracking['strOrigin'] ?? '', 'destination' => $tracking['strDestination'] ?? '',
                'delivered_date' => $tracking['strDeliveryDate'] ?? null,
                'expected_date' => $tracking['strExpectedDelivery'] ?? null,
                'scans' => array_map(fn ($s) => [
                    'location' => $s['strActivity'] ?? '', 'status' => $s['strAction'] ?? '',
                    'timestamp' => $s['strActionDate'] ?? '', 'remarks' => '',
                ], $tracking['strChildDetails'] ?? []),
            ];
        } catch (\Throwable $e) {
            Log::warning('DTDC track failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function trackBulk(array $waybills): array
    {
        $results = [];
        foreach ($waybills as $awb) {
            $result = $this->track($awb);
            if ($result) $results[$awb] = $result;
        }
        return $results;
    }

    public function fetchShipments(string $fromDate, string $toDate): array { return []; }
}

// ─────────────────────────────────────────────

class XpressbeesClient implements LogisticsClientInterface
{
    protected string $token;
    protected string $baseUrl = 'https://ship.xpressbees.com/api';

    public function __construct(protected array $credentials)
    {
        $this->token = $credentials['api_token'] ?? '';
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::withToken($this->token)->timeout(10)->get("{$this->baseUrl}/users/me");
            return $response->successful();
        } catch (\Throwable $e) { return false; }
    }

    public function track(string $waybill): ?array
    {
        try {
            $response = Http::withToken($this->token)->timeout(15)
                ->get("{$this->baseUrl}/shipments2/track/{$waybill}");

            if (!$response->successful()) return null;
            $data = $response->json('data');
            if (!$data) return null;

            $statusMap = ['DL' => 'Delivered', 'RT' => 'RTO', 'OFD' => 'Out for Delivery',
                'IT' => 'In Transit', 'PKD' => 'Picked Up', 'MAN' => 'Manifested'];

            return [
                'waybill' => $waybill, 'order_id' => $data['order_id'] ?? '',
                'status' => $statusMap[$data['status_code'] ?? ''] ?? ($data['status'] ?? 'Unknown'),
                'raw_status' => $data['status'] ?? '',
                'payment_mode' => $data['payment_type'] ?? '',
                'origin' => $data['origin'] ?? '', 'destination' => $data['destination'] ?? '',
                'delivered_date' => $data['delivery_date'] ?? null,
                'expected_date' => $data['expected_delivery_date'] ?? null,
                'cod_amount' => (float) ($data['cod_amount'] ?? 0),
                'scans' => array_map(fn ($s) => [
                    'location' => $s['location'] ?? '', 'status' => $s['status'] ?? '',
                    'timestamp' => $s['timestamp'] ?? '', 'remarks' => $s['remark'] ?? '',
                ], $data['scans'] ?? []),
            ];
        } catch (\Throwable $e) {
            Log::warning('Xpressbees track failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function trackBulk(array $waybills): array
    {
        $results = [];
        foreach ($waybills as $awb) {
            $result = $this->track($awb);
            if ($result) $results[$awb] = $result;
        }
        return $results;
    }

    public function fetchShipments(string $fromDate, string $toDate): array { return []; }
}
