<?php
declare(strict_types=1);
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    public function index(Request $request): Response
    {
        try {
            // Date range filter
            $dateRange = $this->getDateRange(
                $request->input('range', 'all'),
                $request->input('from'),
                $request->input('to'),
            );

            $baseQuery = Order::query()
                ->when($dateRange, fn ($q) => $q->whereBetween('placed_at', [$dateRange[0], $dateRange[1]]))
                ->when($request->filled('status'), function ($q) use ($request) {
                    $status = $request->input('status');
                    if ($status === 'fulfilled') {
                        $q->where('fulfillment_status', 'fulfilled');
                    } elseif ($status === 'unfulfilled') {
                        $q->where(function ($qq) {
                            $qq->whereNull('fulfillment_status')
                               ->orWhere('fulfillment_status', 'partial')
                               ->orWhere('fulfillment_status', 'unfulfilled');
                        })->whereNotIn('status', ['cancelled', 'voided', 'void', 'refunded', 'failed']);
                    } else {
                        $q->where('status', $status);
                    }
                })
                ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->input('provider')))
                ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where(function ($qq) use ($request) {
                    $n = '%'.$request->string('q').'%';
                    $qq->where('order_number', 'ilike', $n)
                       ->orWhere('customer_email', 'ilike', $n)
                       ->orWhere('customer_name', 'ilike', $n);
                }));

            $orders = (clone $baseQuery)
                ->select('orders.*')
                ->selectRaw("
                    CASE
                        WHEN raw_payload->'fulfillments'->0->>'shipment_status' = 'delivered'         THEN 'Delivered'
                        WHEN raw_payload->'fulfillments'->0->>'shipment_status' = 'in_transit'        THEN 'In Transit'
                        WHEN raw_payload->'fulfillments'->0->>'shipment_status' = 'out_for_delivery'  THEN 'Out for Delivery'
                        WHEN raw_payload->'fulfillments'->0->>'shipment_status' = 'confirmed'         THEN 'Confirmed'
                        WHEN raw_payload->'fulfillments'->0->>'shipment_status' = 'failure'           THEN 'Failed'
                        WHEN raw_payload->'fulfillments'->0->>'shipment_status' = 'attempted_delivery' THEN 'Attempted'
                        ELSE NULL
                    END AS delivery_status
                ")
                ->latest('placed_at')
                ->paginate(50)
                ->withQueryString();

            // KPIs apply the same filters as the order list (date + status +
            // provider + search) so the numbers always match what's shown.
            $kpiQuery = $baseQuery;

            $grossRevenue   = (float) (clone $kpiQuery)->sum('total_amount');
            $refundedAmount = (float) (clone $kpiQuery)->where('status', 'refunded')->sum('total_amount');
            $cancelledAmount= (float) (clone $kpiQuery)->where('status', 'cancelled')->sum('total_amount');

            // Fulfillment counts are based on fulfillment_status column,
            // not the payment status column. Unfulfilled = null or 'partial'.
            $fulfilledCount   = (clone $kpiQuery)->where('fulfillment_status', 'fulfilled')->count();
            // Unfulfilled = orders where fulfillment hasn't happened yet,
            // but only for active/paid orders. Cancelled and voided orders
            // are done — they shouldn't show up as "waiting to be fulfilled".
            $activeStatuses = ['paid', 'pending', 'on-hold', 'on_hold', 'hold', 'processing'];
            $unfulfilledCount = (clone $kpiQuery)->where(function ($q) {
                $q->whereNull('fulfillment_status')
                  ->orWhere('fulfillment_status', 'partial')
                  ->orWhere('fulfillment_status', 'unfulfilled');
            })->whereNotIn('status', ['cancelled', 'voided', 'void', 'refunded', 'failed'])->count();

            $totals = [
                'gross_orders'   => (clone $kpiQuery)->count(),
                'gross_revenue'  => $grossRevenue,
                'net_revenue'    => $grossRevenue - $refundedAmount - $cancelledAmount,
                'total_shipping' => (float) (clone $kpiQuery)->sum('total_shipping'),
                'total_discount' => (float) (clone $kpiQuery)->sum('total_discount'),
                'fulfilled'      => $fulfilledCount,
                'unfulfilled'    => $unfulfilledCount,
                // Keep these for backward compat with any other components
                'cancelled'      => (clone $kpiQuery)->where('status', 'cancelled')->count(),
                'pending'        => (clone $kpiQuery)->where('status', 'pending')->count(),
                'paid'           => (clone $kpiQuery)->where('status', 'paid')->count(),
                'refunded'       => (clone $kpiQuery)->where('status', 'refunded')->count(),
                'failed'         => (clone $kpiQuery)->where('status', 'failed')->count(),
                'on_hold'        => (clone $kpiQuery)->whereIn('status', ['on-hold', 'hold', 'on_hold'])->count(),
            ];
        } catch (\Throwable $e) {
            $orders = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);
            $totals = [
                'gross_orders' => 0, 'gross_revenue' => 0, 'net_revenue' => 0,
                'total_shipping' => 0, 'total_discount' => 0, 'cancelled' => 0, 'on_hold' => 0,
                'failed' => 0, 'refunded' => 0, 'pending' => 0, 'fulfilled' => 0, 'paid' => 0,
            ];
        }

        return Inertia::render('Tenant/Orders/Index', [
            'orders'  => $orders,
            'filters' => $request->only(['status', 'provider', 'q', 'range', 'from', 'to']),
            'totals'  => $totals,
        ]);
    }

    /**
     * Auto-sync on page load.
     */
    public function autoSync(): JsonResponse
    {
        $company = app('current_company');
        $accounts = \App\Models\IntegrationAccount::where('company_id', $company->id)->get();

        if ($accounts->isEmpty()) {
            return response()->json(['new_orders' => 0, 'total' => Order::count(), 'message' => 'No integrations connected']);
        }

        $beforeCount = Order::count();
        $errors = [];

        foreach ($accounts as $account) {
            try {
                $account->update(['status' => \App\Models\IntegrationAccount::STATUS_CONNECTED]);

                // If this store has never successfully synced, do a full
                // backfill instead of an incremental "since last sync" sync —
                // otherwise orders placed before the connect timestamp are
                // silently skipped forever.
                $needsBackfill = $account->last_synced_at === null;

                if ($account->provider === 'shopify') {
                    \App\Jobs\Integrations\SyncShopifyOrders::dispatchSync($account->id, backfill: $needsBackfill);
                } elseif ($account->provider === 'woocommerce') {
                    \App\Jobs\Integrations\SyncWooOrders::dispatchSync($account->id, backfill: $needsBackfill);
                }

                $account->refresh();
                if ($account->status === \App\Models\IntegrationAccount::STATUS_ERROR) {
                    $errors[] = "{$account->provider}: " . ($account->error_message ?? 'sync failed');
                }
            } catch (\Throwable $e) {
                $errors[] = "{$account->provider}: " . $e->getMessage();
            }
        }

        $afterCount = Order::count();
        $newOrders = $afterCount - $beforeCount;

        if (!empty($errors)) {
            $message = 'Sync error — ' . implode('; ', $errors);
        } elseif ($newOrders > 0) {
            $message = "{$newOrders} new orders fetched successfully";
        } else {
            $message = 'No new orders found';
        }

        return response()->json([
            'new_orders' => $newOrders,
            'total' => $afterCount,
            'message' => $message,
        ]);
    }

    /**
     * Delete an order manually.
     */
    public function destroy(Request $request, string $tenant, string $orderId): RedirectResponse
    {
        $order = Order::findOrFail($orderId);
        $order->items()->delete();
        $order->delete();

        return back()->with('success', "Order {$order->order_number} deleted.");
    }

    /**
     * Bulk delete orders.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['string']]);

        $count = 0;
        foreach ($request->ids as $id) {
            $order = Order::find($id);
            if ($order) {
                $order->items()->delete();
                $order->delete();
                $count++;
            }
        }

        return back()->with('success', "{$count} orders deleted.");
    }

    public function show(Request $request, string $tenant, string $orderId): Response
    {
        $order = Order::with('items')->findOrFail($orderId);
        $company = app('current_company');

        return Inertia::render('Tenant/Orders/Show', [
            'order' => [
                'id' => $order->id, 'order_number' => $order->order_number,
                'external_id' => $order->external_id, 'provider' => $order->provider,
                'status' => $order->status, 'financial_status' => $order->financial_status,
                'fulfillment_status' => $order->fulfillment_status, 'currency' => $order->currency,
                'subtotal' => (float) $order->subtotal, 'total_tax' => (float) $order->total_tax,
                'total_discount' => (float) $order->total_discount, 'total_shipping' => (float) $order->total_shipping,
                'total_amount' => (float) $order->total_amount,
                'taxable_amount' => (float) ($order->taxable_amount ?? 0),
                'cgst_amount' => (float) ($order->cgst_amount ?? 0),
                'sgst_amount' => (float) ($order->sgst_amount ?? 0),
                'igst_amount' => (float) ($order->igst_amount ?? 0),
                'gst_rate' => $order->gst_rate, 'place_of_supply' => $order->place_of_supply,
                'is_intra_state' => $order->is_intra_state, 'buyer_state_code' => $order->buyer_state_code,
                'customer_name' => $order->customer_name, 'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'shipping_address' => self::normalizeAddress($order->shipping_address),
                'billing_address'  => self::normalizeAddress($order->billing_address),
                'line_item_count' => $order->line_item_count, 'tags' => $order->tags,
                'placed_at' => $order->placed_at,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id, 'sku' => $item->sku, 'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name, 'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price, 'total_price' => (float) $item->total_price,
                    'tax_amount' => (float) ($item->tax_amount ?? 0), 'gst_rate' => $item->gst_rate,
                    'taxable_amount' => (float) ($item->taxable_amount ?? 0),
                    'cgst_amount' => (float) ($item->cgst_amount ?? 0),
                    'sgst_amount' => (float) ($item->sgst_amount ?? 0),
                    'igst_amount' => (float) ($item->igst_amount ?? 0),
                ])->toArray(),
            ],
            'hasGst' => !empty($company->gstin),
        ]);
    }

    protected function getDateRange(?string $range, ?string $from = null, ?string $to = null): ?array
    {
        // All presets are computed in IST ("Asia/Kolkata") regardless of the
        // app's internal/storage timezone (UTC). The app serves Indian D2C
        // merchants, so "Today" must mean the IST calendar day the merchant
        // is actually living in — not the UTC calendar day the server is on.
        // Using Carbon::today()/now() directly resolves in app.timezone
        // (UTC), which silently shifts the day boundary by 5.5 hours and
        // causes orders placed late at night IST to fall outside "Today"
        // (or orders from the start of the IST day to be excluded until
        // UTC catches up). Carbon's date/time columns are stored in UTC by
        // Eloquent casting, but comparisons below still work correctly
        // against whereBetween() since Carbon instances carry their own
        // offset and get normalized to UTC for the SQL comparison.
        $nowIst = Carbon::now('Asia/Kolkata');

        // Custom date range takes priority. Inputs from the date picker are
        // plain calendar dates with no timezone info — interpret them as
        // IST dates too, for the same reason.
        if ($from && $to) {
            return [
                Carbon::parse($from, 'Asia/Kolkata')->startOfDay(),
                Carbon::parse($to, 'Asia/Kolkata')->endOfDay(),
            ];
        }
        if ($from) {
            return [Carbon::parse($from, 'Asia/Kolkata')->startOfDay(), $nowIst->copy()];
        }

        return match ($range) {
            'today'     => [$nowIst->copy()->startOfDay(), $nowIst->copy()],
            'yesterday' => [$nowIst->copy()->subDay()->startOfDay(), $nowIst->copy()->subDay()->endOfDay()],
            'week'      => [$nowIst->copy()->startOfWeek(), $nowIst->copy()],
            'month'     => [$nowIst->copy()->startOfMonth(), $nowIst->copy()],
            '3months'   => [$nowIst->copy()->subMonths(3), $nowIst->copy()],
            '6months'   => [$nowIst->copy()->subMonths(6), $nowIst->copy()],
            'year'      => [$nowIst->copy()->startOfYear(), $nowIst->copy()],
            'lastyear'  => [$nowIst->copy()->subYear()->startOfYear(), $nowIst->copy()->subYear()->endOfYear()],
            default     => null,
        };
    }

    protected static function normalizeAddress(?array $addr): ?array
    {
        if (empty($addr)) return null;
        if (isset($addr['address1']) || isset($addr['name'])) return $addr;
        return [
            'name' => trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')),
            'company' => $addr['company'] ?? null,
            'address1' => $addr['address_1'] ?? $addr['address1'] ?? null,
            'address2' => $addr['address_2'] ?? $addr['address2'] ?? null,
            'city' => $addr['city'] ?? null,
            'province' => $addr['state'] ?? $addr['province'] ?? null,
            'zip' => $addr['postcode'] ?? $addr['zip'] ?? null,
            'country' => $addr['country'] ?? null,
            'phone' => $addr['phone'] ?? null,
            'email' => $addr['email'] ?? null,
        ];
    }
}
