<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $type          alert|opportunity
 * @property string $severity      high|medium|low
 * @property string $title
 * @property string $description
 * @property string|null $action_label
 * @property string|null $action_page
 * @property array|null  $metric
 */
class AiInsight extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'metric' => 'array',
    ];
}
