<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\Attendance;
use App\Models\Tenant\AttendanceSetting;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Holiday;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\WorkSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    protected AttendanceSetting $settings;

    public function __construct()
    {
        $this->settings = AttendanceSetting::getSettings();
    }

    /**
     * Get the effective shift times for an employee (custom or default).
     */
    public function getShiftTimes(Employee $employee, ?\DateTimeInterface $date = null): array
    {
        $start = $employee->shift_override_start ?? null;
        $end = $employee->shift_override_end ?? null;

        // Check day-specific schedule
        if ($date) {
            $dow = (int) $date->format('w');
            $schedule = WorkSchedule::where('day_of_week', $dow)->first();
            if ($schedule && $schedule->shift_start) {
                $start = $start ?? $schedule->shift_start;
                $end = $end ?? $schedule->shift_end;
            }
        }

        return [
            'start' => $start ?? $this->settings->shift_start ?? '09:00',
            'end'   => $end ?? $this->settings->shift_end ?? '18:00',
        ];
    }

    /**
     * Check-in an employee.
     */
    public function checkIn(Employee $employee, array $options = []): array
    {
        $today = Carbon::today();
        $now = Carbon::now();

        // Check if already checked in today
        $existing = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing && $existing->check_in) {
            return ['success' => false, 'error' => 'Already checked in today', 'attendance' => $existing];
        }

        // Check if it's a working day
        $holiday = Holiday::isHoliday($today);
        if ($holiday) {
            // Allow check-in on holiday (for overtime calculation) but mark it
            $options['notes'] = ($options['notes'] ?? '') . " [Holiday: {$holiday->name}]";
        }

        if (!WorkSchedule::isWorkingDay($today) && !$holiday) {
            // Weekend — allow but note it
            $options['notes'] = ($options['notes'] ?? '') . ' [Weekend]';
        }

        $shift = $this->getShiftTimes($employee, $today);
        $checkInTime = $now->format('H:i:s');

        // Calculate late status
        $calc = Attendance::calculateFromTimes(
            $checkInTime,
            null,
            $shift['start'],
            $shift['end'],
            (float) ($this->settings->standard_hours ?? 8),
            (float) ($this->settings->lunch_break_hours ?? 1),
            (int) ($this->settings->late_threshold_minutes ?? 15),
            (int) ($this->settings->half_day_threshold_minutes ?? 120),
        );

        // Calculate penalty
        $lateCountThisMonth = Attendance::where('employee_id', $employee->id)
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->where('is_late', true)
            ->count();

        $penalty = Attendance::calculatePenalty(
            $calc['late_minutes'],
            $lateCountThisMonth + 1,
            $this->settings->late_penalty_type ?? 'fixed',
            (float) ($this->settings->late_penalty_amount ?? 0),
            (float) ($this->settings->late_penalty_per_minute ?? 0),
            (int) ($this->settings->late_grace_count ?? 3),
            $this->getDailySalary($employee),
        );

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            [
                'check_in'          => $checkInTime,
                'status'            => $calc['status'],
                'late_minutes'      => $calc['late_minutes'],
                'is_late'           => $calc['is_late'],
                'penalty_amount'    => $penalty,
                'face_verified'     => $options['face_verified'] ?? false,
                'check_in_location' => $options['location'] ?? null,
                'source'            => $options['source'] ?? 'app',
                'ip_address'        => $options['ip_address'] ?? null,
                'notes'             => $options['notes'] ?? null,
            ]
        );

        return [
            'success'    => true,
            'attendance' => $attendance,
            'shift'      => $shift,
            'late'       => $calc['is_late'],
            'late_minutes' => $calc['late_minutes'],
            'penalty'    => $penalty,
        ];
    }

    /**
     * Check-out an employee.
     */
    public function checkOut(Employee $employee, array $options = []): array
    {
        $today = Carbon::today();
        $now = Carbon::now();

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if (!$attendance || !$attendance->check_in) {
            return ['success' => false, 'error' => 'Not checked in today'];
        }

        if ($attendance->check_out) {
            return ['success' => false, 'error' => 'Already checked out today', 'attendance' => $attendance];
        }

        $shift = $this->getShiftTimes($employee, $today);
        $checkOutTime = $now->format('H:i:s');

        // Recalculate with check-out time
        $calc = Attendance::calculateFromTimes(
            $attendance->check_in,
            $checkOutTime,
            $shift['start'],
            $shift['end'],
            (float) ($this->settings->standard_hours ?? 8),
            (float) ($this->settings->lunch_break_hours ?? 1),
            (int) ($this->settings->late_threshold_minutes ?? 15),
            (int) ($this->settings->half_day_threshold_minutes ?? 120),
            (int) ($this->settings->overtime_min_minutes ?? 30),
        );

        $attendance->update([
            'check_out'          => $checkOutTime,
            'worked_hours'       => $calc['worked_hours'],
            'overtime_hours'     => $calc['overtime_hours'],
            'status'             => $attendance->is_late && $calc['status'] === 'present'
                                    ? ($attendance->status === 'half_day' ? 'half_day' : 'present')
                                    : $calc['status'],
            'check_out_location' => $options['location'] ?? null,
            'face_verified'      => $attendance->face_verified || ($options['face_verified'] ?? false),
        ]);

        return [
            'success'      => true,
            'attendance'   => $attendance->fresh(),
            'worked_hours' => $calc['worked_hours'],
            'overtime'     => $calc['overtime_hours'],
        ];
    }

    /**
     * Auto-mark absent for employees who haven't checked in today.
     * Run via scheduled command.
     */
    public function autoMarkAbsent(?\DateTimeInterface $date = null): int
    {
        $date = $date ?? Carbon::today();

        // Skip weekends
        if (!WorkSchedule::isWorkingDay($date)) {
            return 0;
        }

        // Skip holidays
        $holiday = Holiday::isHoliday($date);
        if ($holiday) {
            // Mark all as holiday
            $employees = Employee::where('status', 'active')->get();
            foreach ($employees as $emp) {
                Attendance::firstOrCreate(
                    ['employee_id' => $emp->id, 'date' => $date],
                    ['status' => 'holiday', 'source' => 'auto', 'notes' => $holiday->name]
                );
            }
            return 0;
        }

        // Get employees who have no attendance record for this date
        $employees = Employee::where('status', 'active')
            ->whereNotIn('id', function ($q) use ($date) {
                $q->select('employee_id')->from('attendances')->whereDate('date', $date);
            })
            ->get();

        $count = 0;
        foreach ($employees as $emp) {
            // Check if they have an approved leave for this date
            $leave = LeaveRequest::where('employee_id', $emp->id)
                ->where('status', 'approved')
                ->where('from_date', '<=', $date)
                ->where('to_date', '>=', $date)
                ->first();

            if ($leave) {
                Attendance::create([
                    'employee_id'      => $emp->id,
                    'date'             => $date,
                    'status'           => 'leave',
                    'source'           => 'auto',
                    'leave_request_id' => $leave->id,
                    'notes'            => $leave->leaveType->code ?? 'Leave',
                ]);
            } else {
                Attendance::create([
                    'employee_id' => $emp->id,
                    'date'        => $date,
                    'status'      => 'absent',
                    'source'      => 'auto',
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Validate geo-fence location.
     */
    public function validateLocation(?array $location): bool
    {
        if (!$this->settings->geo_fence_enabled) return true;
        if (!$location || !isset($location['lat'], $location['lng'])) return false;
        if (!$this->settings->geo_fence_latitude || !$this->settings->geo_fence_longitude) return true;

        $distance = $this->haversineDistance(
            (float) $this->settings->geo_fence_latitude,
            (float) $this->settings->geo_fence_longitude,
            (float) $location['lat'],
            (float) $location['lng'],
        );

        return $distance <= ($this->settings->geo_fence_radius_meters ?? 200);
    }

    protected function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }

    protected function getDailySalary(Employee $employee): float
    {
        $monthly = (float) $employee->basic_salary + (float) $employee->hra +
                   (float) $employee->special_allowance + (float) $employee->other_allowance;
        return round($monthly / 26, 2);
    }
}
