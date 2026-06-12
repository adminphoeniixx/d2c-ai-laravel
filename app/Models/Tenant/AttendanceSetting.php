<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'shift_start', 'shift_end', 'standard_hours', 'lunch_break_hours',
        'late_threshold_minutes', 'half_day_threshold_minutes',
        'late_penalty_type', 'late_penalty_amount', 'late_penalty_per_minute', 'late_grace_count',
        'overtime_rate_multiplier', 'overtime_min_minutes',
        'geo_fence_latitude', 'geo_fence_longitude', 'geo_fence_radius_meters', 'geo_fence_enabled',
        'face_recognition_required',
        'auto_mark_absent', 'auto_absent_after',
    ];

    protected function casts(): array
    {
        return [
            'standard_hours'           => 'decimal:2',
            'lunch_break_hours'        => 'decimal:2',
            'late_penalty_amount'      => 'decimal:2',
            'late_penalty_per_minute'  => 'decimal:2',
            'overtime_rate_multiplier' => 'decimal:2',
            'geo_fence_latitude'       => 'decimal:7',
            'geo_fence_longitude'      => 'decimal:7',
            'geo_fence_enabled'        => 'boolean',
            'face_recognition_required'=> 'boolean',
            'auto_mark_absent'         => 'boolean',
        ];
    }

    /**
     * Get or create the singleton settings row.
     */
    public static function getSettings(): self
    {
        return static::firstOrCreate([], []);
    }
}
