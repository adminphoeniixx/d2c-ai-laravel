<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'employee_id', 'date', 'check_in', 'check_out',
        'worked_hours', 'overtime_hours', 'late_minutes', 'is_late', 'penalty_amount',
        'check_in_location', 'check_out_location', 'face_verified',
        'source', 'ip_address', 'leave_request_id',
        'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date'              => 'date',
            'worked_hours'      => 'decimal:2',
            'overtime_hours'    => 'decimal:2',
            'penalty_amount'    => 'decimal:2',
            'is_late'           => 'boolean',
            'face_verified'     => 'boolean',
            'check_in_location' => 'array',
            'check_out_location'=> 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * Calculate worked hours, overtime, late status from check-in/check-out times.
     */
    public static function calculateFromTimes(
        ?string $checkIn,
        ?string $checkOut,
        string $shiftStart = '09:00',
        string $shiftEnd = '18:00',
        float $standardHours = 8.0,
        float $lunchBreak = 1.0,
        int $lateThresholdMinutes = 15,
        int $halfDayThresholdMinutes = 120,
        int $overtimeMinMinutes = 30,
    ): array {
        $result = [
            'worked_hours'   => 0,
            'overtime_hours' => 0,
            'late_minutes'   => 0,
            'is_late'        => false,
            'status'         => 'present',
        ];

        if (!$checkIn) {
            $result['status'] = 'absent';
            return $result;
        }

        // Calculate late minutes
        $shiftStartTime = strtotime($shiftStart);
        $checkInTime = strtotime($checkIn);

        if ($checkInTime > $shiftStartTime) {
            $lateMinutes = (int) round(($checkInTime - $shiftStartTime) / 60);

            if ($lateMinutes >= $halfDayThresholdMinutes) {
                $result['status'] = 'half_day';
                $result['late_minutes'] = $lateMinutes;
                $result['is_late'] = true;
            } elseif ($lateMinutes >= $lateThresholdMinutes) {
                $result['late_minutes'] = $lateMinutes;
                $result['is_late'] = true;
            }
        }

        if (!$checkOut) {
            return $result;
        }

        // Calculate worked hours
        $checkOutTime = strtotime($checkOut);
        if ($checkOutTime <= $checkInTime) {
            return $result;
        }

        $totalMinutes = ($checkOutTime - $checkInTime) / 60;

        // Subtract lunch break if worked > 5 hours
        if ($totalMinutes > 300) {
            $totalMinutes -= ($lunchBreak * 60);
        }

        $workedHours = round($totalMinutes / 60, 2);
        $result['worked_hours'] = max(0, $workedHours);

        // Overtime: time after shift_end
        $shiftEndTime = strtotime($shiftEnd);
        if ($checkOutTime > $shiftEndTime) {
            $otMinutes = ($checkOutTime - $shiftEndTime) / 60;
            if ($otMinutes >= $overtimeMinMinutes) {
                $result['overtime_hours'] = round($otMinutes / 60, 2);
            }
        }

        return $result;
    }

    /**
     * Calculate late penalty based on settings.
     */
    public static function calculatePenalty(
        int $lateMinutes,
        int $lateCountThisMonth,
        string $penaltyType = 'fixed',
        float $penaltyAmount = 0,
        float $penaltyPerMinute = 0,
        int $graceCount = 3,
        float $dailySalary = 0,
    ): float {
        if ($lateMinutes <= 0) return 0;
        if ($lateCountThisMonth <= $graceCount) return 0;

        return match ($penaltyType) {
            'fixed'          => $penaltyAmount,
            'per_minute'     => round($lateMinutes * $penaltyPerMinute, 2),
            'per_day_salary' => round($dailySalary / 2, 2), // half day salary deduction
            default          => 0,
        };
    }
}
