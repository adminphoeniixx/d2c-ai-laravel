<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    public function index(Request $request): Response
    {
        try {
            $orders = Order::query()
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->input('provider')))
                ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where(function ($qq) use ($request) {
                    $n = '%'.$request->string('q').'%';
                    $qq->where('order_number', 'ilike', $n)
                       ->orWhere('customer_email', 'ilike', $n)
                       ->orWhere('customer_name', 'ilike', $n);
                }))
                ->latest('placed_at')
                ->paginate(50)
                ->withQueryString();

            $totals = [
                'count'   => Order::count(),
                'revenue' => (float) Order::sum('total_amount'),
            ];
        } catch (\Throwable $e) {
            $orders = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);
            $totals = ['count' => 0, 'revenue' => 0];
        }

        return Inertia::render('Tenant/Orders/Index', [
            'orders'  => $orders,
            'filters' => $request->only(['status', 'provider', 'q']),
            'totals'  => $totals,
        ]);
    }

    public function show(Request $request, string $tenant, string $orderId): Response
    {
        $order = Order::with('items')->findOrFail($orderId);

        $company = app('current_company');

        return Inertia::render('Tenant/Orders/Show', [
            'order' => [
                'id'                 => $order->id,
                'order_number'       => $order->order_number,
                'external_id'        => $order->external_id,
                'provider'           => $order->provider,
                'status'             => $order->status,
                'financial_status'   => $order->financial_status,
                'fulfillment_status' => $order->fulfillment_status,
                'currency'           => $order->currency,
                'subtotal'           => (float) $order->subtotal,
                'total_tax'          => (float) $order->total_tax,
                'total_discount'     => (float) $order->total_discount,
                'total_shipping'     => (float) $order->total_shipping,
                'total_amount'       => (float) $order->total_amount,
                // GST
                'taxable_amount'     => (float) ($order->taxable_amount ?? 0),
                'cgst_amount'        => (float) ($order->cgst_amount ?? 0),
                'sgst_amount'        => (float) ($order->sgst_amount ?? 0),
                'igst_amount'        => (float) ($order->igst_amount ?? 0),
                'gst_rate'           => $order->gst_rate,
                'place_of_supply'    => $order->place_of_supply,
                'is_intra_state'     => $order->is_intra_state,
                'buyer_state_code'   => $order->buyer_state_code,
                // Customer
                'customer_name'      => $order->customer_name,
                'customer_email'     => $order->customer_email,
                'customer_phone'     => $order->customer_phone,
                'shipping_address'   => $order->shipping_address,
                'billing_address'    => $order->billing_address,
                'line_item_count'    => $order->line_item_count,
                'tags'               => $order->tags,
                'placed_at'          => $order->placed_at,
                // Line items with GST
                'items' => $order->items->map(fn ($item) => [
                    'id'              => $item->id,
                    'sku'             => $item->sku,
                    'product_name'    => $item->product_name,
                    'variant_name'    => $item->variant_name,
                    'quantity'        => $item->quantity,
                    'unit_price'      => (float) $item->unit_price,
                    'total_price'     => (float) $item->total_price,
                    'tax_amount'      => (float) ($item->tax_amount ?? 0),
                    'gst_rate'        => $item->gst_rate,
                    'taxable_amount'  => (float) ($item->taxable_amount ?? 0),
                    'cgst_amount'     => (float) ($item->cgst_amount ?? 0),
                    'sgst_amount'     => (float) ($item->sgst_amount ?? 0),
                    'igst_amount'     => (float) ($item->igst_amount ?? 0),
                ])->toArray(),
            ],
            'hasGst' => !empty($company->gstin),
        ]);
    }
}
