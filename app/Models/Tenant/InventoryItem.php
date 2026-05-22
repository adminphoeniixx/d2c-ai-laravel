<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $connection = 'tenant';
    protected $fillable = ['name','sku','category','unit','quantity','min_stock_level','cost_price','selling_price','location','status','notes'];
    protected function casts(): array {
        return ['cost_price'=>'decimal:2','selling_price'=>'decimal:2'];
    }
    public function movements(): HasMany { return $this->hasMany(InventoryMovement::class); }
    public function getStockValueAttribute(): float { return round($this->quantity * $this->cost_price, 2); }
    public function getIsLowStockAttribute(): bool { return $this->quantity <= $this->min_stock_level; }
}
