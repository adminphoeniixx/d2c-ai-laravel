<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\AttendanceSetting;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Holiday;
use App\Models\Tenant\WorkSchedule;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Monthly attendance dashboard — calendar grid view.
     */
    public function index(Request $request): Response
    {
        $month = $request->input('month', now()->format('Y-m'));
        $employeeId = $request->input('employee_id');
        $year = (int) substr($month, 0, 4);
        $mon = (int) substr($month, 5, 2);

        try {
            $employees = Employee::where('status', 'active')
                ->orderBy('first_name')
                ->get(['id', 'employee_id', 'first_name', 'last_name', 'designation', 'department']);

            // Get all attendance records for the month
            $query = Attendance::with('employee:id,employee_id,first_name,last_name,designation')
                ->whereYear('date', $year)
                ->whereMonth('date', $mon)
                ->orderBy('date');

            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            }

            $records = $query->get();

            // Group by employee for calendar view
            $byEmployee = $records->groupBy('employee_id');

            // Monthly summary per employee
            $summary = Attendance::selectRaw("
                employee_id,
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days,
                SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leaves,
                SUM(CASE WHEN status = 'holiday' THEN 1 ELSE 0 END) as holidays,
                SUM(CASE WHEN is_late = true THEN 1 ELSE 0 END) as late_count,
                SUM(late_minutes) as total_late_minutes,
                SUM(worked_hours) as total_worked_hours,
                SUM(overtime_hours) as total_overtime_hours,
                SUM(penalty_amount) as total_penalty
            ")
                ->whereYear('date', $year)
                ->whereMonth('date', $mon)
                ->groupBy('employee_id')
                ->get()
                ->keyBy('employee_id');

            // Holidays this month
            $holidays = Holiday::whereYear('date', $year)
                ->whereMonth('date', $mon)
                ->get();

            // Work schedules
            $schedules = WorkSchedule::orderBy('day_of_week')->get();

            // Days in month
            $daysInMonth = Carbon::create($year, $mon)->daysInMonth;

            // Settings
            $settings = AttendanceSetting::getSettings();

        } catch (\Throwable $e) {
            $employees = collect();
            $records = collect();
            $byEmployee = collect();
            $summary = collect();
            $holidays = collect();
            $schedules = collect();
            $daysInMonth = 30;
            $settings = null;
        }

        return Inertia::render('Tenant/HR/Attendance/Index', [
            'records'      => $records,
            'byEmployee'   => $byEmployee,
            'employees'    => $employees,
            'summary'      => $summary,
            'holidays'     => $holidays,
            'schedules'    => $schedules,
            'daysInMonth'  => $daysInMonth,
            'settings'     => $settings,
            'month'        => $month,
            'filters'      => $request->only(['month', 'employee_id']),
        ]);
    }

    /**
     * Manual attendance entry (from company panel).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date'        => ['required', 'date'],
            'check_in'    => ['nullable', 'date_format:H:i'],
            'check_out'   => ['nullable', 'date_format:H:i'],
            'status'      => ['required', 'in:present,absent,half_day,leave,holiday'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $settings = AttendanceSetting::getSettings();
        $service = new AttendanceService();
        $shift = $service->getShiftTimes($employee, Carbon::parse($validated['date']));

        // Calculate hours if check-in/out provided
        $calc = Attendance::calculateFromTimes(
            $validated['check_in'] ?? null,
            $validated['check_out'] ?? null,
            $shift['start'],
            $shift['end'],
            (float) ($settings->standard_hours ?? 8),
            (float) ($settings->lunch_break_hours ?? 1),
            (int) ($settings->late_threshold_minutes ?? 15),
            (int) ($settings->half_day_threshold_minutes ?? 120),
            (int) ($settings->overtime_min_minutes ?? 30),
        );

        Attendance::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'date' => $validated['date']],
            [
                'check_in'       => $validated['check_in'] ?? null,
                'check_out'      => $validated['check_out'] ?? null,
                'worked_hours'   => $calc['worked_hours'],
                'overtime_hours' => $calc['overtime_hours'],
                'late_minutes'   => $calc['late_minutes'],
                'is_late'        => $calc['is_late'],
                'status'         => $validated['status'] !== 'present' ? $validated['status'] : $calc['status'],
                'source'         => 'manual',
                'notes'          => $validated['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Attendance recorded.');
    }

    /**
     * Bulk attendance entry for a date.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date'                     => ['required', 'date'],
            'entries'                  => ['required', 'array'],
            'entries.*.employee_id'    => ['required', 'exists:employees,id'],
            'entries.*.status'         => ['required', 'in:present,absent,half_day,leave'],
            'entries.*.check_in'       => ['nullable', 'date_format:H:i'],
            'entries.*.check_out'      => ['nullable', 'date_format:H:i'],
        ]);

        $settings = AttendanceSetting::getSettings();
        $service = new AttendanceService();

        foreach ($validated['entries'] as $entry) {
            $employee = Employee::find($entry['employee_id']);
            if (!$employee) continue;

            $shift = $service->getShiftTimes($employee, Carbon::parse($validated['date']));

            $calc = Attendance::calculateFromTimes(
                $entry['check_in'] ?? null,
                $entry['check_out'] ?? null,
                $shift['start'],
                $shift['end'],
                (float) $settings->standard_hours,
                (float) $settings->lunch_break_hours,
                (int) $settings->late_threshold_minutes,
                (int) $settings->half_day_threshold_minutes,
                (int) $settings->overtime_min_minutes,
            );

            Attendance::updateOrCreate(
                ['employee_id' => $entry['employee_id'], 'date' => $validated['date']],
                [
                    'check_in'       => $entry['check_in'] ?? null,
                    'check_out'      => $entry['check_out'] ?? null,
                    'worked_hours'   => $calc['worked_hours'],
                    'overtime_hours' => $calc['overtime_hours'],
                    'late_minutes'   => $calc['late_minutes'],
                    'is_late'        => $calc['is_late'],
                    'status'         => $entry['status'] !== 'present' ? $entry['status'] : $calc['status'],
                    'source'         => 'bulk',
                ]
            );
        }

        return back()->with('success', count($validated['entries']) . ' attendance records saved.');
    }

    /**
     * Update a single attendance record (edit from panel).
     */
    public function update(Request $request, string $tenant, Attendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'check_in'    => ['nullable', 'date_format:H:i'],
            'check_out'   => ['nullable', 'date_format:H:i'],
            'status'      => ['required', 'in:present,absent,half_day,leave,holiday'],
            'is_late'     => ['nullable', 'boolean'],
            'late_minutes'=> ['nullable', 'integer', 'min:0'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        // Recalculate hours if times changed
        if (isset($validated['check_in']) || isset($validated['check_out'])) {
            $settings = AttendanceSetting::getSettings();
            $service = new AttendanceService();
            $employee = $attendance->employee;
            $shift = $service->getShiftTimes($employee, $attendance->date);

            $calc = Attendance::calculateFromTimes(
                $validated['check_in'] ?? $attendance->check_in,
                $validated['check_out'] ?? $attendance->check_out,
                $shift['start'], $shift['end'],
                (float) $settings->standard_hours, (float) $settings->lunch_break_hours,
                (int) $settings->late_threshold_minutes, (int) $settings->half_day_threshold_minutes,
                (int) $settings->overtime_min_minutes,
            );

            $validated['worked_hours'] = $calc['worked_hours'];
            $validated['overtime_hours'] = $validated['overtime_hours'] ?? $calc['overtime_hours'];
            $validated['late_minutes'] = $validated['late_minutes'] ?? $calc['late_minutes'];
            $validated['is_late'] = $validated['is_late'] ?? $calc['is_late'];
        }

        $attendance->update($validated);

        return back()->with('success', 'Attendance updated.');
    }

    /**
     * Late & penalty report.
     */
    public function lateReport(Request $request): Response
    {
        $month = $request->input('month', now()->format('Y-m'));
        $year = (int) substr($month, 0, 4);
        $mon = (int) substr($month, 5, 2);

        try {
            $report = Attendance::selectRaw("
                employee_id,
                SUM(CASE WHEN is_late = true THEN 1 ELSE 0 END) as late_count,
                SUM(late_minutes) as total_late_minutes,
                SUM(penalty_amount) as total_penalty,
                AVG(CASE WHEN is_late = true THEN late_minutes ELSE null END) as avg_late_minutes
            ")
                ->whereYear('date', $year)
                ->whereMonth('date', $mon)
                ->groupBy('employee_id')
                ->having('late_count', '>', 0)
                ->with('employee:id,employee_id,first_name,last_name,designation')
                ->orderByDesc('late_count')
                ->get();

            $lateDetails = Attendance::where('is_late', true)
                ->whereYear('date', $year)
                ->whereMonth('date', $mon)
                ->with('employee:id,employee_id,first_name,last_name')
                ->orderBy('date')
                ->get();

            $settings = AttendanceSetting::getSettings();
        } catch (\Throwable $e) {
            $report = collect();
            $lateDetails = collect();
            $settings = null;
        }

        return Inertia::render('Tenant/HR/Attendance/LateReport', [
            'report'      => $report,
            'lateDetails' => $lateDetails,
            'settings'    => $settings,
            'month'       => $month,
        ]);
    }
}
