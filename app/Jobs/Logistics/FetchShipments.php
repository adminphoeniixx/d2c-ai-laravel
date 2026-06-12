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

class FetchShipments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public int $partnerId,
        public string $fromDate,
        public string $toDate,
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

        $shipments = $client->fetchShipments($this->fromDate, $this->toDate);
        $imported = 0;

        foreach ($shipments as $s) {
            try {
                if (empty($s['waybill'])) continue;

                LogisticsShipment::updateOrCreate(
                    ['delivery_partner_id' => $partner->id, 'waybill' => $s['waybill']],
                    [
                        'order_id'        => $s['order_id'] ?? null,
                        'status'          => $s['status'] ?? 'Unknown',
                        'payment_mode'    => $s['payment_mode'] ?? null,
                        'product_value'   => $s['product_value'] ?? 0,
                        'cod_amount'      => $s['cod_amount'] ?? 0,
                        'destination_pin' => $s['destination_pin'] ?? null,
                        'pickup_date'     => isset($s['pickup_date']) ? \Carbon\Carbon::parse($s['pickup_date']) : null,
                        'item_shipped'    => $s['item_shipped'] ?? null,
                        'qty'             => $s['qty'] ?? 1,
                    ]
                );
                $imported++;
            } catch (\Throwable $e) {
                Log::warning('Shipment import failed', ['waybill' => $s['waybill'] ?? 'unknown', 'error' => $e->getMessage()]);
            }
        }

        Log::info("Fetched {$imported} shipments from {$partner->name}");
    }
}
