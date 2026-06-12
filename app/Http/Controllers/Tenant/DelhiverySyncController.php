<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DeliveryPartner;
use App\Models\Tenant\LogisticsShipment;
use App\Services\DelhiveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DelhiverySyncController extends Controller
{
    /**
     * Connect Delhivery — save API token and verify.
     */
    public function connect(Request $request)
    {
        $request->validate([
            'api_token' => 'required|string|min:10',
        ]);

        $token = $request->api_token;
        $service = new DelhiveryService($token);

        if (!$service->verifyToken()) {
            return response()->json(['success' => false, 'error' => 'Invalid API token. Check your Delhivery One dashboard.'], 422);
        }

        // Find or create Delhivery partner
        $partner = DeliveryPartner::updateOrCreate(
            ['slug' => 'delhivery'],
            [
                'name'            => 'Delhivery',
                'api_base_url'    => 'https://track.delhivery.com',
                'api_credentials' => encrypt(json_encode(['token' => $token])),
                'api_connected'   => true,
                'is_active'       => true,
            ]
        );

        return response()->json(['success' => true, 'partner_id' => $partner->id]);
    }

    /**
     * Disconnect Delhivery API.
     */
    public function disconnect()
    {
        $partner = DeliveryPartner::where('slug', 'delhivery')->first();
        if ($partner) {
            $partner->update(['api_connected' => false, 'api_credentials' => null]);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Check connection status.
     */
    public function status()
    {
        $partner = DeliveryPartner::where('slug', 'delhivery')->first();
        return response()->json([
            'connected' => $partner?->api_connected ?? false,
            'partner_id' => $partner?->id,
        ]);
    }

    /**
     * Sync all active shipments — fetch latest tracking status.
     * Call this on-demand or via scheduler.
     */
    public function sync()
    {
        $partner = DeliveryPartner::where('slug', 'delhivery')->where('api_connected', true)->first();
        if (!$partner) {
            return response()->json(['success' => false, 'error' => 'Delhivery not connected'], 422);
        }

        $service = $this->getService($partner);
        if (!$service) return response()->json(['success' => false, 'error' => 'Invalid credentials'], 422);

        // Fetch all non-terminal shipments
        $activeShipments = LogisticsShipment::where('delivery_partner_id', $partner->id)
            ->whereNotIn('status', ['Delivered', 'RTO', 'Cancelled'])
            ->pluck('waybill')
            ->toArray();

        if (empty($activeShipments)) {
            return response()->json(['success' => true, 'synced' => 0, 'message' => 'No active shipments to sync']);
        }

        $tracked = $service->trackBulk($activeShipments);
        $updated = 0;

        foreach ($tracked as $awb => $shipmentData) {
            $metrics = DelhiveryService::extractMetrics($shipmentData);

            $shipment = LogisticsShipment::where('waybill', $awb)
                ->where('delivery_partner_id', $partner->id)
                ->first();

            if ($shipment) {
                $shipment->update([
                    'status'          => $metrics['status'],
                    'origin_pincode'  => $metrics['origin_pincode'] ?: $shipment->origin_pincode,
                    'dest_pincode'    => $metrics['dest_pincode'] ?: $shipment->dest_pincode,
                    'charged_weight'  => $metrics['charged_weight'] ?: $shipment->charged_weight,
                    'payment_mode'    => $metrics['payment_mode'] ?: $shipment->payment_mode,
                    'delivery_days'   => $metrics['delivery_days'],
                    'last_scan'       => $metrics['last_scan'],
                    'last_scan_at'    => $metrics['last_scan_at'] ? \Carbon\Carbon::parse($metrics['last_scan_at']) : null,
                    'is_rto'          => $metrics['is_rto'],
                    'tracking_data'   => $shipmentData,
                ]);
                $updated++;
            }
        }

        Log::info("Delhivery sync: {$updated} shipments updated out of " . count($activeShipments));

        return response()->json([
            'success' => true,
            'synced'  => $updated,
            'total'   => count($activeShipments),
        ]);
    }

    /**
     * Track a single shipment and return full details.
     */
    public function track(Request $request, $tenant, $waybill)
    {
        $partner = DeliveryPartner::where('slug', 'delhivery')->where('api_connected', true)->first();
        if (!$partner) return response()->json(['error' => 'Delhivery not connected'], 422);

        $service = $this->getService($partner);
        if (!$service) return response()->json(['error' => 'Invalid credentials'], 422);

        $data = $service->trackShipment($waybill);
        if (!$data) return response()->json(['error' => 'Shipment not found'], 404);

        $metrics = DelhiveryService::extractMetrics($data);

        // Update local record if exists
        $shipment = LogisticsShipment::where('waybill', $waybill)->where('delivery_partner_id', $partner->id)->first();
        if ($shipment) {
            $shipment->update([
                'status'        => $metrics['status'],
                'delivery_days' => $metrics['delivery_days'],
                'last_scan'     => $metrics['last_scan'],
                'last_scan_at'  => $metrics['last_scan_at'] ? \Carbon\Carbon::parse($metrics['last_scan_at']) : null,
                'is_rto'        => $metrics['is_rto'],
                'tracking_data' => $data,
            ]);
        }

        return response()->json([
            'shipment' => $data,
            'metrics'  => $metrics,
            'scans'    => $data['Scans'] ?? [],
        ]);
    }

    /**
     * Check pincode serviceability.
     */
    public function checkPincode(Request $request)
    {
        $request->validate(['pincode' => 'required|digits:6']);

        $partner = DeliveryPartner::where('slug', 'delhivery')->where('api_connected', true)->first();
        if (!$partner) return response()->json(['error' => 'Delhivery not connected'], 422);

        $service = $this->getService($partner);
        $result = $service->checkPincode($request->pincode);

        if (!$result) return response()->json(['error' => 'Could not check pincode'], 422);

        return response()->json($result);
    }

    /**
     * Calculate shipping cost.
     */
    public function calculateCost(Request $request)
    {
        $request->validate([
            'origin_pin' => 'required|digits:6',
            'dest_pin'   => 'required|digits:6',
            'weight'     => 'required|numeric|min:1', // grams
            'mode'       => 'nullable|in:E,S', // Express or Surface
            'cod_amount' => 'nullable|numeric',
        ]);

        $partner = DeliveryPartner::where('slug', 'delhivery')->where('api_connected', true)->first();
        if (!$partner) return response()->json(['error' => 'Delhivery not connected'], 422);

        $service = $this->getService($partner);
        $result = $service->calculateShippingCost([
            'md' => $request->mode ?? 'E',
            'ss' => $request->origin_pin,
            'ds' => $request->dest_pin,
            'wt' => $request->weight,
            'pt' => $request->cod_amount ? 'COD' : 'Pre-paid',
            'cod' => $request->cod_amount ?? 0,
        ]);

        if (!$result) return response()->json(['error' => 'Could not calculate cost'], 422);

        return response()->json($result);
    }

    /**
     * Import shipments from Delhivery — fetch recent orders and create local records.
     */
    public function importShipments(Request $request)
    {
        $request->validate([
            'waybills' => 'required|array|min:1|max:100',
            'waybills.*' => 'string',
        ]);

        $partner = DeliveryPartner::where('slug', 'delhivery')->where('api_connected', true)->first();
        if (!$partner) return response()->json(['error' => 'Delhivery not connected'], 422);

        $service = $this->getService($partner);
        $tracked = $service->trackBulk($request->waybills);
        $imported = 0;

        foreach ($tracked as $awb => $shipmentData) {
            $metrics = DelhiveryService::extractMetrics($shipmentData);

            LogisticsShipment::updateOrCreate(
                ['waybill' => $awb, 'delivery_partner_id' => $partner->id],
                [
                    'status'         => $metrics['status'],
                    'origin_pincode' => $metrics['origin_pincode'],
                    'dest_pincode'   => $metrics['dest_pincode'],
                    'payment_mode'   => $metrics['payment_mode'],
                    'charged_weight' => $metrics['charged_weight'],
                    'delivery_days'  => $metrics['delivery_days'],
                    'last_scan'      => $metrics['last_scan'],
                    'last_scan_at'   => $metrics['last_scan_at'] ? \Carbon\Carbon::parse($metrics['last_scan_at']) : null,
                    'is_rto'         => $metrics['is_rto'],
                    'tracking_data'  => $shipmentData,
                ]
            );
            $imported++;
        }

        return response()->json(['success' => true, 'imported' => $imported]);
    }

    /**
     * Get analytics data for the logistics dashboard.
     */
    public function analytics(Request $request)
    {
        $partner = DeliveryPartner::where('slug', 'delhivery')->first();
        if (!$partner) return response()->json(['error' => 'No Delhivery partner'], 422);

        $query = LogisticsShipment::where('delivery_partner_id', $partner->id);

        // Date filter
        if ($request->filled('from')) $query->where('created_at', '>=', $request->from);
        if ($request->filled('to')) $query->where('created_at', '<=', $request->to . ' 23:59:59');

        $shipments = $query->get();
        $total = $shipments->count();

        if ($total === 0) {
            return response()->json([
                'total' => 0, 'delivered' => 0, 'in_transit' => 0, 'rto' => 0,
                'rto_percent' => 0, 'avg_delivery_days' => 0, 'ndr' => 0,
                'cod_count' => 0, 'prepaid_count' => 0,
                'pincode_breakdown' => [], 'status_breakdown' => [], 'zone_breakdown' => [],
            ]);
        }

        $delivered = $shipments->where('status', 'Delivered')->count();
        $rto = $shipments->filter(fn($s) => $s->is_rto)->count();
        $inTransit = $shipments->where('status', 'In Transit')->count();
        $ofd = $shipments->where('status', 'Out For Delivery')->count();
        $ndr = $shipments->where('status', 'NDR')->count();
        $cancelled = $shipments->where('status', 'Cancelled')->count();

        $avgDays = $shipments->where('status', 'Delivered')
            ->whereNotNull('delivery_days')
            ->avg('delivery_days');

        $codCount = $shipments->where('payment_mode', 'COD')->count();
        $prepaidCount = $shipments->where('payment_mode', 'Pre-paid')->count();

        // RTO by payment mode
        $rtoCod = $shipments->where('payment_mode', 'COD')->filter(fn($s) => $s->is_rto)->count();
        $rtoPrepaid = $shipments->where('payment_mode', 'Pre-paid')->filter(fn($s) => $s->is_rto)->count();

        // Top RTO pincodes
        $rtoPincodes = $shipments->filter(fn($s) => $s->is_rto)
            ->groupBy('dest_pincode')
            ->map(fn($group) => $group->count())
            ->sortDesc()
            ->take(10)
            ->toArray();

        // Status breakdown
        $statusBreakdown = $shipments->groupBy('status')
            ->map(fn($group) => $group->count())
            ->toArray();

        // Zone breakdown
        $zoneBreakdown = $shipments->groupBy('zone')
            ->map(fn($group) => [
                'total'     => $group->count(),
                'delivered' => $group->where('status', 'Delivered')->count(),
                'rto'       => $group->filter(fn($s) => $s->is_rto)->count(),
                'avg_days'  => round($group->where('status', 'Delivered')->whereNotNull('delivery_days')->avg('delivery_days') ?? 0, 1),
            ])
            ->toArray();

        // Delivery success by dest pincode (top 20 by volume)
        $pincodeBreakdown = $shipments->groupBy('dest_pincode')
            ->map(fn($group) => [
                'total'     => $group->count(),
                'delivered' => $group->where('status', 'Delivered')->count(),
                'rto'       => $group->filter(fn($s) => $s->is_rto)->count(),
                'success_rate' => $group->count() > 0
                    ? round($group->where('status', 'Delivered')->count() / $group->count() * 100, 1) : 0,
            ])
            ->sortByDesc('total')
            ->take(20)
            ->toArray();

        return response()->json([
            'total'           => $total,
            'delivered'       => $delivered,
            'in_transit'      => $inTransit + $ofd,
            'rto'             => $rto,
            'rto_percent'     => $total > 0 ? round($rto / $total * 100, 1) : 0,
            'ndr'             => $ndr,
            'cancelled'       => $cancelled,
            'avg_delivery_days' => round($avgDays ?? 0, 1),
            'cod_count'       => $codCount,
            'prepaid_count'   => $prepaidCount,
            'rto_cod'         => $rtoCod,
            'rto_prepaid'     => $rtoPrepaid,
            'rto_cod_percent' => $codCount > 0 ? round($rtoCod / $codCount * 100, 1) : 0,
            'rto_prepaid_percent' => $prepaidCount > 0 ? round($rtoPrepaid / $prepaidCount * 100, 1) : 0,
            'top_rto_pincodes'    => $rtoPincodes,
            'status_breakdown'    => $statusBreakdown,
            'zone_breakdown'      => $zoneBreakdown,
            'pincode_breakdown'   => $pincodeBreakdown,
        ]);
    }

    /**
     * Fetch all shipments from API by date range and upsert into DB.
     * Called from LogisticsController when user clicks "Fetch Orders".
     */
    public function fetchAll(Request $request, $tenant)
    {
        $partner = DeliveryPartner::where('slug', 'delhivery')->where('api_connected', true)->first();
        if (!$partner) {
            return back()->withErrors(['sync' => 'Delhivery not connected.']);
        }

        $service = $this->getService($partner);
        if (!$service) {
            return back()->withErrors(['sync' => 'Invalid credentials. Reconnect API.']);
        }

        // Default: last 90 days
        $from = $request->input('from', now()->subDays(90)->format('Y-m-d'));
        $to   = $request->input('to',   now()->format('Y-m-d'));

        try {
            $shipments = $service->fetchShipmentsByDate($from, $to, 1000);
        } catch (\Exception $e) {
            return back()->withErrors(['sync' => 'API error: ' . $e->getMessage()]);
        }

        if (empty($shipments)) {
            return back()->with('success', 'No shipments found in the selected date range.');
        }

        $imported = 0;
        foreach ($shipments as $awb => $data) {
            $metrics = DelhiveryService::extractMetrics($data);

            LogisticsShipment::updateOrCreate(
                [
                    'delivery_partner_id' => $partner->id,
                    'waybill'             => $awb,
                ],
                [
                    'status'          => $metrics['status'],
                    'payment_mode'    => $metrics['payment_mode'],
                    'zone'            => $data['Zone'] ?? null,
                    'destination_pin' => $metrics['dest_pincode'],
                    'origin_center'   => $metrics['origin_pincode'],
                    'charged_weight'  => $metrics['charged_weight'],
                    'cod_amount'      => $data['CodAmount'] ?? 0,
                    'product_value'   => $data['ItemValue'] ?? 0,
                    'origin_pincode'  => $metrics['origin_pincode'],
                    'dest_pincode'    => $metrics['dest_pincode'],
                    'delivery_days'   => $metrics['delivery_days'],
                    'is_rto'          => $metrics['is_rto'],
                    'last_scan'       => $metrics['last_scan'],
                    'last_scan_at'    => $metrics['last_scan_at'] ? \Carbon\Carbon::parse($metrics['last_scan_at']) : null,
                    'tracking_data'   => $data,
                ]
            );
            $imported++;
        }

        return back()->with('success', "Fetched {$imported} shipments from Delhivery.");
    }

    /* ── Helpers ────────────────────────────────────────── */

    protected function getService(DeliveryPartner $partner): ?DelhiveryService
    {
        try {
            $creds = json_decode(decrypt($partner->api_credentials), true);
            $token = $creds['api_token'] ?? $creds['token'] ?? null;
            if (!$token) return null;
            return new DelhiveryService($token, $partner->api_base_url ?? 'https://track.delhivery.com');
        } catch (\Exception $e) {
            Log::error('Failed to init DelhiveryService', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
