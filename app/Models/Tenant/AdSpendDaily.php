<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSpendDaily extends Model
{
    protected $connection = 'tenant';
    protected $table = 'ad_spend_daily';
    protected $fillable = ['ad_campaign_id','platform','date','spend','impressions','clicks','conversions','conversion_value','cpm','cpc','ctr','roas','currency','meta'];
    protected function casts(): array {
        return ['date'=>'date','spend'=>'decimal:2','conversion_value'=>'decimal:2','cpm'=>'decimal:2','cpc'=>'decimal:2','ctr'=>'decimal:4','roas'=>'decimal:2','meta'=>'array'];
    }
    public function campaign(): BelongsTo { return $this->belongsTo(AdCampaign::class, 'ad_campaign_id'); }
}
