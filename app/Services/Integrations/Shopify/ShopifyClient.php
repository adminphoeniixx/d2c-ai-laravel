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
                // Respect Shopify's 429 with Retry-After semantics
                return true;
            }, throw: false);
    }

    /**
     * Fetch orders with cursor pagination. Yields pages until no `next` link.
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
            $body = $response->json('orders', []);
            yield $body;

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
