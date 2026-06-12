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
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->input('provider')))
                ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where(function ($qq) use ($request) {
                    $n = '%'.$request->string('q').'%';
                    $qq->where('order_number', 'ilike', $n)
                       ->orWhere('customer_email', 'ilike', $n)
                       ->orWhere('customer_name', 'ilike', $n);
                }));

            $orders = (clone $baseQuery)->latest('placed_at')
                ->paginate(50)
                ->withQueryString();

            // KPIs based on same date range (but not status/provider/search filters)
            $kpiQuery = Order::query()
                ->when($dateRange, fn ($q) => $q->whereBetween('placed_at', [$dateRange[0], $dateRange[1]]));

            $grossRevenue = (float) (clone $kpiQuery)->sum('total_amount');
            $refundedAmount = (float) (clone $kpiQuery)->where('status', 'refunded')->sum('total_amount');
            $cancelledAmount = (float) (clone $kpiQuery)->where('status', 'cancelled')->sum('total_amount');

            $totals = [
                'gross_orders'     => (clone $kpiQuery)->count(),
                'gross_revenue'    => $grossRevenue,
                'net_revenue'      => $grossRevenue - $refundedAmount - $cancelledAmount,
                'total_shipping'   => (float) (clone $kpiQuery)->sum('total_shipping'),
                'total_discount'   => (float) (clone $kpiQuery)->sum('total_discount'),
                'cancelled'        => (clone $kpiQuery)->where('status', 'cancelled')->count(),
                'on_hold'          => (clone $kpiQuery)->whereIn('status', ['on-hold', 'hold', 'on_hold'])->count(),
                'failed'           => (clone $kpiQuery)->where('status', 'failed')->count(),
                'refunded'         => (clone $kpiQuery)->where('status', 'refunded')->count(),
                'pending'          => (clone $kpiQuery)->where('status', 'pending')->count(),
                'fulfilled'        => (clone $kpiQuery)->whereIn('status', ['fulfilled', 'completed'])->count(),
                'paid'             => (clone $kpiQuery)->where('status', 'paid')->count(),
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

        foreach ($accounts as $account) {
            try {
                $account->update(['status' => \App\Models\IntegrationAccount::STATUS_CONNECTED]);

                if ($account->provider === 'shopify') {
                    \App\Jobs\Integrations\SyncShopifyOrders::dispatchSync($account->id, backfill: false);
                } elseif ($account->provider === 'woocommerce') {
                    \App\Jobs\Integrations\SyncWooOrders::dispatchSync($account->id, backfill: false);
                }
            } catch (\Throwable $e) {
                \Log::warning("Auto-sync failed for {$account->provider}", ['error' => $e->getMessage()]);
            }
        }

        $afterCount = Order::count();
        $newOrders = $afterCount - $beforeCount;

        return response()->json([
            'new_orders' => $newOrders,
            'total' => $afterCount,
            'message' => $newOrders > 0
                ? "{$newOrders} new orders fetched successfully"
                : 'No new orders found',
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
        // Custom date range takes priority
        if ($from && $to) {
            return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
        }
        if ($from) {
            return [Carbon::parse($from)->startOfDay(), Carbon::now()];
        }

        return match ($range) {
            'today'    => [Carbon::today(), Carbon::now()],
            'week'     => [Carbon::now()->startOfWeek(), Carbon::now()],
            'month'    => [Carbon::now()->startOfMonth(), Carbon::now()],
            '3months'  => [Carbon::now()->subMonths(3), Carbon::now()],
            '6months'  => [Carbon::now()->subMonths(6), Carbon::now()],
            'year'     => [Carbon::now()->startOfYear(), Carbon::now()],
            'lastyear' => [Carbon::now()->subYear()->startOfYear(), Carbon::now()->subYear()->endOfYear()],
            default    => null,
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
