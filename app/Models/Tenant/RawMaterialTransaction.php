<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawMaterialTransaction extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'raw_material_id', 'type', 'quantity', 'cost_per_unit',
        'total_cost', 'transaction_date', 'reference', 'reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity'         => 'decimal:3',
            'cost_per_unit'    => 'decimal:2',
            'total_cost'       => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
