<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $connection = 'tenant';
    protected $fillable = ['purchase_order_id','product_name','sku','hsn_code','quantity','received_quantity','unit_price','tax_rate','tax_amount','total_price'];
    protected function casts(): array {
        return ['unit_price'=>'decimal:2','tax_rate'=>'decimal:2','tax_amount'=>'decimal:2','total_price'=>'decimal:2'];
    }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
}
