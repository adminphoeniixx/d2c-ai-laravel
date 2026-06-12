<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['day_of_week', 'is_working_day', 'shift_start', 'shift_end', 'label'];

    protected function casts(): array
    {
        return ['is_working_day' => 'boolean'];
    }

    public const DAY_LABELS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * Seed default Mon-Sat working, Sunday off.
     */
    public static function seedDefaults(): void
    {
        foreach (self::DAY_LABELS as $i => $label) {
            static::firstOrCreate(
                ['day_of_week' => $i],
                ['is_working_day' => $i >= 1 && $i <= 6, 'label' => substr($label, 0, 3)]
            );
        }
    }

    /**
     * Check if a given date is a working day.
     */
    public static function isWorkingDay(\DateTimeInterface $date): bool
    {
        $dow = (int) $date->format('w'); // 0=Sun
        $schedule = static::where('day_of_week', $dow)->first();
        return $schedule ? $schedule->is_working_day : ($dow >= 1 && $dow <= 6);
    }
}
