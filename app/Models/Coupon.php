<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $connection = 'pgsql';
    protected $guarded = ['id'];
    protected $casts = [
        'is_active'       => 'boolean',
        'first_time_only' => 'boolean',
        'applicable_plans'=> 'array',
        'valid_from'      => 'datetime',
        'valid_until'     => 'datetime',
        'value'           => 'float',
        'max_discount'    => 'float',
    ];

    public function usages() { return $this->hasMany(CouponUsage::class); }
}
