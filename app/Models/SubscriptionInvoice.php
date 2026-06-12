<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    protected $connection = 'pgsql';
    protected $guarded = ['id'];
    protected $casts   = ['paid_at' => 'datetime'];
    public function subscription() { return $this->belongsTo(Subscription::class); }
}
