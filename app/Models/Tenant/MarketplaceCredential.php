<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class MarketplaceCredential extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['marketplace', 'status', 'credentials', 'settings', 'last_synced_at', 'last_error'];

    protected function casts(): array
    {
        return [
            'settings'       => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Get decrypted credentials.
     */
    public function getDecryptedCredentials(): array
    {
        $raw = $this->attributes['credentials'] ?? '{}';
        try {
            return json_decode(Crypt::decryptString($raw), true) ?: [];
        } catch (\Throwable $e) {
            // Fallback: maybe stored as plain JSON during development
            return json_decode($raw, true) ?: [];
        }
    }

    /**
     * Set encrypted credentials.
     */
    public function setEncryptedCredentials(array $creds): void
    {
        $this->attributes['credentials'] = Crypt::encryptString(json_encode($creds));
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
