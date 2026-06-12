<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdInvoice extends Model
{
    protected $connection = 'tenant';
    protected $fillable = ['platform', 'invoice_number', 'invoice_date', 'period_from', 'period_to',
        'subtotal', 'tax', 'total_amount', 'currency', 'file_url', 'status', 'entry_count', 'metadata'];
    protected function casts(): array {
        return ['invoice_date' => 'date', 'period_from' => 'date', 'period_to' => 'date',
            'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'total_amount' => 'decimal:2', 'metadata' => 'array'];
    }
    public function entries(): HasMany { return $this->hasMany(AdSpendManual::class, 'ad_invoice_id'); }
}
