<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'month', 'status', 'total_gross', 'total_deductions', 'total_net',
        'employee_count', 'processed_at', 'paid_at', 'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'total_gross'      => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net'        => 'decimal:2',
            'processed_at'     => 'date',
            'paid_at'          => 'date',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
