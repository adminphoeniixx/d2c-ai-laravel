<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id', 'external_id', 'sku', 'product_name', 'variant_name',
        'quantity', 'unit_price', 'total_price', 'tax_amount', 'discount_amount',
        // GST fields
        'hsn_code', 'gst_rate', 'taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'      => 'decimal:2',
            'total_price'     => 'decimal:2',
            'tax_amount'      => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_amount'  => 'decimal:2',
            'cgst_amount'     => 'decimal:2',
            'sgst_amount'     => 'decimal:2',
            'igst_amount'     => 'decimal:2',
            'gst_rate'        => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
