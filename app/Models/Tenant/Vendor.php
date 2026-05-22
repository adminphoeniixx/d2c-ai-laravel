<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $connection = 'tenant';
    protected $fillable = ['name','email','phone','gstin','address','city','state','pincode','contact_person','notes'];

    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class); }
}
