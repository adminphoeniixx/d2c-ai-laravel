<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Employee;
use App\Models\Tenant\PayrollRun;
use App\Models\Tenant\Payslip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'runs'     => $runs,
            'nextMonth'=> now()->format('Y-m'),
        ]);
    }

    public function create(Request $request): Response
    {
        $month = $request->input('month', now()->format('Y-m'));
        $employees = Employee::where('status', 'active')->orderBy('first_name')->get();

        // Get attendance summary for the month
        $year = substr($month, 0, 4);
        $mon = substr($month, 5, 2);
        $workingDays = $this->getWorkingDays((int) $year, (int) $mon);

        $attendanceSummary = [];
        try {
            $summaries = Attendance::selectRaw("
                employee_id,
                SUM(CASE WHEN status IN ('present','half_day') THEN 1 ELSE 0 END) as days_present,
                SUM(overtime_hours) as total_overtime
            ")
                ->whereYear('date', $year)
                ->whereMonth('date', $mon)
                ->groupBy('employee_id')
                ->get()
                ->keyBy('employee_id');

            foreach ($employees as $emp) {
                $s = $summaries->get($emp->id);
                $daysPresent = $s ? (int) $s->days_present : $workingDays;
                $overtime = $s ? (float) $s->total_overtime : 0;

                $calc = Payslip::calculateFromEmployee($emp, $workingDays, $daysPresent, $overtime, $this->overtimeRate($emp), 0, app('current_company'));
                $attendanceSummary[$emp->id] = array_merge($calc, [
                    'employee_id' => $emp->id,
                    'employee_name' => $emp->full_name,
                    'employee_code' => $emp->employee_id,
                    'designation' => $emp->designation,
                ]);
            }
        } catch (\Throwable $e) {
            // Empty summary if tables don't exist yet
        }

        return Inertia::render('Tenant/Payroll/Create', [
            'month'        => $month,
            'employees'    => $employees,
            'workingDays'  => $workingDays,
            'calculations' => array_values($attendanceSummary),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $month = $validated['month'];
        $year = (int) substr($month, 0, 4);
        $mon = (int) substr($month, 5, 2);
        $workingDays = $this->getWorkingDays($year, $mon);

        $employees = Employee::where('status', 'active')->get();

        $run = PayrollRun::create([
            'month'  => $month,
            'status' => 'draft',
            'employee_count' => $employees->count(),
            'processed_by'   => auth()->id(),
        ]);

        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;

        $summaries = Attendance::selectRaw("employee_id, SUM(CASE WHEN status IN ('present','half_day') THEN 1 ELSE 0 END) as days_present, SUM(overtime_hours) as total_overtime")
            ->whereYear('date', $year)->whereMonth('date', $mon)
            ->groupBy('employee_id')->get()->keyBy('employee_id');

        foreach ($employees as $emp) {
            $s = $summaries->get($emp->id);
            $daysPresent = $s ? (int) $s->days_present : $workingDays;
            $overtime = $s ? (float) $s->total_overtime : 0;

            $calc = Payslip::calculateFromEmployee($emp, $workingDays, $daysPresent, $overtime, $this->overtimeRate($emp), 0, app('current_company'));

            Payslip::create(array_merge($calc, [
                'payroll_run_id' => $run->id,
                'employee_id'    => $emp->id,
                'month'          => $month,
                'status'         => 'draft',
            ]));

            $totalGross += $calc['gross_salary'];
            $totalDeductions += $calc['total_deductions'];
            $totalNet += $calc['net_salary'];
        }

        $run->update([
            'total_gross'      => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net'        => $totalNet,
            'status'           => 'processed',
            'processed_at'     => now(),
        ]);

        $slug = request()->route('tenant') ?? '';
        return redirect()->route('tenant.payroll.show', ['tenant' => $slug, 'id' => $run->id])
            ->with('success', "Payroll processed for {$employees->count()} employees.");
    }

    public function show(Request $request, string $tenant, string $id): Response
    {
        $run = PayrollRun::findOrFail($id);
        $payslips = Payslip::with('employee:id,employee_id,first_name,last_name,designation,department')
            ->where('payroll_run_id', $run->id)
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

    protected function getWorkingDays(int $year, int $month): int
    {
        $days = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $working = 0;
        for ($d = 1; $d <= $days; $d++) {
            $dow = (int) date('N', mktime(0, 0, 0, $month, $d, $year));
            if ($dow <= 6) $working++; // Mon-Sat
        }
        return $working;
    }

    protected function overtimeRate(Employee $emp): float
    {
        // OT rate = 2x basic hourly rate
        $monthlyBasic = (float) $emp->basic_salary;
        $dailyRate = $monthlyBasic / 26; // 26 working days
        $hourlyRate = $dailyRate / 8;
        return round($hourlyRate * 2, 2);
    }
}
