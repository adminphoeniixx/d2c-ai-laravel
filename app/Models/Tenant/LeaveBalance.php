<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'allocated', 'used', 'carried_forward'];

    protected function casts(): array
    {
        return ['allocated' => 'decimal:1', 'used' => 'decimal:1', 'carried_forward' => 'decimal:1'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }

    public function getRemainingAttribute(): float
    {
        return (float) $this->allocated + (float) $this->carried_forward - (float) $this->used;
    }

    /**
     * Initialize leave balances for an employee for the given year.
     */
    public static function initializeForEmployee(int $employeeId, int $year): void
    {
        $types = LeaveType::where('is_active', true)->get();
        foreach ($types as $type) {
            static::firstOrCreate(
                ['employee_id' => $employeeId, 'leave_type_id' => $type->id, 'year' => $year],
                ['allocated' => $type->annual_quota, 'used' => 0, 'carried_forward' => 0]
            );
        }
    }
}
