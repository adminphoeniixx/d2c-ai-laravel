<?php

declare(strict_types=1);

namespace App\Jobs\Integrations;

use App\Events\IntegrationSyncCompleted;
use App\Models\IntegrationAccount;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Services\GST\GSTCalculator;
use App\Services\GST\StateCodeMap;
use App\Services\Integrations\Shopify\ShopifyClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Fetches orders from a company's Shopify store and upserts them into the
 * tenant's Postgres schema. Uses cursor pagination.
 *
 * Dispatch this on the `integrations` queue so it doesn't block default work.
 */
class SyncShopifyOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        public int  $accountId,
        public bool $backfill = false,
    ) {}

    public function handle(): void
    {
        /** @var IntegrationAccount $account */
        $account = IntegrationAccount::with('company')->findOrFail($this->accountId);

        if ($account->status !== IntegrationAccount::STATUS_CONNECTED) {
            return;
        }

        $company = $account->company;

        // Switch into tenant schema for this job
        tenancy()->initialize($company);

        $client = new ShopifyClient($account);

        $query = $this->backfill
            ? ['created_at_min' => Carbon::now()->subYear()->toIso8601String()]
            : ['updated_at_min' => optional($account->last_synced_at)->toIso8601String()
                                   ?? Carbon::now()->subDay()->toIso8601String()];

        $total = 0;
        $failed = 0;

        try {
            foreach ($client->orders($query) as $page) {
                foreach ($page as $payload) {
                    try {
                        $this->upsertOrder($payload);
                        $total++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('Shopify order upsert failed', [
                            'order_id' => $payload['id'] ?? null,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            }

            $account->update([
                'last_synced_at' => now(),
                'status'         => IntegrationAccount::STATUS_CONNECTED,
                'error_message'  => null,
            ]);

            event(new IntegrationSyncCompleted(
                companyId: $company->id,
                provider: 'shopify',
                orderCount: $total,
                failed: $failed,
            ));
        } catch (\Throwable $e) {
            $account->update([
                'status'        => IntegrationAccount::STATUS_ERROR,
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            tenancy()->end();
        }
    }

    protected function upsertOrder(array $o): void
    {
        $company = tenancy()->tenant;

        // Calculate GST split if company has GSTIN configured
        $gstData = null;
        if ($company->registered_state_code) {
            $gstData = GSTCalculator::calculateForShopifyOrder(
                order: $o,
                sellerStateCode: $company->registered_state_code,
                businessCategory: $company->business_category ?? 'other',
                defaultGstRate: $company->default_gst_rate,
            );
        }

        // Determine buyer state from shipping address
        $buyerStateCode = null;
        $shippingProvince = $o['shipping_address']['province_code'] ?? null;
        if ($shippingProvince) {
            $buyerStateCode = StateCodeMap::shopifyProvinceToStateCode($shippingProvince);
        }

        $order = Order::updateOrCreate(
            ['provider' => 'shopify', 'external_id' => (string) $o['id']],
            [
                'order_number'       => $o['name'] ?? '#'.$o['order_number'],
                'status'             => $o['financial_status'] ?? 'pending',
                'financial_status'   => $o['financial_status'] ?? null,
                'fulfillment_status' => $o['fulfillment_status'] ?? null,
                'currency'           => $o['currency'] ?? 'INR',
                'subtotal'           => (float) ($o['subtotal_price'] ?? 0),
                'total_tax'          => (float) ($o['total_tax'] ?? 0),
                'total_discount'     => (float) ($o['total_discounts'] ?? 0),
                'total_shipping'     => (float) ($o['total_shipping_price_set']['shop_money']['amount'] ?? 0),
                'total_amount'       => (float) ($o['total_price'] ?? 0),
                // GST fields
                'taxable_amount'     => $gstData['taxable_amount'] ?? 0,
                'cgst_amount'        => $gstData['cgst_amount'] ?? 0,
                'sgst_amount'        => $gstData['sgst_amount'] ?? 0,
                'igst_amount'        => $gstData['igst_amount'] ?? 0,
                'gst_rate'           => $gstData['line_items'][0]['gst_rate'] ?? null,
                'place_of_supply'    => $gstData['place_of_supply'] ?? null,
                'is_intra_state'     => $gstData['is_intra_state'] ?? null,
                'buyer_state_code'   => $buyerStateCode,
                // Customer fields
                'customer_email'     => $o['email'] ?? null,
                'customer_name'      => trim(($o['customer']['first_name'] ?? '').' '.($o['customer']['last_name'] ?? '')),
                'customer_phone'     => $o['phone'] ?? ($o['customer']['phone'] ?? null),
                'shipping_address'   => $o['shipping_address'] ?? null,
                'billing_address'    => $o['billing_address'] ?? null,
                'line_item_count'    => count($o['line_items'] ?? []),
                'tags'               => array_filter(array_map('trim', explode(',', (string) ($o['tags'] ?? '')))),
                'raw_payload'        => $o,
                'placed_at'          => isset($o['created_at']) ? Carbon::parse($o['created_at']) : now(),
            ]
        );

        // Replace line items with GST breakup
        $order->items()->delete();
        $gstLineItems = $gstData['line_items'] ?? [];

        foreach (($o['line_items'] ?? []) as $idx => $li) {
            $itemGst = $gstLineItems[$idx] ?? null;

            OrderItem::create([
                'order_id'       => $order->id,
                'external_id'    => (string) ($li['id'] ?? ''),
                'sku'            => $li['sku'] ?? null,
                'product_name'   => $li['name'] ?? $li['title'] ?? 'Unknown',
                'variant_name'   => $li['variant_title'] ?? null,
                'quantity'       => (int) ($li['quantity'] ?? 1),
                'unit_price'     => (float) ($li['price'] ?? 0),
                'total_price'    => (float) ($li['price'] ?? 0) * (int) ($li['quantity'] ?? 1),
                'tax_amount'     => (float) ($li['total_tax'] ?? 0),
                // GST fields per item
                'gst_rate'       => $itemGst['gst_rate'] ?? null,
                'taxable_amount' => $itemGst['taxable_amount'] ?? 0,
                'cgst_amount'    => $itemGst['cgst_amount'] ?? 0,
                'sgst_amount'    => $itemGst['sgst_amount'] ?? 0,
                'igst_amount'    => $itemGst['igst_amount'] ?? 0,
            ]);
        }
    }
}
