<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DelhiveryService
{
    protected string $token;
    protected string $baseUrl;

    public function __construct(string $token, string $baseUrl = 'https://track.delhivery.com')
    {
        $this->token = $token;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /* ── Tracking ──────────────────────────────────────── */

    /**
     * Track single shipment by AWB.
     * Rate limit: 750 requests / 5 min per IP.
     */
    public function trackShipment(string $waybill): ?array
    {
        $response = Http::timeout(15)
            ->withHeaders(['Authorization' => "Token {$this->token}"])
            ->get("{$this->baseUrl}/api/v1/packages/json/", [
                'waybill' => $waybill,
            ]);

        if ($response->failed()) {
            Log::warning('Delhivery track failed', ['waybill' => $waybill, 'status' => $response->status()]);
            return null;
        }

        $data = $response->json();
        return $data['ShipmentData'][0]['Shipment'] ?? null;
    }

    /**
     * Track multiple shipments (comma-separated AWBs, max 25 per call).
     */
    public function trackBulk(array $waybills): array
    {
        $results = [];
        $chunks = array_chunk($waybills, 25);

        foreach ($chunks as $chunk) {
            $response = Http::timeout(30)
                ->withHeaders(['Authorization' => "Token {$this->token}"])
                ->get("{$this->baseUrl}/api/v1/packages/json/", [
                    'waybill' => implode(',', $chunk),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                foreach (($data['ShipmentData'] ?? []) as $item) {
                    $shipment = $item['Shipment'] ?? null;
                    if ($shipment) {
                        $awb = $shipment['AWB'] ?? '';
                        $results[$awb] = $shipment;
                    }
                }
            }

            // Rate limit: sleep 500ms between chunks
            if (count($chunks) > 1) usleep(500000);
        }

        return $results;
    }

    /* ── Pincode Serviceability ─────────────────────────── */

    /**
     * Check if a pincode is serviceable.
     * Returns: prepaid, cod, pickup, repl, cod_limit, etc.
     */
    public function checkPincode(string $pincode): ?array
    {
        $response = Http::timeout(10)
            ->withHeaders(['Authorization' => "Token {$this->token}"])
            ->get("{$this->baseUrl}/c/api/pin-codes/json/", [
                'filter_codes' => $pincode,
            ]);

        if ($response->failed()) return null;

        $data = $response->json();
        return $data['delivery_codes'][0]['postal_code'] ?? null;
    }

    /**
     * Bulk pincode check (comma-separated).
     */
    public function checkPincodes(array $pincodes): array
    {
        $response = Http::timeout(15)
            ->withHeaders(['Authorization' => "Token {$this->token}"])
            ->get("{$this->baseUrl}/c/api/pin-codes/json/", [
                'filter_codes' => implode(',', $pincodes),
            ]);

        if ($response->failed()) return [];

        $results = [];
        foreach (($response->json()['delivery_codes'] ?? []) as $item) {
            $pc = $item['postal_code'] ?? null;
            if ($pc) $results[$pc['pin']] = $pc;
        }
        return $results;
    }

    /* ── Shipping Cost Calculator ──────────────────────── */

    /**
     * Calculate shipping charge for a shipment.
     */
    public function calculateShippingCost(array $params): ?array
    {
        // params: md (mode: E=Express,S=Surface), ss (origin), ds (dest), wt (weight grams), pt (COD/Pre-paid), cod (cod amount)
        $response = Http::timeout(10)
            ->withHeaders(['Authorization' => "Token {$this->token}"])
            ->get("{$this->baseUrl}/api/kinko/v1/invoice/charges/.json", $params);

        if ($response->failed()) return null;
        return $response->json();
    }

    /* ── Warehouse Management ──────────────────────────── */

    /**
     * Fetch all registered warehouses/pickup locations.
     */
    public function getWarehouses(): array
    {
        $response = Http::timeout(15)
            ->withHeaders(['Authorization' => "Token {$this->token}"])
            ->get("{$this->baseUrl}/api/backend/clientwarehouse/");

        if ($response->failed()) return [];
        return $response->json() ?? [];
    }

    /* ── Order Management ──────────────────────────────── */

    /**
     * Create a forward order.
     */
    public function createOrder(array $orderData): ?array
    {
        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => "Token {$this->token}",
                'Content-Type'  => 'application/json',
            ])
            ->post("{$this->baseUrl}/api/cmu/create.json", [
                'format'   => 'json',
                'data'     => json_encode($orderData),
                'pickup_location' => [
                    'name' => $orderData['pickup_name'] ?? 'Default',
                ],
            ]);

        if ($response->failed()) {
            Log::error('Delhivery create order failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }
        return $response->json();
    }

    /**
     * Cancel an order by waybill.
     */
    public function cancelOrder(string $waybill): ?array
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => "Token {$this->token}",
                'Content-Type'  => 'application/json',
            ])
            ->post("{$this->baseUrl}/api/p/edit", [
                'waybill' => $waybill,
                'cancellation' => true,
            ]);

        if ($response->failed()) return null;
        return $response->json();
    }

    /* ── Helpers ────────────────────────────────────────── */

    /**
     * Parse Delhivery status to our normalized status.
     */
    public static function normalizeStatus(string $rawStatus): string
    {
        $status = strtolower(trim($rawStatus));

        return match (true) {
            str_contains($status, 'delivered')                      => 'Delivered',
            str_contains($status, 'rto')                            => 'RTO',
            str_contains($status, 'return')                         => 'RTO',
            str_contains($status, 'in transit')                     => 'In Transit',
            str_contains($status, 'dispatched')                     => 'In Transit',
            str_contains($status, 'out for delivery')               => 'Out For Delivery',
            str_contains($status, 'pending')                        => 'Pending',
            str_contains($status, 'manifested')                     => 'Manifested',
            str_contains($status, 'picked up')                      => 'Picked Up',
            str_contains($status, 'not picked')                     => 'Pickup Failed',
            str_contains($status, 'undelivered')                    => 'NDR',
            str_contains($status, 'cancelled')                      => 'Cancelled',
            default                                                 => ucfirst($rawStatus),
        };
    }

    /**
     * Extract key metrics from a shipment tracking response.
     */
    public static function extractMetrics(array $shipment): array
    {
        $scans = $shipment['Scans'] ?? [];
        $status = $shipment['Status']['Status'] ?? 'Unknown';
        $originPin = $shipment['OriginArea'] ?? '';
        $destPin = $shipment['DestinationArea'] ?? '';

        // Calculate delivery days
        $pickupDate = null;
        $deliveredDate = null;
        foreach ($scans as $scan) {
            $scanType = $scan['ScanDetail']['ScanType'] ?? '';
            $scanDate = $scan['ScanDetail']['ScanDateTime'] ?? '';
            if ($scanType === 'UD' && !$pickupDate) $pickupDate = $scanDate; // first scan
            if (str_contains(strtolower($scan['ScanDetail']['Instructions'] ?? ''), 'delivered')) $deliveredDate = $scanDate;
        }

        $deliveryDays = null;
        if ($pickupDate && $deliveredDate) {
            try {
                $deliveryDays = \Carbon\Carbon::parse($pickupDate)->diffInDays(\Carbon\Carbon::parse($deliveredDate));
            } catch (\Exception $e) {}
        }

        return [
            'status'          => self::normalizeStatus($status),
            'raw_status'      => $status,
            'origin_pincode'  => $originPin,
            'dest_pincode'    => $destPin,
            'delivery_days'   => $deliveryDays,
            'scan_count'      => count($scans),
            'last_scan'       => $scans[0]['ScanDetail']['Instructions'] ?? null,
            'last_scan_at'    => $scans[0]['ScanDetail']['ScanDateTime'] ?? null,
            'is_rto'          => str_contains(strtolower($status), 'rto') || str_contains(strtolower($status), 'return'),
            'payment_mode'    => $shipment['OrderType'] ?? null,
            'charged_weight'  => $shipment['ChargedWeight'] ?? null,
        ];
    }

    /**
     * Verify API token is valid.
     */
    public function verifyToken(): bool
    {
        $response = Http::timeout(10)
            ->withHeaders(['Authorization' => "Token {$this->token}"])
            ->get("{$this->baseUrl}/api/v1/packages/json/", ['waybill' => 'TEST000000']);

        // Even a "not found" response means auth worked
        return $response->status() !== 401 && $response->status() !== 403;
    }
}
