<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $connection = 'tenant';
    protected $fillable = [
        'ticket_number', 'subject', 'description', 'status', 'priority', 'source', 'category_id',
        'customer_name', 'customer_email', 'customer_phone', 'order_number',
        'assigned_to', 'assigned_at', 'first_responded_at', 'resolved_at', 'closed_at',
        'sla_hours', 'sla_breached', 'tags', 'metadata',
    ];
    protected function casts(): array {
        return ['tags' => 'array', 'metadata' => 'array', 'sla_breached' => 'boolean',
            'assigned_at' => 'datetime', 'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime', 'closed_at' => 'datetime'];
    }
    public function category(): BelongsTo { return $this->belongsTo(SupportCategory::class, 'category_id'); }
    public function replies(): HasMany { return $this->hasMany(SupportReply::class, 'ticket_id'); }

    public static function generateNumber(): string
    {
        $last = static::orderByDesc('id')->value('ticket_number');
        $num = $last ? ((int) substr($last, 4)) + 1 : 1;
        return 'TKT-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
    }

    public function checkSla(): bool
    {
        if ($this->first_responded_at) return true;
        $deadline = $this->created_at->addHours($this->sla_hours);
        if (now()->isAfter($deadline)) {
            $this->update(['sla_breached' => true]);
            return false;
        }
        return true;
    }
}
