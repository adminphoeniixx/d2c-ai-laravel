<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $connection = 'tenant';
    protected $fillable = [
        'po_number','vendor_id','status','order_date','expected_date','received_date',
        'subtotal','tax_amount','discount','total_amount','notes','created_by',
    ];
    protected function casts(): array {
        return ['order_date'=>'date','expected_date'=>'date','received_date'=>'date',
            'subtotal'=>'decimal:2','tax_amount'=>'decimal:2','discount'=>'decimal:2','total_amount'=>'decimal:2'];
    }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
}
