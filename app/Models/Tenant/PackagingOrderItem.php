<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagingOrderItem extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'packaging_order_id', 'packaging_item_id', 'item_name',
        'unit', 'quantity', 'unit_price', 'total_price',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'  => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PackagingOrder::class, 'packaging_order_id');
    }

    public function packagingItem(): BelongsTo
    {
        return $this->belongsTo(PackagingItem::class, 'packaging_item_id');
    }
}
