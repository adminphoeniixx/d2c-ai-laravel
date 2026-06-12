<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['date', 'name', 'type', 'is_paid', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_paid' => 'boolean'];
    }

    /**
     * Check if a given date is a holiday.
     */
    public static function isHoliday(\DateTimeInterface $date): ?self
    {
        return static::whereDate('date', $date->format('Y-m-d'))->first();
    }
}
