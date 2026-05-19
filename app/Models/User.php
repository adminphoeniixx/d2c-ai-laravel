<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * User model — lives in the central database.
 * Users are scoped to one Company (tenant) via `company_id`.
 * Admins have is_admin=true and company_id=null.
 *
 * @property int     $id
 * @property ?string $company_id
 * @property string  $name
 * @property string  $email
 * @property bool    $is_admin
 */
class User extends Authenticatable
{
    use HasApiTokens;

    // This table lives in the central (public) schema, not tenant schemas
    protected $connection = 'pgsql';
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasRoles;
    use LogsActivity;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $guard_name = 'web';

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = $parts[0] ?? '';
        $last  = count($parts) > 1 ? end($parts) : '';
        return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_admin', 'company_id'])
            ->logOnlyDirty();
    }
}
