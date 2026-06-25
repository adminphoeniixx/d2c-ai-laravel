<?php

declare(strict_types=1);

namespace App\Jobs\Integrations;

use App\Events\IntegrationSyncCompleted;
use App\Models\IntegrationAccount;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Services\Integrations\Woo\WooClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncWooOrders implements ShouldQueue
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
        tenancy()->initialize($company);

        $client = new WooClient($account);

        $query = $this->backfill
            ? ['status' => 'any']
            : ['modified_after' => optional($account->last_synced_at)->toIso8601String()
                                    ?? Carbon::now()->subDay()->toIso8601String(), 'status' => 'any'];

        $total = 0;
        $failed = 0;

        try {
            foreach ($client->orders($query) as $page) {
                foreach ($page as $o) {
                    try {
                        $this->upsert($o);
                        $total++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('Woo order upsert failed', [
                            'order_id' => $o['id'] ?? null,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Clean up trashed/deleted orders from WooCommerce
            try {
                foreach ($client->orders(['status' => 'trash']) as $page) {
                    foreach ($page as $o) {
                        $existing = Order::where('provider', 'woocommerce')
                            ->where('external_id', (string) $o['id'])
                            ->first();
                        if ($existing) {
                            $existing->items()->delete();
                            $existing->delete();
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Trash cleanup is best-effort
            }

            $account->update(['last_synced_at' => now(), 'status' => IntegrationAccount::STATUS_CONNECTED, 'error_message' => null]);

            try {
                event(new IntegrationSyncCompleted($company->id, 'woocommerce', $total, $failed));
            } catch (\Throwable $logErr) {
                // Don't let logging failure override connected status
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            // DNS resolution failures (cURL error 6) and connection refused
            // errors mean the WooCommerce site is down, deleted, or the
            // domain has expired. Retrying 3 times wastes queue capacity
            // since these are permanent failures, not transient ones.
            // Mark as disconnected with a human-readable message so the
            // user knows to reconnect or update the store URL.
            $isPermanentFailure = str_contains($message, 'cURL error 6')
                || str_contains($message, 'Could not resolve host')
                || str_contains($message, 'Domain name not found')
                || str_contains($message, 'Connection refused');

            if ($isPermanentFailure) {
                $account->update([
                    'status'        => IntegrationAccount::STATUS_ERROR,
                    'error_message' => 'Could not connect to your WooCommerce store. The domain may have expired or the store may be offline. Please check your store URL in Integrations.',
                ]);
                // Don't throw — this stops the job from retrying pointlessly
                return;
            }

            $account->update(['status' => IntegrationAccount::STATUS_ERROR, 'error_message' => $message]);
            throw $e;
        } finally {
            tenancy()->end();
        }
    }

    protected function upsert(array $o): void
    {
        // If order was trashed/deleted in WooCommerce, remove from our DB
        if (in_array($o['status'] ?? '', ['trash', 'auto-draft', 'draft'])) {
            $existing = Order::where('provider', 'woocommerce')
                ->where('external_id', (string) $o['id'])
                ->first();
            if ($existing) {
                $existing->items()->delete();
                $existing->delete();
            }
            return;
        }

        $order = Order::updateOrCreate(
            ['provider' => 'woocommerce', 'external_id' => (string) $o['id']],
            [
                'order_number'       => '#'.$o['number'],
                'status'             => $o['status'] ?? 'pending',
                'financial_status'   => $o['status'] ?? null,
                'fulfillment_status' => $o['status'] ?? null,
                'currency'           => $o['currency'] ?? 'INR',
                'subtotal'           => array_sum(array_column($o['line_items'] ?? [], 'subtotal')),
                'total_tax'          => (float) ($o['total_tax'] ?? 0),
                'total_discount'     => (float) ($o['discount_total'] ?? 0),
                'total_shipping'     => (float) ($o['shipping_total'] ?? 0),
                'total_amount'       => (float) ($o['total'] ?? 0),
                'customer_email'     => $o['billing']['email'] ?? null,
                'customer_name'      => trim(($o['billing']['first_name'] ?? '').' '.($o['billing']['last_name'] ?? '')),
                'customer_phone'     => $o['billing']['phone'] ?? null,
                'shipping_address'   => $o['shipping'] ?? null,
                'billing_address'    => $o['billing'] ?? null,
                'line_item_count'    => count($o['line_items'] ?? []),
                'tags'               => [],
                'raw_payload'        => $o,
                'placed_at'          => isset($o['date_created']) ? Carbon::parse($o['date_created']) : now(),
            ]
        );

        $order->items()->delete();
        foreach (($o['line_items'] ?? []) as $li) {
            OrderItem::create([
                'order_id'     => $order->id,
                'external_id'  => (string) ($li['id'] ?? ''),
                'sku'          => $li['sku'] ?? null,
                'product_name' => $li['name'] ?? 'Unknown',
                'variant_name' => null,
                'quantity'     => (int) ($li['quantity'] ?? 1),
                'unit_price'   => (float) ($li['price'] ?? 0),
                'total_price'  => (float) ($li['total'] ?? 0),
                'tax_amount'   => (float) ($li['total_tax'] ?? 0),
            ]);
        }
    }
}
