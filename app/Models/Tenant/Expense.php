<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount'         => 'decimal:2',
        'occurred_at'    => 'date',
        'extracted_data' => 'array',
        'line_items'     => 'array',
    ];
}
