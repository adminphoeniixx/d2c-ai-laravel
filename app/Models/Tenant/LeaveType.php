<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'name', 'code', 'is_paid', 'annual_quota', 'carry_forward',
        'max_carry_forward_days', 'max_consecutive_days', 'requires_approval',
        'is_active', 'description',
    ];

    protected function casts(): array
    {
        return [
            'is_paid'          => 'boolean',
            'carry_forward'    => 'boolean',
            'requires_approval'=> 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    public function balances(): HasMany { return $this->hasMany(LeaveBalance::class); }
    public function requests(): HasMany { return $this->hasMany(LeaveRequest::class); }

    /**
     * Seed default Indian leave types.
     */
    public static function seedDefaults(): void
    {
        $defaults = [
            ['name' => 'Casual Leave',    'code' => 'CL', 'is_paid' => true, 'annual_quota' => 12, 'carry_forward' => false, 'max_consecutive_days' => 3],
            ['name' => 'Sick Leave',       'code' => 'SL', 'is_paid' => true, 'annual_quota' => 12, 'carry_forward' => false, 'max_consecutive_days' => 7],
            ['name' => 'Earned Leave',     'code' => 'EL', 'is_paid' => true, 'annual_quota' => 15, 'carry_forward' => true, 'max_carry_forward_days' => 30, 'max_consecutive_days' => 30],
            ['name' => 'Compensatory Off', 'code' => 'CO', 'is_paid' => true, 'annual_quota' => 0,  'carry_forward' => false, 'max_consecutive_days' => 1],
            ['name' => 'Leave Without Pay','code' => 'LWP','is_paid' => false,'annual_quota' => 0,  'carry_forward' => false, 'max_consecutive_days' => 30],
        ];

        foreach ($defaults as $d) {
            static::firstOrCreate(['code' => $d['code']], $d);
        }
    }
}
