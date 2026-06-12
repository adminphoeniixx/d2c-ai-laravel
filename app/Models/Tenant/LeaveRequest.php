<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'employee_id', 'leave_type_id', 'from_date', 'to_date', 'days',
        'reason', 'status', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'from_date'   => 'date',
            'to_date'     => 'date',
            'days'        => 'decimal:1',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
}
