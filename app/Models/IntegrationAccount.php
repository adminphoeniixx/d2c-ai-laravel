<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One company may have multiple integration accounts (typically one per provider).
 *
 * @property int    $id
 * @property string $company_id
 * @property string $provider   shopify|woocommerce
 * @property string $mode       oauth|manual
 * @property string $status     connected|disconnected|error
 * @property string $shop_domain
 * @property array  $credentials  Encrypted: access_token, consumer_key, consumer_secret, etc.
 * @property array  $scopes
 * @property array  $meta
 * @property ?\Carbon\CarbonImmutable $last_synced_at
 * @property ?\Carbon\CarbonImmutable $connected_at
 */
class IntegrationAccount extends Model
{
    use HasFactory;

    // This table lives in the central (public) schema, not tenant schemas
    protected $connection = 'pgsql';

    public const PROVIDER_SHOPIFY = 'shopify';
    public const PROVIDER_WOO     = 'woocommerce';
    public const PROVIDER_META    = 'meta_ads';
    public const PROVIDER_GOOGLE  = 'google_ads';

    public const MODE_OAUTH  = 'oauth';
    public const MODE_MANUAL = 'manual';

    public const STATUS_CONNECTED    = 'connected';
    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_ERROR        = 'error';

    protected $fillable = [
        'company_id', 'provider', 'mode', 'status',
        'shop_domain', 'credentials', 'scopes', 'meta',
        'last_synced_at', 'connected_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'credentials'    => 'encrypted:array',
            'scopes'         => 'array',
            'meta'           => 'array',
            'last_synced_at' => 'immutable_datetime',
            'connected_at'   => 'immutable_datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getCredential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials ?? [], $key, $default);
    }
}
