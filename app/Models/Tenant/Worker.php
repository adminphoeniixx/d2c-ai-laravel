<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Worker (श्रमिक / कर्मचारी) — separate from salaried Employee.
 * Based on standard Hindi bio-data form + temporary appointment letter.
 */
class Worker extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'worker_id', 'name', 'father_husband_name',
        'date_of_birth', 'age', 'permanent_address', 'local_address',
        'education', 'technical_qualification', 'languages',
        'mobile', 'pan_number', 'aadhaar_number', 'pf_uan',
        'post_applied', 'post_held',
        'appointment_from', 'appointment_to', 'appointment_type',
        'daily_wage', 'monthly_wage', 'payment_mode', 'currency',
        'pf_applicable', 'esi_applicable', 'pf_number', 'esi_number',
        'experience', 'references',
        'status', 'date_of_leaving', 'reason_leaving',
        'bank_name', 'bank_account_number', 'bank_ifsc',
        'notes', 'photo_url',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'    => 'date',
            'appointment_from' => 'date',
            'appointment_to'   => 'date',
            'date_of_leaving'  => 'date',
            'daily_wage'       => 'decimal:2',
            'monthly_wage'     => 'decimal:2',
            'pf_applicable'    => 'boolean',
            'esi_applicable'   => 'boolean',
            'experience'       => 'array',
            'references'       => 'array',
        ];
    }

    /**
     * Get effective wage for payroll: monthly or daily * 26.
     */
    public function getEffectiveMonthlyWageAttribute(): float
    {
        if ($this->payment_mode === 'monthly') {
            return (float) $this->monthly_wage;
        }
        return (float) $this->daily_wage * 26; // assumed 26 working days
    }
}
