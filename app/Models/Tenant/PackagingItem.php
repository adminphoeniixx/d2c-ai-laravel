<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackagingItem extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'name', 'sku', 'unit', 'quantity', 'min_stock_level',
        'cost_price', 'location', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['cost_price' => 'decimal:2'];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(PackagingOrderItem::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_stock_level;
    }
}
