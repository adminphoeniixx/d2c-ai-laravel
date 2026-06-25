<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawMaterial extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'name', 'sku', 'category', 'unit', 'quantity', 'reorder_level',
        'cost_per_unit', 'supplier', 'location', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'cost_per_unit' => 'decimal:2',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RawMaterialTransaction::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->reorder_level;
    }
}
