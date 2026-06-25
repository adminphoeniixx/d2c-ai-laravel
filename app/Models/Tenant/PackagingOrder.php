<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackagingOrder extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'po_number', 'supplier_name', 'status', 'order_date',
        'expected_date', 'received_date', 'subtotal', 'tax_amount',
        'total_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'order_date'    => 'date',
            'expected_date' => 'date',
            'received_date' => 'date',
            'subtotal'      => 'decimal:2',
            'tax_amount'    => 'decimal:2',
            'total_amount'  => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackagingOrderItem::class);
    }

    /**
     * Generate the next PO number in the format PKG-0001, PKG-0002, etc.
     */
    public static function nextPoNumber(): string
    {
        $last = static::orderByDesc('id')->value('po_number');
        $n = $last ? ((int) substr($last, 4)) + 1 : 1;
        return 'PKG-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
