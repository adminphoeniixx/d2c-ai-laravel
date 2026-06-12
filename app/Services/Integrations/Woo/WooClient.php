<?php

declare(strict_types=1);

namespace App\Services\Integrations\Woo;

use App\Models\IntegrationAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WooClient
{
    protected string $baseUrl;
    protected string $consumerKey;
    protected string $consumerSecret;

    public function __construct(protected IntegrationAccount $account)
    {
        $this->baseUrl = (string) $this->account->getCredential('base_url');
        $this->consumerKey = (string) $this->account->getCredential('consumer_key');
        $this->consumerSecret = (string) $this->account->getCredential('consumer_secret');

        if (empty($this->baseUrl) || empty($this->consumerKey) || empty($this->consumerSecret)) {
            // Mark account as error with clear message
            $this->account->update([
                'status' => IntegrationAccount::STATUS_ERROR,
                'error_message' => 'Credentials could not be decrypted. This usually happens when APP_KEY changes. Please disconnect and reconnect WooCommerce with fresh credentials.',
            ]);
            throw new \RuntimeException('WooCommerce credentials invalid or corrupted. Please reconnect your store in Settings → Integrations → WooCommerce.');
        }
    }

    public function request(): PendingRequest
    {
        $baseUrl = rtrim($this->baseUrl, '/').'/wp-json/wc/v3/';

        return Http::baseUrl($baseUrl)
            ->withBasicAuth($this->consumerKey, $this->consumerSecret)
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
                'order'    => 'desc',
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
