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
}
