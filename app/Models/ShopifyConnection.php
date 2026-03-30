<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyConnection extends Model
{

    protected $fillable = [
        'tenant_id',
        'shop',
        'access_token',
        'scope',
        'installed_at',
        'last_synced_at',
        'is_active'
    ];

    protected $casts = [

        'access_token' => 'encrypted',

        'installed_at' => 'datetime',

        'last_synced_at' => 'datetime',

        'is_active' => 'boolean',

    ];

}