<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $provider
 * @property string $category
 * @property float $amount
 * @property string $billing_cycle
 * @property \Illuminate\Support\Carbon|null $next_billing_date
 * @property string $status
 * @property string|null $notes
 */
class SaasSubscription extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount'            => 'decimal:2',
        'next_billing_date' => 'date',
    ];

    /**
     * Convert this subscription's cost to an equivalent monthly amount,
     * for "total monthly SaaS spend" style summaries.
     */
    public function getMonthlyEquivalentAttribute(): float
    {
        return match ($this->billing_cycle) {
            'yearly'   => round((float) $this->amount / 12, 2),
            'one_time' => 0.0,
            default    => (float) $this->amount,
        };
    }
}
