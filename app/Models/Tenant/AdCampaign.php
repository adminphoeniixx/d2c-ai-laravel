<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    protected $connection = 'tenant';
    protected $fillable = ['platform','external_id','name','status','objective','daily_budget','lifetime_budget','currency','meta'];
    protected function casts(): array {
        return ['daily_budget'=>'decimal:2','lifetime_budget'=>'decimal:2','meta'=>'array'];
    }
    public function dailySpend(): HasMany { return $this->hasMany(AdSpendDaily::class); }
}
