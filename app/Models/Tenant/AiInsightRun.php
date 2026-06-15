<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $generated_at
 * @property string $status
 * @property string|null $error
 */
class AiInsightRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'generated_at' => 'datetime',
    ];
}
