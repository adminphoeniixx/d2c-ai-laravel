<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsShipment extends Model
{
    protected $connection = 'tenant';
    protected $fillable = [
        'delivery_partner_id', 'logistics_invoice_id', 'waybill', 'order_id', 'status',
        'payment_mode', 'zone', 'product_value', 'cod_amount', 'charged_weight',
        'destination_pin', 'origin_center',
        'charge_freight', 'charge_cod', 'charge_rto', 'charge_fuel', 'charge_pickup',
        'charge_vas', 'charge_other', 'gross_amount', 'cgst', 'sgst', 'igst', 'total_amount',
        'pickup_date', 'first_delivery_attempt', 'delivered_date', 'pdd',
        'attempt_count', 'item_shipped', 'qty', 'raw_data',
    ];
    protected function casts(): array {
        return ['pickup_date' => 'datetime', 'first_delivery_attempt' => 'datetime',
            'delivered_date' => 'datetime', 'pdd' => 'datetime', 'raw_data' => 'array'];
    }
    public function partner(): BelongsTo { return $this->belongsTo(DeliveryPartner::class, 'delivery_partner_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(LogisticsInvoice::class, 'logistics_invoice_id'); }
}
