<?php

declare(strict_types=1);

namespace App\Services\Logistics;

use App\Models\Tenant\DeliveryPartner;
use App\Services\Logistics\Clients\BlueDartClient;
use App\Services\Logistics\Clients\DelhiveryClient;
use App\Services\Logistics\Clients\DtdcClient;
use App\Services\Logistics\Clients\EcomExpressClient;
use App\Services\Logistics\Clients\LogisticsClientInterface;
use App\Services\Logistics\Clients\ShiprocketClient;
use App\Services\Logistics\Clients\XpressbeesClient;
use Illuminate\Support\Facades\Crypt;

class LogisticsClientFactory
{
    /**
     * Map of partner slug → client class
     */
    protected static array $clients = [
        'delhivery'    => DelhiveryClient::class,
        'shiprocket'   => ShiprocketClient::class,
        'ecom-express' => EcomExpressClient::class,
        'bluedart'     => BlueDartClient::class,
        'dtdc'         => DtdcClient::class,
        'xpressbees'   => XpressbeesClient::class,
    ];

    /**
     * Required credential fields per partner
     */
    public static function credentialFields(string $slug): array
    {
        return match ($slug) {
            'delhivery'    => ['api_token' => 'API Token (from Delhivery One portal)', 'environment' => 'production'],
            'shiprocket'   => ['email' => 'Shiprocket Login Email', 'password' => 'Shiprocket Password'],
            'ecom-express' => ['username' => 'API Username', 'password' => 'API Password', 'environment' => 'production'],
            'bluedart'     => ['api_key' => 'API Key', 'license_key' => 'License Key'],
            'dtdc'         => ['api_key' => 'X-Access-Token'],
            'xpressbees'   => ['api_token' => 'API Token'],
            'shadowfax'    => ['api_token' => 'API Token'],
            default        => [],
        };
    }

    /**
     * Create an API client for a delivery partner.
     */
    public static function make(DeliveryPartner $partner): ?LogisticsClientInterface
    {
        $clientClass = self::$clients[$partner->slug] ?? null;
        if (!$clientClass) return null;

        $credentials = self::decryptCredentials($partner);
        if (empty($credentials)) return null;

        return new $clientClass($credentials);
    }

    /**
     * Check if a partner has API support.
     */
    public static function hasApiSupport(string $slug): bool
    {
        return isset(self::$clients[$slug]);
    }

    /**
     * Decrypt stored credentials.
     */
    protected static function decryptCredentials(DeliveryPartner $partner): array
    {
        if (empty($partner->api_credentials)) return [];

        try {
            $decrypted = Crypt::decrypt($partner->api_credentials);
            return is_string($decrypted) ? json_decode($decrypted, true) : (array) $decrypted;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
