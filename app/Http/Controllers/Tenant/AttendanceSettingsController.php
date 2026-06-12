<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AttendanceSetting;
use App\Models\Tenant\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSettingsController extends Controller
{
    public function index(): Response
    {
        $settings = AttendanceSetting::getSettings();
        WorkSchedule::seedDefaults(); // ensure 7 rows exist

        return Inertia::render('Tenant/HR/Attendance/Settings', [
            'settings'  => $settings,
            'schedules' => WorkSchedule::orderBy('day_of_week')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shift_start'                => ['required', 'date_format:H:i'],
            'shift_end'                  => ['required', 'date_format:H:i'],
            'standard_hours'             => ['required', 'numeric', 'min:1', 'max:24'],
            'lunch_break_hours'          => ['required', 'numeric', 'min:0', 'max:3'],
            'late_threshold_minutes'     => ['required', 'integer', 'min:0', 'max:240'],
            'half_day_threshold_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'late_penalty_type'          => ['required', 'in:fixed,per_minute,per_day_salary,none'],
            'late_penalty_amount'        => ['nullable', 'numeric', 'min:0'],
            'late_penalty_per_minute'    => ['nullable', 'numeric', 'min:0'],
            'late_grace_count'           => ['required', 'integer', 'min:0', 'max:31'],
            'overtime_rate_multiplier'   => ['required', 'numeric', 'min:1', 'max:5'],
            'overtime_min_minutes'       => ['required', 'integer', 'min:0'],
            'geo_fence_enabled'          => ['nullable', 'boolean'],
            'geo_fence_latitude'         => ['nullable', 'numeric'],
            'geo_fence_longitude'        => ['nullable', 'numeric'],
            'geo_fence_radius_meters'    => ['nullable', 'integer', 'min:10'],
            'face_recognition_required'  => ['nullable', 'boolean'],
            'auto_mark_absent'           => ['nullable', 'boolean'],
            'auto_absent_after'          => ['nullable', 'date_format:H:i'],
        ]);

        $settings = AttendanceSetting::getSettings();
        $settings->update($validated);

        return back()->with('success', 'Attendance settings updated.');
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'schedules'                    => ['required', 'array', 'size:7'],
            'schedules.*.day_of_week'      => ['required', 'integer', 'between:0,6'],
            'schedules.*.is_working_day'   => ['required', 'boolean'],
            'schedules.*.shift_start'      => ['nullable', 'date_format:H:i'],
            'schedules.*.shift_end'        => ['nullable', 'date_format:H:i'],
        ]);

        foreach ($validated['schedules'] as $s) {
            WorkSchedule::updateOrCreate(
                ['day_of_week' => $s['day_of_week']],
                [
                    'is_working_day' => $s['is_working_day'],
                    'shift_start'    => $s['shift_start'] ?? null,
                    'shift_end'      => $s['shift_end'] ?? null,
                ]
            );
        }

        return back()->with('success', 'Work schedule updated.');
    }
}
