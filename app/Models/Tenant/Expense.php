<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-scoped Expense.
 *
 * @property string $category   ads|payroll|inventory|shipping|tools|rent|other
 * @property string $source     manual|voice|auto
 */
class Expense extends Model
{
    use HasFactory;

    protected $table = 'expenses';

    protected $fillable = [
        'category', 'source', 'label', 'amount', 'currency',
        'occurred_at', 'recorded_by_user_id', 'voice_transcript', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'occurred_at' => 'immutable_datetime',
            'meta'        => 'array',
        ];
    }
}
