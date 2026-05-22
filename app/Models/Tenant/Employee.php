<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'employee_id', 'first_name', 'last_name', 'email', 'phone',
        'date_of_birth', 'gender',
        'designation', 'department', 'date_of_joining', 'date_of_leaving',
        'employment_type', 'status',
        'ctc_annual', 'basic_salary', 'hra', 'special_allowance', 'other_allowance',
        'bank_name', 'bank_account_number', 'bank_ifsc',
        'pan_number', 'aadhaar_number', 'uan_number', 'esi_number',
        'pf_applicable', 'esi_applicable', 'pf_number',
        'address', 'city', 'state', 'pincode',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'    => 'date',
            'date_of_joining'  => 'date',
            'date_of_leaving'  => 'date',
            'ctc_annual'       => 'decimal:2',
            'basic_salary'     => 'decimal:2',
            'hra'              => 'decimal:2',
            'special_allowance'=> 'decimal:2',
            'other_allowance'  => 'decimal:2',
            'pf_applicable'    => 'boolean',
            'esi_applicable'   => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function getMonthlySalaryAttribute(): float
    {
        return round(($this->basic_salary + $this->hra + $this->special_allowance + $this->other_allowance), 2);
    }
}
