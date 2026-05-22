<?php

declare(strict_types=1);

namespace App\Services\Integrations\Shopify;

use App\Models\IntegrationAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Shopify Admin REST client.
 * Using REST over GraphQL here for simpler cursor pagination.
 */
class ShopifyClient
{
    public function __construct(protected IntegrationAccount $account) {}

    public function request(): PendingRequest
    {
        $version = config('services.shopify.api_version', '2025-01');

        return Http::baseUrl("https://{$this->account->shop_domain}/admin/api/{$version}/")
            ->withHeaders([
                'X-Shopify-Access-Token' => $this->account->getCredential('access_token'),
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->retry(3, 1000, function ($exception, $request) {
                // On 403, try to refresh the token
                if ($exception instanceof \Illuminate\Http\Client\RequestException && $exception->response->status() === 403) {
                    if ($this->refreshToken()) {
                        $request->withHeaders([
                            'X-Shopify-Access-Token' => $this->account->getCredential('access_token'),
                        ]);
                        return true;
                    }
                }
                return $exception instanceof \Illuminate\Http\Client\RequestException
                    && $exception->response->status() === 429;
            }, throw: false);
    }

    /**
     * Refresh the OAuth token using the refresh_token.
     */
    protected function refreshToken(): bool
    {
        $refreshToken = $this->account->getCredential('refresh_token');
        if (!$refreshToken) {
            return false;
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(15)
                ->post("https://{$this->account->shop_domain}/admin/oauth/access_token", [
                    'client_id'     => config('services.shopify.key'),
                    'client_secret' => config('services.shopify.secret'),
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ])
                ->throw();

            $data = $response->json();

            // Update stored credentials
            $credentials = $this->account->credentials;
            $credentials['access_token'] = $data['access_token'];
            if (!empty($data['refresh_token'])) {
                $credentials['refresh_token'] = $data['refresh_token'];
            }
            $credentials['expires_in'] = $data['expires_in'] ?? null;

            $this->account->update(['credentials' => $credentials, 'status' => IntegrationAccount::STATUS_CONNECTED]);
            $this->account->refresh();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
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
