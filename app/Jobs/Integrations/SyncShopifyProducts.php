<?php

declare(strict_types=1);

namespace App\Jobs\Integrations;

use App\Models\IntegrationAccount;
use App\Models\Tenant\InventoryItem;
use App\Models\Tenant\Product;
use App\Services\Integrations\Shopify\ShopifyClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fetches products from Shopify and upserts them into the tenant's
 * `products` table. Also syncs inventory quantity into `inventory_items`
 * so the Inventory module reflects real Shopify stock levels.
 *
 * Each Shopify product can have multiple variants (size/colour combos).
 * We store one row per variant (keyed on variant `external_id`) so that
 * SKU-level inventory tracking works correctly rather than collapsing a
 * product with 5 sizes into a single row with ambiguous quantity.
 */
class SyncShopifyProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public int $accountId) {}

    public function handle(): void
    {
        $account = IntegrationAccount::with('company')->findOrFail($this->accountId);

        if ($account->status !== IntegrationAccount::STATUS_CONNECTED) {
            return;
        }

        $company = $account->company;
        tenancy()->initialize($company);

        $client = new ShopifyClient($account);

        $synced = 0;
        $errors = 0;

        foreach ($client->products() as $shopifyProduct) {
            $variants = $shopifyProduct['variants'] ?? [];
            if (empty($variants)) continue;

            $productTitle = $shopifyProduct['title'] ?? 'Unknown Product';
            $productType  = $shopifyProduct['product_type'] ?? null;
            $vendor       = $shopifyProduct['vendor'] ?? null;
            $status       = $shopifyProduct['status'] ?? 'active';
            $tags         = $shopifyProduct['tags'] ?? '';
            $tagsArray    = array_filter(array_map('trim', explode(',', $tags)));

            foreach ($variants as $variant) {
                try {
                    $variantId = (string) $variant['id'];
                    $sku       = $variant['sku'] ?? null;
                    $price     = (float) ($variant['price'] ?? 0);
                    $compare   = (float) ($variant['compare_at_price'] ?? 0);
                    $qty       = (int) ($variant['inventory_quantity'] ?? 0);
                    $cost      = isset($variant['cost']) ? (float) $variant['cost'] : null;

                    // Build a human-readable name: product + variant option values
                    $optionValues = array_filter([
                        $variant['option1'] ?? null,
                        $variant['option2'] ?? null,
                        $variant['option3'] ?? null,
                    ]);
                    // Don't append "Default Title" (Shopify's placeholder for products without real variants)
                    $hasRealOption = count($optionValues) > 0 && !($optionValues === ['Default Title']);
                    $name = $hasRealOption
                        ? $productTitle . ' – ' . implode(' / ', $optionValues)
                        : $productTitle;

                    // Upsert into products table keyed on variant external_id
                    Product::updateOrCreate(
                        ['external_id' => $variantId, 'provider' => 'shopify'],
                        [
                            'sku'                => $sku,
                            'name'               => $name,
                            'vendor'             => $vendor,
                            'product_type'       => $productType,
                            'status'             => $status,
                            'price'              => $price,
                            'compare_at_price'   => $compare ?: null,
                            'cost'               => $cost,
                            'inventory_quantity' => $qty,
                            'tags'               => $tagsArray,
                            'meta'               => [
                                'shopify_product_id'  => (string) $shopifyProduct['id'],
                                'variant_title'       => $variant['title'] ?? null,
                                'inventory_item_id'   => (string) ($variant['inventory_item_id'] ?? ''),
                            ],
                        ]
                    );

                    // SKU: use the real Shopify SKU when present, otherwise
                    // generate a stable placeholder from the variant ID so
                    // inventory_items.sku (NOT NULL) is always satisfied and
                    // re-syncing the same variant doesn't create duplicates.
                    $effectiveSku = $sku ?: ('SHOPIFY-' . $variantId);

                    InventoryItem::updateOrCreate(
                        ['sku' => $effectiveSku],
                        array_filter([
                            'name'          => $name,
                            'sku'           => $effectiveSku,
                            'category'      => $productType ?: null,
                            'quantity'      => $qty,
                            'selling_price' => $price,
                            'cost_price'    => $cost ?? 0,
                            'status'        => $status === 'active' ? 'active' : 'discontinued',
                        ], fn ($v) => $v !== null)
                    );

                    $synced++;
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('SyncShopifyProducts: variant error', [
                        'product_id' => $shopifyProduct['id'] ?? null,
                        'variant_id' => $variant['id'] ?? null,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        $account->update(['last_synced_at' => now()]);

        Log::info('SyncShopifyProducts: done', [
            'account_id' => $this->accountId,
            'synced'     => $synced,
            'errors'     => $errors,
        ]);

        tenancy()->end();
    }
}
