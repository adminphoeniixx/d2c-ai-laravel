<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportFaq extends Model
{
    protected $connection = 'tenant';
    protected $table = 'support_faq';
    protected $fillable = ['category_id', 'question', 'answer', 'sort_order', 'is_active', 'helpful_count'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function category(): BelongsTo { return $this->belongsTo(SupportCategory::class, 'category_id'); }
}
