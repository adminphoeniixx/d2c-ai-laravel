<?php

declare(strict_types=1);

namespace App\Services\Integrations\Woo;

use App\Models\IntegrationAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WooClient
{
    public function __construct(protected IntegrationAccount $account) {}

    public function request(): PendingRequest
    {
        $baseUrl = rtrim((string) $this->account->getCredential('base_url'), '/').'/wp-json/wc/v3/';

        return Http::baseUrl($baseUrl)
            ->withBasicAuth(
                (string) $this->account->getCredential('consumer_key'),
                (string) $this->account->getCredential('consumer_secret'),
            )
            ->acceptJson()
            ->timeout(30)
            ->retry(3, 1000);
    }

    /**
     * Yield pages of orders with page-based pagination.
     *
     * @return \Generator<int,array>
     */
    public function orders(array $query = []): \Generator
    {
        $page = 1;
        $perPage = 100;

        do {
            $response = $this->request()->get('orders', array_merge([
                'per_page' => $perPage,
                'page'     => $page,
                'orderby'  => 'date',
                'order'    => 'asc',
            ], $query))->throw();

            $orders = $response->json();
            if (empty($orders)) {
                break;
            }
            yield $orders;

            $totalPages = (int) ($response->header('X-WP-TotalPages') ?? 1);
            $page++;
        } while ($page <= $totalPages);
    }
}
