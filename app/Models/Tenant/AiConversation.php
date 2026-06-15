<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $guarded = [];

    public function messages()
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('created_at');
    }
}
