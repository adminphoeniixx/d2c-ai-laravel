<?php

declare(strict_types=1);

namespace App\Jobs\Logistics;

use App\Models\Tenant\DeliveryPartner;
use App\Models\Tenant\LogisticsShipment;
use App\Services\Logistics\LogisticsClientFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTrackingStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public int $partnerId,
        public ?string $schema = null,
    ) {}

    public function handle(): void
    {
        if ($this->schema) {
            \DB::connection('tenant')->statement("SET search_path TO \"{$this->schema}\", public");
        }

        $partner = DeliveryPartner::find($this->partnerId);
        if (!$partner || !$partner->api_connected) return;

        $client = LogisticsClientFactory::make($partner);
        if (!$client) return;

        // Get all non-final shipments (not Delivered, not RTO, not Cancelled)
        $pendingShipments = LogisticsShipment::where('delivery_partner_id', $partner->id)
            ->whereNotIn('status', ['Delivered', 'RTO', 'Cancelled', 'RTO Delivered', 'VAS'])
            ->pluck('waybill')
            ->toArray();

        if (empty($pendingShipments)) return;

        $updated = 0;
        $failed = 0;

        // Use bulk tracking if possible
        $trackingResults = $client->trackBulk($pendingShipments);

        foreach ($trackingResults as $awb => $tracking) {
            try {
                $shipment = LogisticsShipment::where('delivery_partner_id', $partner->id)
                    ->where('waybill', $awb)
                    ->first();

                if (!$shipment) continue;

                $updates = ['status' => $tracking['status'] ?? $shipment->status];

                if (!empty($tracking['delivered_date']) && !$shipment->delivered_date) {
                    $updates['delivered_date'] = $tracking['delivered_date'];
                }
                if (!empty($tracking['pickup_date']) && !$shipment->pickup_date) {
                    $updates['pickup_date'] = $tracking['pickup_date'];
                }
                if (!empty($tracking['first_delivery_attempt']) && !$shipment->first_delivery_attempt) {
                    $updates['first_delivery_attempt'] = $tracking['first_delivery_attempt'];
                }
                if (isset($tracking['attempt_count'])) {
                    $updates['attempt_count'] = $tracking['attempt_count'];
                }
                if (!empty($tracking['cod_amount'])) {
                    $updates['cod_amount'] = $tracking['cod_amount'];
                }

                $shipment->update($updates);
                $updated++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("Tracking update failed for {$awb}", ['error' => $e->getMessage()]);
            }
        }

        Log::info("Tracking sync complete for {$partner->name}: {$updated} updated, {$failed} failed out of " . count($pendingShipments));
    }
}
