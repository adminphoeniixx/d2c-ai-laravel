<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PackagingItem;
use App\Models\Tenant\PackagingOrder;
use App\Models\Tenant\PackagingOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PackagingController extends Controller
{
    /* ── Inventory ─────────────────────────────────── */

    public function inventoryIndex(Request $request): Response
    {
        $items = PackagingItem::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where(function ($qq) use ($request) {
                $n = '%' . $request->string('q') . '%';
                $qq->where('name', 'ilike', $n)->orWhere('sku', 'ilike', $n);
            }))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $totals = [
            'total_items' => PackagingItem::count(),
            'low_stock'   => PackagingItem::whereColumn('quantity', '<=', 'min_stock_level')->count(),
            'total_value' => (float) (PackagingItem::selectRaw('COALESCE(SUM(quantity * cost_price), 0) as v')->value('v') ?? 0),
        ];

        return Inertia::render('Tenant/Packaging/Inventory/Index', [
            'items'   => $items,
            'totals'  => $totals,
            'filters' => $request->only(['status', 'q']),
        ]);
    }

    public function inventoryStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:200'],
            'sku'             => ['nullable', 'string', 'max:60'],
            'unit'            => ['nullable', 'string', 'max:20'],
            'quantity'        => ['nullable', 'integer', 'min:0'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'cost_price'      => ['required', 'numeric', 'min:0'],
            'location'        => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string'],
        ]);

        PackagingItem::create($validated);

        return back()->with('success', 'Packaging item added.');
    }

    public function inventoryUpdate(Request $request, string $tenant, int $id): RedirectResponse
    {
        $item = PackagingItem::findOrFail($id);
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:200'],
            'sku'             => ['nullable', 'string', 'max:60'],
            'unit'            => ['nullable', 'string', 'max:20'],
            'quantity'        => ['nullable', 'integer', 'min:0'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'cost_price'      => ['required', 'numeric', 'min:0'],
            'location'        => ['nullable', 'string', 'max:100'],
            'status'          => ['nullable', 'in:active,discontinued'],
            'notes'           => ['nullable', 'string'],
        ]);

        $item->update($validated);

        return back()->with('success', 'Item updated.');
    }

    public function inventoryDestroy(string $tenant, int $id): RedirectResponse
    {
        PackagingItem::findOrFail($id)->delete();
        return back()->with('success', 'Item deleted.');
    }

    /* ── Purchase Orders ────────────────────────────── */

    public function ordersIndex(Request $request): Response
    {
        $orders = PackagingOrder::query()
            ->with('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $totals = [
            'total'       => PackagingOrder::count(),
            'pending'     => PackagingOrder::whereIn('status', ['draft', 'sent'])->count(),
            'total_value' => (float) (PackagingOrder::selectRaw('COALESCE(SUM(total_amount), 0) as v')->value('v') ?? 0),
        ];

        $packagingItems = PackagingItem::orderBy('name')->get(['id', 'name', 'sku', 'unit', 'cost_price']);

        return Inertia::render('Tenant/Packaging/Orders/Index', [
            'orders'        => $orders,
            'totals'        => $totals,
            'packagingItems'=> $packagingItems,
            'filters'       => $request->only(['status']),
        ]);
    }

    public function ordersCreate(): Response
    {
        $packagingItems = PackagingItem::orderBy('name')->get(['id', 'name', 'sku', 'unit', 'cost_price']);

        return Inertia::render('Tenant/Packaging/Orders/Create', [
            'packagingItems' => $packagingItems,
            'nextPoNumber'   => PackagingOrder::nextPoNumber(),
        ]);
    }

    public function ordersStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'order_date'    => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'notes'         => ['nullable', 'string'],
            'items'         => ['required', 'array', 'min:1'],
            'items.*.item_name'        => ['required', 'string', 'max:200'],
            'items.*.packaging_item_id'=> ['nullable', 'integer'],
            'items.*.unit'             => ['nullable', 'string', 'max:20'],
            'items.*.quantity'         => ['required', 'integer', 'min:1'],
            'items.*.unit_price'       => ['required', 'numeric', 'min:0'],
        ]);

        DB::connection('tenant')->transaction(function () use ($validated) {
            $subtotal = collect($validated['items'])->sum(fn ($i) => $i['quantity'] * $i['unit_price']);

            $order = PackagingOrder::create([
                'po_number'     => PackagingOrder::nextPoNumber(),
                'supplier_name' => $validated['supplier_name'] ?? null,
                'status'        => 'draft',
                'order_date'    => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'subtotal'      => $subtotal,
                'tax_amount'    => 0,
                'total_amount'  => $subtotal,
                'notes'         => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $total = $item['quantity'] * $item['unit_price'];
                PackagingOrderItem::create([
                    'packaging_order_id' => $order->id,
                    'packaging_item_id'  => $item['packaging_item_id'] ?? null,
                    'item_name'          => $item['item_name'],
                    'unit'               => $item['unit'] ?? 'pcs',
                    'quantity'           => $item['quantity'],
                    'unit_price'         => $item['unit_price'],
                    'total_price'        => $total,
                ]);
            }
        });

        return redirect()
            ->route('tenant.packaging.orders.index', ['tenant' => request()->route('tenant')])
            ->with('success', 'Packaging PO created.');
    }

    public function ordersUpdateStatus(Request $request, string $tenant, int $id): RedirectResponse
    {
        $order = PackagingOrder::findOrFail($id);
        $validated = $request->validate([
            'status' => ['required', 'in:draft,sent,received,cancelled'],
        ]);

        $order->update($validated);

        // When marked as received, increment packaging_items quantities
        if ($validated['status'] === 'received' && $order->status !== 'received') {
            foreach ($order->items as $item) {
                if ($item->packaging_item_id) {
                    PackagingItem::where('id', $item->packaging_item_id)
                        ->increment('quantity', $item->quantity);
                }
            }
        }

        return back()->with('success', 'Status updated.');
    }
}
