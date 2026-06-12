<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportReply extends Model
{
    protected $connection = 'tenant';
    protected $fillable = ['ticket_id', 'body', 'sender_type', 'sender_name', 'sender_email', 'user_id', 'is_internal_note', 'attachments'];
    protected function casts(): array { return ['is_internal_note' => 'boolean', 'attachments' => 'array']; }
    public function ticket(): BelongsTo { return $this->belongsTo(SupportTicket::class, 'ticket_id'); }
}
