<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSpendManual extends Model
{
    protected $connection = 'tenant';
    protected $table = 'ad_spend_manual';
    protected $fillable = ['ad_invoice_id', 'platform', 'date', 'campaign_name', 'spend',
        'impressions', 'clicks', 'conversions', 'conversion_value', 'source', 'raw_data'];
    protected function casts(): array {
        return ['date' => 'date', 'spend' => 'decimal:2', 'conversion_value' => 'decimal:2', 'raw_data' => 'array'];
    }
    public function invoice(): BelongsTo { return $this->belongsTo(AdInvoice::class, 'ad_invoice_id'); }
}
