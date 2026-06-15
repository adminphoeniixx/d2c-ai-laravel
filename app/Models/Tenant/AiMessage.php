<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
