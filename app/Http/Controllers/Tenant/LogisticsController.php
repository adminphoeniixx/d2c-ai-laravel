<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DeliveryPartner;
use App\Models\Tenant\LogisticsInvoice;
use App\Models\Tenant\LogisticsShipment;
use App\Services\AI\AiInvoiceExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LogisticsController extends Controller
{
    // ── Index: list all delivery partners
    public function index(Request $request)
    {
        // Defensive seed for companies created without an explicit
        // seeding step. Cheap — firstOrCreate per partner.
        DeliveryPartner::seedDefaults();

        $partners = DeliveryPartner::withCount(['shipments', 'invoices'])
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                return [
                    'id'             => $p->id,
                    'name'           => $p->name,
                    'slug'           => $p->slug,
                    'logo'           => $p->logo ?? null,
                    'is_active'      => $p->is_active,
                    'has_api'        => true,
                    'api_connected'  => $p->api_connected,
                    'shipments_count'=> $p->shipments_count,
                    'invoices_count' => $p->invoices_count,
                    'total_amount'   => LogisticsInvoice::where('delivery_partner_id', $p->id)->sum('total_amount'),
                ];
            });

        $stats = [
            'total_shipments'  => LogisticsShipment::count(),
            'total_delivered'  => LogisticsShipment::where('status', 'Delivered')->count(),
            'total_rto'        => LogisticsShipment::whereIn('status', ['RTO', 'DTO', 'Return to Origin'])->count(),
            'total_freight'    => LogisticsInvoice::sum('total_amount'),
            'avg_shipping_cost'=> 0,
        ];
        $stats['rto_rate'] = $stats['total_shipments']
            ? round($stats['total_rto'] / $stats['total_shipments'] * 100, 1)
            : 0;

        return Inertia::render('Tenant/Logistics/Index', [
            'partners' => $partners,
            'stats'    => $stats,
        ]);
    }

    // ── Partner page (by partnerId)
    public function show(Request $request, $tenant, $partnerId)
    {
        $partner = DeliveryPartner::findOrFail($partnerId);

        $invoices = LogisticsInvoice::where('delivery_partner_id', $partner->id)
            ->withCount('shipments')
            ->orderByDesc('invoice_date')
            ->get()
            ->map(fn($i) => [
                'id'             => $i->id,
                'invoice_number' => $i->invoice_number,
                'invoice_date'   => $i->invoice_date,
                'type'           => $i->type,
                'total_amount'   => $i->total_amount,
                'file_url'       => $i->file_url,
                'shipments_count'=> $i->shipments_count,
            ]);

        $sq        = LogisticsShipment::where('delivery_partner_id', $partner->id);
        $total     = (clone $sq)->count();
        $delivered = (clone $sq)->where('status', 'Delivered')->count();
        $rto       = (clone $sq)->whereIn('status', ['RTO', 'DTO', 'Return to Origin'])->count();
        $inTransit = (clone $sq)->whereNotIn('status', ['Delivered', 'RTO', 'DTO', 'Return to Origin', 'Cancelled'])->count();
        $cod       = (clone $sq)->where('payment_mode', 'COD')->sum('cod_amount');
        $freight   = (clone $sq)->sum('charge_freight');

        $iq = LogisticsInvoice::where('delivery_partner_id', $partner->id);
        $invoiceStats = [
            'total_invoices' => (clone $iq)->count(),
            'total_subtotal' => (clone $iq)->sum('subtotal'),
            'total_gst'      => (clone $iq)->selectRaw('COALESCE(SUM(cgst + sgst + igst), 0) as g')->value('g') ?? 0,
            'total_amount'   => (clone $iq)->sum('total_amount'),
        ];

        // Credential fields per partner for the connect modal
        $credentialFields = $this->credentialFields($partner->slug);

        return Inertia::render('Tenant/Logistics/Partner', [
            'partner' => [
                'id'               => $partner->id,
                'name'             => $partner->name,
                'slug'             => $partner->slug,
                'logo'             => $partner->logo ?? null,
                'has_api'          => true,
                'api_connected'    => $partner->api_connected,
                'credential_fields'=> $credentialFields,
            ],
            'invoices'      => $invoices,
            'shipmentStats' => [
                'total'      => $total,
                'delivered'  => $delivered,
                'rto'        => $rto,
                'in_transit' => $inTransit,
                'cod'        => $cod,
                'freight'    => $freight,
            ],
            'invoiceStats'  => $invoiceStats,
        ]);
    }

    // ── Smart upload on index page (auto-detect partner)
    public function smartUpload(Request $request, $tenant)
    {
        $request->validate([
            'invoice_pdf' => 'nullable|file|mimes:pdf|max:20480',
            'invoice_csv' => 'nullable|file|mimes:csv,txt|max:20480',
        ]);

        $extractor = new AiInvoiceExtractor();
        $extracted = [];
        $partnerId = null;
        $fileUrl   = null;

        if ($request->hasFile('invoice_pdf')) {
            $file    = $request->file('invoice_pdf');
            $fileUrl = $this->uploadToBunny($file, 'logistics');
            try { $extracted = $extractor->extractFromPdf($file->getRealPath(), 'logistics'); } catch (\Exception $e) {}
        }

        if ($request->hasFile('invoice_csv') && empty($extracted)) {
            $file = $request->file('invoice_csv');
            if (!$fileUrl) $fileUrl = $this->uploadToBunny($file, 'logistics');
            try { $extracted = $extractor->extractFromCsv($file->getRealPath(), 'logistics'); } catch (\Exception $e) {}
        }

        $partnerName = $extracted['partner'] ?? null;
        if ($partnerName) {
            $partner   = DeliveryPartner::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($partnerName) . '%'])->first();
            $partnerId = $partner?->id;
        }
        if (!$partnerId) {
            $partnerId = DeliveryPartner::first()?->id;
        }

        if ($partnerId) $this->saveInvoice($partnerId, $extracted, $fileUrl);

        return back();
    }

    // ── Upload invoice on partner page
    public function uploadInvoice(Request $request, $tenant, $partnerId)
    {
        $request->validate([
            'invoice_pdf' => 'nullable|file|mimes:pdf|max:20480',
            'invoice_csv' => 'nullable|file|mimes:csv,txt|max:20480',
        ]);

        $extractor = new AiInvoiceExtractor();
        $extracted = [];
        $fileUrl   = null;

        if ($request->hasFile('invoice_pdf')) {
            $file    = $request->file('invoice_pdf');
            $fileUrl = $this->uploadToBunny($file, 'logistics');
            try { $extracted = $extractor->extractFromPdf($file->getRealPath(), 'logistics'); } catch (\Exception $e) {}
        }

        if ($request->hasFile('invoice_csv')) {
            $file = $request->file('invoice_csv');
            if (!$fileUrl) $fileUrl = $this->uploadToBunny($file, 'logistics');
            if (empty($extracted)) {
                try { $extracted = $extractor->extractFromCsv($file->getRealPath(), 'logistics'); } catch (\Exception $e) {}
            }
        }

        $this->saveInvoice((int) $partnerId, $extracted, $fileUrl);
        return back();
    }

    // ── Connect API credentials
    public function connectApi(Request $request, $tenant, $partnerId)
    {
        $partner   = DeliveryPartner::findOrFail($partnerId);
        $fields    = $this->credentialFields($partner->slug);
        $creds     = $request->only(array_keys($fields));

        $partner->update([
            'api_credentials' => encrypt(json_encode($creds)),
            'api_connected'   => true,
        ]);

        return back();
    }

    // ── Disconnect API
    public function disconnectApi(Request $request, $tenant, $partnerId)
    {
        DeliveryPartner::findOrFail($partnerId)->update([
            'api_credentials' => null,
            'api_connected'   => false,
        ]);
        return back();
    }

    // ── Track single shipment (JSON)
    public function trackShipment(Request $request, $tenant, $partnerId)
    {
        $partner = DeliveryPartner::findOrFail($partnerId);
        $waybill = $request->input('waybill');

        if ($partner->slug === 'delhivery' && $partner->api_connected) {
            try {
                $creds  = json_decode(decrypt($partner->api_credentials), true);
                $token  = $creds['api_token'] ?? $creds['token'] ?? '';
                $svc    = new \App\Services\DelhiveryService($token);
                return response()->json($svc->trackShipment($waybill));
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        return response()->json(['error' => 'Tracking not available for this partner'], 422);
    }

    // ── Sync tracking
    public function syncTracking(Request $request, $tenant, $partnerId)
    {
        $partner = DeliveryPartner::findOrFail($partnerId);
        if ($partner->slug === 'delhivery' && $partner->api_connected) {
            try {
                $creds  = json_decode(decrypt($partner->api_credentials), true);
                $token  = $creds['api_token'] ?? $creds['token'] ?? '';
                $svc    = new \App\Services\DelhiveryService($token);

                $activeWaybills = LogisticsShipment::where('delivery_partner_id', $partner->id)
                    ->whereNotIn('status', ['Delivered', 'RTO', 'Cancelled'])
                    ->pluck('waybill')->toArray();

                if (empty($activeWaybills)) {
                    return back()->with('success', 'No active shipments to sync.');
                }

                $tracked = $svc->trackBulk($activeWaybills);
                $updated = 0;

                foreach ($tracked as $awb => $shipmentData) {
                    $metrics  = \App\Services\DelhiveryService::extractMetrics($shipmentData);
                    $shipment = LogisticsShipment::where('waybill', $awb)
                        ->where('delivery_partner_id', $partner->id)->first();
                    if ($shipment) {
                        $shipment->update([
                            'status'         => $metrics['status'],
                            'last_scan'      => $metrics['last_scan'],
                            'last_scan_at'   => $metrics['last_scan_at'] ? \Carbon\Carbon::parse($metrics['last_scan_at']) : null,
                            'is_rto'         => $metrics['is_rto'],
                            'tracking_data'  => $shipmentData,
                        ]);
                        $updated++;
                    }
                }
                return back()->with('success', "Synced $updated shipments.");
            } catch (\Exception $e) {
                return back()->withErrors(['sync' => $e->getMessage()]);
            }
        }
        return back();
    }

    // ── Fetch ALL shipments from API by date range. Disabled: Delhivery's
    // API has no endpoint to list/discover shipments — only to track AWBs
    // you already know. Use CSV upload to bring AWBs in, then Sync Status
    // keeps their tracking data current.
    public function fetchShipments(Request $request, $tenant, $partnerId)
    {
        return back()->withErrors(['fetch' => 'Discovering new shipments by date isn\'t supported by Delhivery\'s API. Upload a shipment CSV to add AWBs instead.']);
    }

    // ── Invoice detail page
    public function invoiceDetail(Request $request, $tenant, $invoiceId)
    {
        $invoice   = LogisticsInvoice::with('partner')->findOrFail($invoiceId);
        $shipments = LogisticsShipment::where('logistics_invoice_id', $invoice->id)
            ->orderByDesc('created_at')->paginate(100);

        return Inertia::render('Tenant/Logistics/InvoiceDetail', [
            'invoice'   => $invoice,
            'shipments' => $shipments,
        ]);
    }

    // ── Delete invoice
    public function deleteInvoice(Request $request, $tenant, $invoiceId)
    {
        LogisticsInvoice::findOrFail($invoiceId)->delete();
        return back();
    }

    // ── Helpers ────────────────────────────────────────

    private function credentialFields(string $slug): array
    {
        return match($slug) {
            'delhivery'  => ['api_token' => 'API Token'],
            'shiprocket' => ['email' => 'Email', 'password' => 'Password'],
            'bluedart'   => ['license_key' => 'License Key', 'login_id' => 'Login ID'],
            default      => ['api_token' => 'API Token'],
        };
    }

    private function uploadToBunny($file, string $folder): ?string
    {
        try {
            $ext      = strtolower($file->getClientOriginalExtension());
            $filename = $folder . '/' . uniqid() . '.' . $ext;
            Storage::disk('bunny')->put($filename, file_get_contents($file->getRealPath()), 'public');
            return config('filesystems.disks.bunny.url') . '/' . $filename;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function saveInvoice(int $partnerId, array $extracted, ?string $fileUrl): void
    {
        $cgst = $extracted['cgst'] ?? null;
        $sgst = $extracted['sgst'] ?? null;
        if (!$cgst && !$sgst && isset($extracted['tax'])) {
            $cgst = round($extracted['tax'] / 2, 2);
            $sgst = round($extracted['tax'] / 2, 2);
        }

        LogisticsInvoice::updateOrCreate(
            [
                'delivery_partner_id' => $partnerId,
                'invoice_number'      => $extracted['invoice_number'] ?? ('INV-' . uniqid()),
            ],
            [
                'invoice_date' => $extracted['invoice_date']                     ?? now()->toDateString(),
                'type'         => $extracted['invoice_type'] ?? $extracted['type'] ?? 'freight',
                'total_amount' => $extracted['total_amount']                     ?? 0,
                'subtotal'     => $extracted['taxable_amount'] ?? $extracted['subtotal'] ?? 0,
                'cgst'         => $cgst ?? 0,
                'sgst'         => $sgst ?? 0,
                'igst'         => $extracted['igst']                             ?? 0,
                'file_url'     => $fileUrl,
                'metadata'     => $extracted ?: [],
            ]
        );
    }
}
