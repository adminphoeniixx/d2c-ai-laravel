<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $connection = 'tenant';
    protected $fillable = ['name', 'account_number', 'ifsc', 'bank_name', 'type', 'opening_balance', 'is_primary'];
    protected function casts(): array { return ['is_primary' => 'boolean', 'opening_balance' => 'decimal:2']; }
    public function transactions(): HasMany { return $this->hasMany(BankTransaction::class); }
}
