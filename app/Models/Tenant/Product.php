<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'external_id', 'provider', 'sku', 'name', 'vendor',
        'product_type', 'status', 'price', 'compare_at_price', 'cost',
        'inventory_quantity', 'tags', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'price'            => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost'             => 'decimal:2',
            'tags'             => 'array',
            'meta'             => 'array',
        ];
    }
}
