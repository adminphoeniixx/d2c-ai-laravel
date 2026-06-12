<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsInvoice extends Model
{
    protected $connection = 'tenant';
    protected $fillable = [
        'delivery_partner_id', 'invoice_number', 'invoice_date', 'period_from', 'period_to',
        'type', 'subtotal', 'cgst', 'sgst', 'igst', 'total_amount',
        'status', 'file_url', 'csv_url', 'shipment_count', 'metadata',
    ];
    protected function casts(): array {
        return ['invoice_date' => 'date', 'period_from' => 'date', 'period_to' => 'date',
            'subtotal' => 'decimal:2', 'cgst' => 'decimal:2', 'sgst' => 'decimal:2',
            'igst' => 'decimal:2', 'total_amount' => 'decimal:2', 'metadata' => 'array'];
    }
    public function partner(): BelongsTo { return $this->belongsTo(DeliveryPartner::class, 'delivery_partner_id'); }
    public function shipments(): HasMany { return $this->hasMany(LogisticsShipment::class, 'logistics_invoice_id'); }
}
