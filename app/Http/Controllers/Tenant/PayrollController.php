<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\AttendanceSetting;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Holiday;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\PayrollRun;
use App\Models\Tenant\Payslip;
use App\Models\Tenant\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(Request $request): Response
    {
        try {
            $runs = PayrollRun::withCount('payslips')
                ->orderByDesc('month')
                ->paginate(12)
                ->withQueryString();
        } catch (\Throwable $e) {
            $runs = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 12);
        }

        return Inertia::render('Tenant/Payroll/Index', [
            'runs'      => $runs,
            'nextMonth' => now()->format('Y-m'),
        ]);
    }

    public function create(Request $request): Response
    {
        $month = $request->input('month', now()->format('Y-m'));
        $employees = Employee::where('status', 'active')->orderBy('first_name')->get();
        $company = app('current_company');

        $year = (int) substr($month, 0, 4);
        $mon = (int) substr($month, 5, 2);
        $workingDays = $this->getWorkingDays($year, $mon);

        $calculations = [];

        try {
            $settings = AttendanceSetting::getSettings();

            foreach ($employees as $emp) {
                $att = $this->getAttendanceSummary($emp->id, $year, $mon);

                $daysPresent = $att['present'] + ($att['half_days'] * 0.5);
                $overtime = $att['overtime_hours'];
                $otRate = $this->overtimeRate($emp, $settings);

                $calc = Payslip::calculateFromEmployee(
                    $emp, $workingDays, (int) $daysPresent, $overtime, $otRate, 0, $company
                );

                // Additional deductions from attendance
                $latePenalty = $att['total_penalty'];
                $absentDays = $att['absent'];
                $dailyRate = ($emp->basic_salary + $emp->hra + $emp->special_allowance + $emp->other_allowance) / 26;
                $absentDeduction = round($absentDays * $dailyRate, 2);

                // LWP deductions
                $lwpDays = $att['lwp_days'];
                $lwpDeduction = round($lwpDays * $dailyRate, 2);

                $calc['late_deductions'] = $latePenalty;
                $calc['absent_deductions'] = $absentDeduction;
                $calc['lwp_deductions'] = $lwpDeduction;
                $calc['late_count'] = $att['late_count'];
                $calc['half_days'] = $att['half_days'];
                $calc['leave_days'] = $att['leave_days'];

                // Adjust total deductions and net
                $calc['total_deductions'] += $latePenalty + $absentDeduction + $lwpDeduction;
                $calc['net_salary'] = $calc['gross_salary'] - $calc['total_deductions'];

                $calc['employee_id'] = $emp->id;
                $calc['employee_name'] = $emp->full_name;
                $calc['employee_code'] = $emp->employee_id;
                $calc['designation'] = $emp->designation;

                $calculations[] = $calc;
            }
        } catch (\Throwable $e) {
            // If attendance tables don't exist, calculate basic payroll
            foreach ($employees as $emp) {
                $calc = Payslip::calculateFromEmployee($emp, $workingDays, $workingDays, 0, 0, 0, $company);
                $calc['late_deductions'] = 0;
                $calc['absent_deductions'] = 0;
                $calc['lwp_deductions'] = 0;
                $calc['late_count'] = 0;
                $calc['half_days'] = 0;
                $calc['leave_days'] = 0;
                $calc['employee_id'] = $emp->id;
                $calc['employee_name'] = $emp->full_name;
                $calc['employee_code'] = $emp->employee_id;
                $calc['designation'] = $emp->designation;
                $calculations[] = $calc;
            }
        }

        return Inertia::render('Tenant/Payroll/Create', [
            'month'        => $month,
            'employees'    => $employees,
            'workingDays'  => $workingDays,
            'calculations' => $calculations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        // Check if already processed
        if (PayrollRun::where('month', $validated['month'])->exists()) {
            return back()->with('error', 'Payroll for this month already exists.');
        }

        $company = app('current_company');
        $employees = Employee::where('status', 'active')->get();
        $year = (int) substr($validated['month'], 0, 4);
        $mon = (int) substr($validated['month'], 5, 2);
        $workingDays = $this->getWorkingDays($year, $mon);

        $run = PayrollRun::create([
            'month'        => $validated['month'],
            'status'       => 'processed',
            'processed_at' => now(),
            'processed_by' => auth()->id(),
        ]);

        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;

        try { $settings = AttendanceSetting::getSettings(); } catch (\Throwable $e) { $settings = null; }

        foreach ($employees as $emp) {
            try {
                $att = $this->getAttendanceSummary($emp->id, $year, $mon);
            } catch (\Throwable $e) {
                $att = ['present' => $workingDays, 'absent' => 0, 'half_days' => 0, 'late_count' => 0,
                    'leave_days' => 0, 'lwp_days' => 0, 'overtime_hours' => 0, 'total_penalty' => 0];
            }

            $daysPresent = $att['present'] + ($att['half_days'] * 0.5);
            $otRate = $this->overtimeRate($emp, $settings);

            $calc = Payslip::calculateFromEmployee(
                $emp, $workingDays, (int) $daysPresent, $att['overtime_hours'], $otRate, 0, $company
            );

            $dailyRate = ($emp->basic_salary + $emp->hra + $emp->special_allowance + $emp->other_allowance) / 26;
            $latePenalty = $att['total_penalty'];
            $absentDeduction = round($att['absent'] * $dailyRate, 2);
            $lwpDeduction = round($att['lwp_days'] * $dailyRate, 2);

            $calc['total_deductions'] += $latePenalty + $absentDeduction + $lwpDeduction;
            $calc['net_salary'] = $calc['gross_salary'] - $calc['total_deductions'];

            $payslip = Payslip::create(array_merge($calc, [
                'payroll_run_id'     => $run->id,
                'employee_id'        => $emp->id,
                'month'              => $validated['month'],
                'working_days'       => $workingDays,
                'days_present'       => (int) $daysPresent,
                'days_absent'        => $att['absent'],
                'late_count'         => $att['late_count'],
                'half_days'          => $att['half_days'],
                'leave_days'         => $att['leave_days'],
                'total_overtime_hours'=> $att['overtime_hours'],
                'overtime_rate'      => $otRate,
                'late_deductions'    => $latePenalty,
                'absent_deductions'  => $absentDeduction,
                'lwp_deductions'     => $lwpDeduction,
                'status'             => 'generated',
            ]));

            $totalGross += $calc['gross_salary'];
            $totalDeductions += $calc['total_deductions'];
            $totalNet += $calc['net_salary'];
        }

        $run->update([
            'total_gross'      => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net'        => $totalNet,
            'employee_count'   => $employees->count(),
        ]);

        return redirect()->route('tenant.payroll.show', ['tenant' => $request->route('tenant'), 'id' => $run->id])
            ->with('success', "Payroll processed for {$employees->count()} employees.");
    }

    public function show(Request $request, string $tenant, string $id): Response
    {
        $run = PayrollRun::findOrFail($id);
        $payslips = Payslip::where('payroll_run_id', $run->id)
            ->with('employee:id,employee_id,first_name,last_name,designation,department,bank_name,bank_account_number')
            ->orderBy('employee_id')
            ->get();

        return Inertia::render('Tenant/Payroll/Show', [
            'run'      => $run,
            'payslips' => $payslips,
        ]);
    }

    public function markPaid(Request $request, string $tenant, string $id): RedirectResponse
    {
        $run = PayrollRun::findOrFail($id);
        $run->update(['status' => 'paid', 'paid_at' => now()]);
        $run->payslips()->update(['status' => 'paid']);

        return back()->with('success', 'Payroll marked as paid.');
    }

    /* ── Helpers ──────────────────────────────── */

    protected function getAttendanceSummary(int $employeeId, int $year, int $month): array
    {
        $summary = Attendance::selectRaw("
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days,
            SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days,
            SUM(CASE WHEN is_late = true THEN 1 ELSE 0 END) as late_count,
            SUM(overtime_hours) as overtime_hours,
            SUM(penalty_amount) as total_penalty
        ")
            ->where('employee_id', $employeeId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->first();

        // Count LWP days (Leave Without Pay)
        $lwpDays = LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', false))
            ->where(function ($q) use ($year, $month) {
                $start = Carbon::create($year, $month, 1)->startOfMonth();
                $end = $start->copy()->endOfMonth();
                $q->whereBetween('from_date', [$start, $end])
                  ->orWhereBetween('to_date', [$start, $end]);
            })
            ->sum('days');

        return [
            'present'        => (int) ($summary->present ?? 0),
            'absent'         => (int) ($summary->absent ?? 0),
            'half_days'      => (float) ($summary->half_days ?? 0),
            'leave_days'     => (int) ($summary->leave_days ?? 0),
            'lwp_days'       => (float) $lwpDays,
            'late_count'     => (int) ($summary->late_count ?? 0),
            'overtime_hours' => (float) ($summary->overtime_hours ?? 0),
            'total_penalty'  => (float) ($summary->total_penalty ?? 0),
        ];
    }

    protected function getWorkingDays(int $year, int $month): int
    {
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $workingDays = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d);
            if (WorkSchedule::isWorkingDay($date) && !Holiday::isHoliday($date)) {
                $workingDays++;
            }
        }

        return $workingDays ?: 26; // fallback
    }

    protected function overtimeRate(Employee $emp, ?object $settings = null): float
    {
        $monthly = $emp->basic_salary + $emp->hra + $emp->special_allowance + $emp->other_allowance;
        $hourlyRate = $monthly / (26 * 8); // 26 working days, 8 hours
        $multiplier = $settings?->overtime_rate_multiplier ?? 1.5;
        return round($hourlyRate * $multiplier, 2);
    }
}
