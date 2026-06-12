<?php
// app/Models/SubscriptionPlan.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $connection = 'pgsql';
    protected $guarded = ['id'];
    protected $casts = [
        'features'    => 'array',
        'is_free'     => 'boolean',
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
        'price'       => 'float',
        'price_yearly'=> 'float',
        'per_order_charge' => 'float',
    ];

    public function subscriptions() { return $this->hasMany(Subscription::class, 'plan_id'); }

    public function getRazorpayPlanId(): ?string
    {
        $mode = PaymentSetting::getValue('razorpay_mode', 'test');
        return $mode === 'live' ? $this->razorpay_plan_id : $this->razorpay_plan_id_test;
    }
}
