<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class TeamInvitation extends Model
{
    protected $connection = 'tenant';
    protected $fillable = ['email', 'role', 'token', 'invited_by', 'expires_at', 'accepted_at'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'accepted_at' => 'datetime']; }
    public function isExpired(): bool { return now()->isAfter($this->expires_at); }
    public function isAccepted(): bool { return !is_null($this->accepted_at); }
}
