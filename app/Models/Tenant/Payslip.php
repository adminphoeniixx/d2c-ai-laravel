<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'month',
        'basic_salary', 'hra', 'special_allowance', 'other_allowance',
        'overtime_pay', 'bonus', 'gross_salary',
        'pf_employee', 'pf_employer', 'esi_employee', 'esi_employer',
        'professional_tax', 'tds', 'other_deductions', 'total_deductions',
        'net_salary',
        'working_days', 'days_present', 'days_absent',
        'total_overtime_hours', 'overtime_rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary'        => 'decimal:2',
            'hra'                 => 'decimal:2',
            'special_allowance'   => 'decimal:2',
            'other_allowance'     => 'decimal:2',
            'overtime_pay'        => 'decimal:2',
            'bonus'               => 'decimal:2',
            'gross_salary'        => 'decimal:2',
            'pf_employee'         => 'decimal:2',
            'pf_employer'         => 'decimal:2',
            'esi_employee'        => 'decimal:2',
            'esi_employer'        => 'decimal:2',
            'professional_tax'    => 'decimal:2',
            'tds'                 => 'decimal:2',
            'other_deductions'    => 'decimal:2',
            'total_deductions'    => 'decimal:2',
            'net_salary'          => 'decimal:2',
            'total_overtime_hours'=> 'decimal:2',
            'overtime_rate'       => 'decimal:2',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Calculate payslip from employee data + attendance.
     *
     * Uses company-level PF/ESI rates + per-employee opt-out.
     * Falls back to Indian statutory defaults if company settings not available.
     */
    public static function calculateFromEmployee(Employee $employee, int $workingDays, int $daysPresent, float $overtimeHours = 0, float $overtimeRatePerHour = 0, float $bonus = 0, ?object $company = null): array
    {
        $ratio = $workingDays > 0 ? $daysPresent / $workingDays : 1;

        $basic    = round($employee->basic_salary * $ratio, 2);
        $hra      = round($employee->hra * $ratio, 2);
        $special  = round($employee->special_allowance * $ratio, 2);
        $other    = round($employee->other_allowance * $ratio, 2);
        $otPay    = round($overtimeHours * $overtimeRatePerHour, 2);
        $gross    = $basic + $hra + $special + $other + $otPay + $bonus;

        // Company PF settings (defaults to statutory rates)
        $pfEnabled       = $company->pf_enabled ?? true;
        $pfEmployeeRate  = ($company->pf_employee_rate ?? 12.00) / 100;
        $pfEmployerRate  = ($company->pf_employer_rate ?? 12.00) / 100;
        $pfBasicCap      = $company->pf_basic_cap ?? 15000;

        // Company ESI settings
        $esiEnabled        = $company->esi_enabled ?? true;
        $esiEmployeeRate   = ($company->esi_employee_rate ?? 0.75) / 100;
        $esiEmployerRate   = ($company->esi_employer_rate ?? 3.25) / 100;
        $esiGrossThreshold = $company->esi_gross_threshold ?? 21000;

        // Professional Tax
        $ptAmount = $company->pt_amount ?? 200;

        // PF — only if company enabled AND employee opted in
        $pfEmployee = 0;
        $pfEmployer = 0;
        if ($pfEnabled && ($employee->pf_applicable ?? true)) {
            $pfBasic     = min($basic, $pfBasicCap);
            $pfEmployee  = round($pfBasic * $pfEmployeeRate, 2);
            $pfEmployer  = round($pfBasic * $pfEmployerRate, 2);
        }

        // ESI — only if company enabled AND employee opted in AND gross within threshold
        $esiEmployee = 0;
        $esiEmployer = 0;
        if ($esiEnabled && ($employee->esi_applicable ?? true) && $gross <= $esiGrossThreshold) {
            $esiEmployee = round($gross * $esiEmployeeRate, 2);
            $esiEmployer = round($gross * $esiEmployerRate, 2);
        }

        // Professional Tax
        $pt = 0;
        if ($gross > 10000) {
            $pt = $ptAmount;
        }

        $totalDeductions = $pfEmployee + $esiEmployee + $pt;
        $net = round($gross - $totalDeductions, 2);

        return [
            'basic_salary'         => $basic,
            'hra'                  => $hra,
            'special_allowance'    => $special,
            'other_allowance'      => $other,
            'overtime_pay'         => $otPay,
            'bonus'                => $bonus,
            'gross_salary'         => $gross,
            'pf_employee'          => $pfEmployee,
            'pf_employer'          => $pfEmployer,
            'esi_employee'         => $esiEmployee,
            'esi_employer'         => $esiEmployer,
            'professional_tax'     => $pt,
            'tds'                  => 0,
            'other_deductions'     => 0,
            'total_deductions'     => $totalDeductions,
            'net_salary'           => $net,
            'working_days'         => $workingDays,
            'days_present'         => $daysPresent,
            'days_absent'          => $workingDays - $daysPresent,
            'total_overtime_hours' => $overtimeHours,
            'overtime_rate'        => $overtimeRatePerHour,
        ];
    }
}
