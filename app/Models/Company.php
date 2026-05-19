<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Company = Tenant. Each company owns a Postgres schema `tenant_<uuid>`.
 */
class Company extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasFactory;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PENDING   = 'pending';

    public const PLAN_FREE       = 'free';
    public const PLAN_PRO        = 'pro';
    public const PLAN_ENTERPRISE = 'enterprise';

    protected $table = 'companies';

    public static function getCustomColumns(): array
    {
        return [
            'id', 'slug', 'name', 'email', 'status', 'plan',
            'gstin', 'registered_state_code', 'business_category', 'default_gst_rate',
            'country', 'currency', 'timezone', 'settings',
            'shopify_connected_at', 'woo_connected_at',
            'suspended_at', 'trial_ends_at',
        ];
    }

    protected function casts(): array
    {
        return [
            'settings'             => 'array',
            'default_gst_rate'     => 'float',
            'shopify_connected_at' => 'immutable_datetime',
            'woo_connected_at'     => 'immutable_datetime',
            'suspended_at'         => 'immutable_datetime',
            'trial_ends_at'        => 'immutable_datetime',
        ];
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function integrationAccounts(): HasMany
    {
        return $this->hasMany(IntegrationAccount::class);
    }

    public static function booted(): void
    {
        static::creating(function (self $company) {
            if (empty($company->id)) {
                $company->id = (string) Str::uuid();
            }
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name);
            }
            $company->status   ??= self::STATUS_ACTIVE;
            $company->plan     ??= self::PLAN_FREE;
            $company->currency ??= 'INR';
            $company->timezone ??= 'UTC';
        });
    }
}
