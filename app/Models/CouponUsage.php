<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    protected $connection = 'pgsql';
    protected $guarded = ['id'];
    protected $casts   = ['used_at' => 'datetime'];
    public function coupon() { return $this->belongsTo(Coupon::class); }
}
