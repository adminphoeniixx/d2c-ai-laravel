<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->input('month', now()->format('Y-m'));
        $employeeId = $request->input('employee_id');

        try {
            $query = Attendance::with('employee')
                ->whereYear('date', substr($month, 0, 4))
                ->whereMonth('date', substr($month, 5, 2))
                ->orderBy('date', 'desc');

            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            }

            $records = $query->paginate(50)->withQueryString();

            $employees = Employee::where('status', 'active')->orderBy('first_name')->get(['id', 'employee_id', 'first_name', 'last_name']);

            // Monthly summary
            $summary = Attendance::selectRaw("
                employee_id,
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days,
                SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leaves,
                SUM(worked_hours) as total_worked_hours,
                SUM(overtime_hours) as total_overtime_hours
            ")
                ->whereYear('date', substr($month, 0, 4))
                ->whereMonth('date', substr($month, 5, 2))
                ->groupBy('employee_id')
                ->with('employee:id,employee_id,first_name,last_name,designation')
                ->get();
        } catch (\Throwable $e) {
            $records = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);
            $employees = collect();
            $summary = collect();
        }

        return Inertia::render('Tenant/HR/Attendance/Index', [
            'records'   => $records,
            'employees' => $employees,
            'summary'   => $summary,
            'filters'   => ['month' => $month, 'employee_id' => $employeeId],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date'        => ['required', 'date'],
            'check_in'    => ['nullable', 'date_format:H:i'],
            'check_out'   => ['nullable', 'date_format:H:i'],
            'status'      => ['required', 'in:present,absent,half_day,leave,holiday'],
            'notes'       => ['nullable', 'string'],
        ]);

        $hours = Attendance::calculateHours($validated['check_in'] ?? null, $validated['check_out'] ?? null);

        Attendance::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'date' => $validated['date']],
            array_merge($validated, $hours)
        );

        return back()->with('success', 'Attendance recorded.');
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date'       => ['required', 'date'],
            'entries'    => ['required', 'array'],
            'entries.*.employee_id' => ['required', 'exists:employees,id'],
            'entries.*.check_in'    => ['nullable', 'date_format:H:i'],
            'entries.*.check_out'   => ['nullable', 'date_format:H:i'],
            'entries.*.status'      => ['required', 'in:present,absent,half_day,leave,holiday'],
        ]);

        foreach ($validated['entries'] as $entry) {
            $hours = Attendance::calculateHours($entry['check_in'] ?? null, $entry['check_out'] ?? null);
            Attendance::updateOrCreate(
                ['employee_id' => $entry['employee_id'], 'date' => $validated['date']],
                array_merge($entry, $hours, ['date' => $validated['date']])
            );
        }

        return back()->with('success', count($validated['entries']) . ' attendance records saved.');
    }
}
