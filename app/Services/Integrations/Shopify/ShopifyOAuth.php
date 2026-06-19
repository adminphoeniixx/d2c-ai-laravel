<?php

declare(strict_types=1);

namespace App\Services\Integrations\Shopify;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin wrapper around the Shopify OAuth handshake.
 *
 * Flow:
 *   1. buildAuthorizeUrl(shop, state) — redirect merchant here
 *   2. Shopify redirects back with ?code=&shop=&state=&hmac=&timestamp=
 *   3. verifyHmac() — cryptographic integrity check
 *   4. exchangeCode(shop, code) — returns permanent access_token + scope
 */
class ShopifyOAuth
{
    public function __construct(
        protected string $apiKey,
        protected string $apiSecret,
        protected string $scopes,
    ) {}

    public static function make(): self
    {
        return new self(
            apiKey:    (string) config('services.shopify.key'),
            apiSecret: (string) config('services.shopify.secret'),
            scopes:    (string) config('services.shopify.scopes'),
        );
    }

    /**
     * Request a token via the client_credentials grant — confirmed working
     * for this Dev Dashboard app/store combination, even though it isn't
     * covered in Shopify's published authorization-code-grant docs. No
     * merchant authorization redirect is needed; the app's own client_id +
     * client_secret are sufficient. Returns a token that expires in ~24h
     * with NO refresh_token — it must be regenerated from scratch before
     * each expiry, not refreshed.
     */
    public function requestClientCredentialsToken(string $shop, string $clientId, string $clientSecret): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post("https://{$shop}/admin/oauth/access_token", [
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ])
            ->throw();

        return $response->json();
    }

    public function buildAuthorizeUrl(string $shop, string $state): string
    {
        // Use central (non-tenant) callback URL — Shopify doesn't support wildcards
        $redirectUri = route('integrations.shopify.callback.central');

        $params = http_build_query([
            'client_id'    => $this->apiKey,
            'scope'        => $this->scopes,
            'redirect_uri' => $redirectUri,
            'state'        => $state,
            'grant_options[]' => 'per-user',
        ]);

        // Use offline access with token rotation
        $params = str_replace('grant_options%5B%5D=per-user', 'grant_options%5B%5D=', $params);

        return "https://{$shop}/admin/oauth/authorize?{$params}&access_mode=offline";
    }

    public function exchangeCode(string $shop, string $code): array
    {
        $response = Http::asJson()
            ->acceptJson()
            ->timeout(15)
            ->post("https://{$shop}/admin/oauth/access_token", [
                'client_id'     => $this->apiKey,
                'client_secret' => $this->apiSecret,
                'code'          => $code,
                // Request an expiring offline token from the start so new
                // connections never hit the non-expiring-token rejection.
                'expiring'      => 1,
            ])
            ->throw();

        return $response->json();
    }

    /**
     * Exchange an existing non-expiring offline token for an expiring one.
     * This is Shopify's documented, supported migration path — it accepts
     * the old non-expiring token as input even when that same token has
     * started being rejected by the regular REST/GraphQL Admin API.
     *
     * Returns ['access_token','expires_in','refresh_token','refresh_token_expires_in','scope'].
     * This is a one-time, irreversible migration per shop: the original
     * non-expiring token is revoked by Shopify once this succeeds.
     */
    public function migrateToExpiringToken(string $shop, string $nonExpiringToken): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post("https://{$shop}/admin/oauth/access_token", [
                'client_id'              => $this->apiKey,
                'client_secret'          => $this->apiSecret,
                'grant_type'             => 'urn:ietf:params:oauth:grant-type:token-exchange',
                'subject_token'          => $nonExpiringToken,
                'subject_token_type'     => 'urn:shopify:params:oauth:token-type:offline-access-token',
                'requested_token_type'   => 'urn:shopify:params:oauth:token-type:offline-access-token',
                'expiring'               => 1,
            ])
            ->throw();

        return $response->json();
    }

    /**
     * Use a refresh token to obtain a new access token + refresh token pair.
     * Returns the same shape as migrateToExpiringToken().
     */
    public function refreshExpiringToken(string $shop, string $refreshToken): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post("https://{$shop}/admin/oauth/access_token", [
                'client_id'     => $this->apiKey,
                'client_secret' => $this->apiSecret,
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
            ])
            ->throw();

        return $response->json();
    }

    /**
     * Shopify signs every callback with HMAC-SHA256 using the app's shared secret.
     * We strip `hmac` and `signature`, rebuild the querystring, compare.
     */
    public function verifyHmac(array $query): void
    {
        $provided = $query['hmac'] ?? null;
        unset($query['hmac'], $query['signature']);

        ksort($query);
        $message = urldecode(http_build_query($query));
        $computed = hash_hmac('sha256', $message, $this->apiSecret);

        if (! is_string($provided) || ! hash_equals($computed, $provided)) {
            throw new \RuntimeException('Invalid HMAC signature on Shopify callback.');
        }
    }

    /** Encode company id + nonce into tamper-proof state. */
    public function encodeState(string $companyId): string
    {
        $payload = $companyId.'|'.Str::random(16);
        $sig = hash_hmac('sha256', $payload, $this->apiSecret);
        return rtrim(strtr(base64_encode($payload.'|'.$sig), '+/', '-_'), '=');
    }

    public function decodeState(string $state): string
    {
        $decoded = base64_decode(strtr($state, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('Malformed state.');
        }
        [$companyId, $nonce, $sig] = explode('|', $decoded) + [null, null, null];
        $expected = hash_hmac('sha256', "{$companyId}|{$nonce}", $this->apiSecret);
        if (! is_string($sig) || ! hash_equals($expected, $sig)) {
            throw new \RuntimeException('State signature mismatch.');
        }
        return (string) $companyId;
    }

    /**
     * Encode state for a fresh App Store install where no heyd2c company
     * exists yet — prefixed so the callback can tell it apart from a
     * normal "existing company connecting their store" state.
     */
    public function encodeInstallState(string $shop): string
    {
        $payload = 'install:'.$shop.'|'.Str::random(16);
        $sig = hash_hmac('sha256', $payload, $this->apiSecret);
        return rtrim(strtr(base64_encode($payload.'|'.$sig), '+/', '-_'), '=');
    }

    /**
     * Returns the shop domain if this state was produced by
     * encodeInstallState(), or null if it's a normal company state.
     */
    public function decodeInstallState(string $state): ?string
    {
        $decoded = base64_decode(strtr($state, '-_', '+/'), true);
        if ($decoded === false || !str_starts_with($decoded, 'install:')) {
            return null;
        }
        [$marker, $nonce, $sig] = explode('|', $decoded) + [null, null, null];
        $expected = hash_hmac('sha256', "{$marker}|{$nonce}", $this->apiSecret);
        if (! is_string($sig) || ! hash_equals($expected, $sig)) {
            throw new \RuntimeException('State signature mismatch.');
        }
        return substr((string) $marker, strlen('install:'));
    }
}
