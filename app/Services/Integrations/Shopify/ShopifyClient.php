<?php

declare(strict_types=1);

namespace App\Services\Integrations\Shopify;

use App\Models\IntegrationAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Shopify Admin REST client.
 * Using REST over GraphQL here for simpler cursor pagination.
 */
class ShopifyClient
{
    protected string $accessToken;

    public function __construct(protected IntegrationAccount $account)
    {
        $this->ensureFreshToken();

        $this->accessToken = (string) $this->account->getCredential('access_token');

        if (empty($this->accessToken)) {
            $this->account->update([
                'status' => IntegrationAccount::STATUS_ERROR,
                'error_message' => 'Credentials could not be decrypted. This usually happens when APP_KEY changes. Please disconnect and reconnect Shopify.',
            ]);
            throw new \RuntimeException('Shopify credentials invalid or corrupted. Please reconnect your store in Settings → Integrations → Shopify.');
        }
    }

    /**
     * Keep the access token fresh before each request:
     *  - client_credentials mode: no refresh_token exists at all — must
     *    regenerate a brand new token from client_id+client_secret before
     *    the ~24h expiry, since this grant type doesn't support refreshing.
     *  - expiring offline token mode: has a refresh_token — refresh
     *    proactively within 2 minutes of the (much shorter, ~60min) expiry.
     *  - non-expiring legacy token: neither field present — nothing to do.
     */
    protected function ensureFreshToken(): void
    {
        if ($this->account->mode === IntegrationAccount::MODE_CLIENT_CREDENTIALS) {
            $expiresAt = $this->account->getCredential('expires_at');
            $isExpiringSoon = !$expiresAt || now()->addMinutes(5)->gte(\Illuminate\Support\Carbon::parse($expiresAt));
            if ($isExpiringSoon) {
                $this->regenerateClientCredentialsToken();
            }
            return;
        }

        $refreshToken = $this->account->getCredential('refresh_token');
        if (!$refreshToken) {
            return; // non-expiring token (or OAuth token without rotation) — nothing to do
        }

        $expiresAt = $this->account->getCredential('expires_at');
        $isExpiringSoon = !$expiresAt || now()->addMinutes(2)->gte(\Illuminate\Support\Carbon::parse($expiresAt));

        if ($isExpiringSoon) {
            $this->refreshToken();
        }
    }

    /**
     * Regenerate a fresh client_credentials token. Unlike refresh_token
     * exchange, this calls the same endpoint from scratch using the
     * stored client_id/client_secret — there's no rotation/refresh token
     * involved in this grant type.
     */
    protected function regenerateClientCredentialsToken(): bool
    {
        $clientId = $this->account->getCredential('client_id');
        $clientSecret = $this->account->getCredential('client_secret');

        if (!$clientId || !$clientSecret) {
            $this->account->update([
                'status' => IntegrationAccount::STATUS_ERROR,
                'error_message' => 'Missing client_id/client_secret for client_credentials mode. Please reconnect.',
            ]);
            return false;
        }

        try {
            $oauth = ShopifyOAuth::make();
            $data = $oauth->requestClientCredentialsToken($this->account->shop_domain, $clientId, $clientSecret);

            $credentials = $this->account->credentials ?? [];
            $credentials['access_token'] = $data['access_token'];
            if (isset($data['expires_in'])) {
                $credentials['expires_at'] = now()->addSeconds((int) $data['expires_in'])->toIso8601String();
            }

            $this->account->update(['credentials' => $credentials, 'status' => IntegrationAccount::STATUS_CONNECTED, 'error_message' => null]);
            $this->account->refresh();
            $this->accessToken = (string) $this->account->getCredential('access_token');

            return true;
        } catch (\Throwable $e) {
            $this->account->update([
                'status' => IntegrationAccount::STATUS_ERROR,
                'error_message' => 'Shopify token regeneration failed: ' . $e->getMessage(),
            ]);
            return false;
        }
    }

    public function request(): PendingRequest
    {
        $version = config('services.shopify.api_version', '2025-01');

        return Http::baseUrl("https://{$this->account->shop_domain}/admin/api/{$version}/")
            ->withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->retry(3, 1000, function ($exception, $request) {
                // On 403/401, try to get a fresh token
                if ($exception instanceof \Illuminate\Http\Client\RequestException
                    && in_array($exception->response->status(), [401, 403], true)) {
                    $refreshed = $this->account->mode === IntegrationAccount::MODE_CLIENT_CREDENTIALS
                        ? $this->regenerateClientCredentialsToken()
                        : $this->refreshToken();

                    if ($refreshed) {
                        $this->accessToken = (string) $this->account->getCredential('access_token');
                        $request->withHeaders([
                            'X-Shopify-Access-Token' => $this->accessToken,
                        ]);
                        return true;
                    }
                }
                return $exception instanceof \Illuminate\Http\Client\RequestException
                    && $exception->response->status() === 429;
            }, throw: false);
    }

    /**
     * Refresh the expiring offline token using its refresh_token.
     * Stores the new access token, new refresh token, and both
     * expiry timestamps — Shopify rotates the refresh token on every use.
     */
    protected function refreshToken(): bool
    {
        $refreshToken = $this->account->getCredential('refresh_token');
        if (!$refreshToken) {
            return false;
        }

        try {
            $oauth = ShopifyOAuth::make();
            $data = $oauth->refreshExpiringToken($this->account->shop_domain, $refreshToken);

            $this->storeTokenResponse($data);

            return true;
        } catch (\Throwable $e) {
            // If the refresh token itself has expired (90-day window passed,
            // or app reinstalled), the merchant needs to reconnect — surface
            // that clearly rather than silently failing sync after sync.
            $this->account->update([
                'status' => IntegrationAccount::STATUS_ERROR,
                'error_message' => 'Shopify token refresh failed: ' . $e->getMessage() . '. Please reconnect your store.',
            ]);
            return false;
        }
    }

    /**
     * Persist an access_token/refresh_token response from Shopify
     * (used by both refresh and the one-time migration to expiring tokens).
     */
    public function storeTokenResponse(array $data): void
    {
        $credentials = $this->account->credentials ?? [];
        $credentials['access_token'] = $data['access_token'];

        if (!empty($data['refresh_token'])) {
            $credentials['refresh_token'] = $data['refresh_token'];
        }
        if (isset($data['expires_in'])) {
            $credentials['expires_at'] = now()->addSeconds((int) $data['expires_in'])->toIso8601String();
        }
        if (isset($data['refresh_token_expires_in'])) {
            $credentials['refresh_token_expires_at'] = now()->addSeconds((int) $data['refresh_token_expires_in'])->toIso8601String();
        }

        $this->account->update(['credentials' => $credentials, 'status' => IntegrationAccount::STATUS_CONNECTED, 'error_message' => null]);
        $this->account->refresh();
        $this->accessToken = (string) $this->account->getCredential('access_token');
    }

    /**
     * Fetch orders with cursor pagination. Yields individual orders.
     *
     * @return \Generator<int,array>
     */
    public function orders(array $query = []): \Generator
    {
        $params = array_merge([
            'status'        => 'any',
            'limit'         => 100,
            'order'         => 'created_at asc',
        ], $query);

        $nextPageInfo = null;

        do {
            $params = $nextPageInfo
                ? ['limit' => 250, 'page_info' => $nextPageInfo]
                : $params;

            $response = $this->request()->get('orders.json', $params)->throw();
            $orders = $response->json('orders', []);

            // Yield each order individually
            foreach ($orders as $order) {
                yield $order;
            }

            // Parse Link header for page_info
            $link = $response->header('Link') ?? '';
            $nextPageInfo = $this->parseNextPageInfo($link);
        } while ($nextPageInfo !== null);
    }

    /**
     * Update inventory quantity for a specific Shopify variant via the
     * Inventory API. Requires the inventory_item_id (not the variant id).
     *
     * We get the location_id from the existing inventory level for this
     * item rather than calling locations.json — this avoids needing the
     * read_locations scope, which client_credentials tokens often lack.
     *
     * Returns true on success, false if Shopify rejected the update.
     */
    public function updateInventoryQuantity(string $inventoryItemId, int $quantity): bool
    {
        // Get the current inventory level for this item — tells us which
        // location_id it's tracked at without needing read_locations scope.
        $levelResponse = $this->request()->get('inventory_levels.json', [
            'inventory_item_ids' => $inventoryItemId,
        ]);

        if (!$levelResponse->successful()) {
            Log::warning('ShopifyClient: could not fetch inventory levels', [
                'status' => $levelResponse->status(),
                'body'   => $levelResponse->body(),
            ]);
            return false;
        }

        $levels = $levelResponse->json('inventory_levels', []);
        if (empty($levels)) {
            Log::warning('ShopifyClient: no inventory levels found for item', [
                'inventory_item_id' => $inventoryItemId,
            ]);
            return false;
        }

        $locationId = $levels[0]['location_id'];

        // Set the absolute quantity at that location
        $response = $this->request()->post('inventory_levels/set.json', [
            'location_id'       => $locationId,
            'inventory_item_id' => (int) $inventoryItemId,
            'available'         => $quantity,
        ]);

        if (!$response->successful()) {
            Log::warning('ShopifyClient: inventory set failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        return $response->successful();
    }

    /**
     * Fetch products with cursor pagination. Yields individual products.
     *
     * @return \Generator<int,array>
     */
    public function products(array $query = []): \Generator
    {
        $params = array_merge([
            'limit' => 100,
        ], $query);

        $nextPageInfo = null;

        do {
            $params = $nextPageInfo
                ? ['limit' => 100, 'page_info' => $nextPageInfo]
                : $params;

            $response = $this->request()->get('products.json', $params)->throw();
            $products = $response->json('products', []);

            foreach ($products as $product) {
                yield $product;
            }

            $link = $response->header('Link') ?? '';
            $nextPageInfo = $this->parseNextPageInfo($link);
        } while ($nextPageInfo !== null);
    }

    private function parseNextPageInfo(string $linkHeader): ?string
    {
        if (preg_match('/<([^>]+)>;\s*rel="next"/', $linkHeader, $m)) {
            $parts = parse_url($m[1]);
            parse_str($parts['query'] ?? '', $q);
            return $q['page_info'] ?? null;
        }
        return null;
    }
}
