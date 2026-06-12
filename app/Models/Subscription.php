<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $connection = 'pgsql';
    protected $guarded = ['id'];
    protected $casts = [
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
        'cancelled_at' => 'datetime',
        'trial_ends_at'=> 'datetime',
        'metadata'     => 'array',
        'amount'       => 'float',
        'discount_amount' => 'float',
        'tax_amount'   => 'float',
        'final_amount' => 'float',
    ];

    public function plan()    { return $this->belongsTo(SubscriptionPlan::class, 'plan_id'); }
    public function company() { return $this->belongsTo(Company::class, 'company_id', 'id'); }
    public function invoices(){ return $this->hasMany(SubscriptionInvoice::class); }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isExpired(): bool { return $this->ends_at && $this->ends_at->isPast(); }
}
