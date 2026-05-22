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
        'worked_hours', 'overtime_hours', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date'           => 'date',
            'worked_hours'   => 'decimal:2',
            'overtime_hours' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Auto-calculate worked_hours and overtime from check_in/check_out.
     * Standard work day = 8 hours. Anything above is overtime.
     */
    public static function calculateHours(?string $checkIn, ?string $checkOut, float $standardHours = 8.0): array
    {
        if (!$checkIn || !$checkOut) {
            return ['worked_hours' => 0, 'overtime_hours' => 0];
        }

        $in = strtotime($checkIn);
        $out = strtotime($checkOut);
        if ($out <= $in) {
            return ['worked_hours' => 0, 'overtime_hours' => 0];
        }

        $totalHours = round(($out - $in) / 3600, 2);
        // Subtract 1 hour for lunch if worked > 5 hours
        if ($totalHours > 5) {
            $totalHours -= 1;
        }

        $overtime = max(0, round($totalHours - $standardHours, 2));

        return [
            'worked_hours'   => round($totalHours, 2),
            'overtime_hours' => $overtime,
        ];
    }
}
