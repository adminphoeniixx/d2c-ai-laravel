<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $connection = 'tenant';
    protected $fillable = [
        'bank_account_id', 'date', 'type', 'amount', 'balance', 'description',
        'reference', 'category', 'vendor', 'source', 'upload_batch', 'raw_data',
    ];
    protected function casts(): array {
        return ['date' => 'date', 'amount' => 'decimal:2', 'balance' => 'decimal:2', 'raw_data' => 'array'];
    }
    public function account(): BelongsTo { return $this->belongsTo(BankAccount::class, 'bank_account_id'); }
}
