<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\InventoryItem;
use App\Models\Tenant\InventoryMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        try {
            $items = InventoryItem::query()
                ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where(function ($qq) use ($request) {
                    $n = '%' . $request->string('q') . '%';
                    $qq->where('name', 'ilike', $n)->orWhere('sku', 'ilike', $n);
                }))
                ->orderBy('name')
                ->paginate(50)
                ->withQueryString();

            $totals = [
                'total_items'  => InventoryItem::count(),
                'low_stock'    => InventoryItem::whereColumn('quantity', '<=', 'min_stock_level')->count(),
                'total_value'  => InventoryItem::selectRaw('SUM(quantity * cost_price) as v')->value('v') ?? 0,
            ];
            $categories = InventoryItem::distinct()->whereNotNull('category')->pluck('category')->toArray();
        } catch (\Throwable $e) {
            $items = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);
            $totals = ['total_items' => 0, 'low_stock' => 0, 'total_value' => 0];
            $categories = [];
        }

        return Inertia::render('Tenant/Inventory/Index', [
            'items'      => $items,
            'totals'     => $totals,
            'categories' => $categories,
            'filters'    => $request->only(['category', 'status', 'q']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'sku'            => ['required', 'string', 'max:60', 'unique:inventory_items,sku'],
            'category'       => ['nullable', 'string', 'max:60'],
            'unit'           => ['nullable', 'string', 'max:20'],
            'quantity'       => ['required', 'integer', 'min:0'],
            'min_stock_level'=> ['nullable', 'integer', 'min:0'],
            'cost_price'     => ['required', 'numeric', 'min:0'],
            'selling_price'  => ['nullable', 'numeric', 'min:0'],
            'location'       => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string'],
        ]);

        $item = InventoryItem::create($validated);

        if ($validated['quantity'] > 0) {
            InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'type'              => 'in',
                'quantity'          => $validated['quantity'],
                'balance_after'     => $validated['quantity'],
                'reference_type'    => 'manual',
                'notes'             => 'Initial stock',
                'created_by'        => auth()->id(),
            ]);
        }

        return back()->with('success', 'Item added to inventory.');
    }

    public function show(Request $request, string $tenant, string $id): Response
    {
        $item = InventoryItem::findOrFail($id);
        $movements = InventoryMovement::where('inventory_item_id', $id)->latest()->limit(50)->get();

        return Inertia::render('Tenant/Inventory/Show', [
            'item'      => $item,
            'movements' => $movements,
        ]);
    }

    public function update(Request $request, string $tenant, string $id): RedirectResponse
    {
        $item = InventoryItem::findOrFail($id);
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'category'       => ['nullable', 'string', 'max:60'],
            'unit'           => ['nullable', 'string', 'max:20'],
            'min_stock_level'=> ['nullable', 'integer', 'min:0'],
            'cost_price'     => ['required', 'numeric', 'min:0'],
            'selling_price'  => ['nullable', 'numeric', 'min:0'],
            'location'       => ['nullable', 'string', 'max:100'],
            'status'         => ['nullable', 'in:active,discontinued'],
            'notes'          => ['nullable', 'string'],
        ]);
        $item->update($validated);
        return back()->with('success', 'Item updated.');
    }

    public function adjustStock(Request $request, string $tenant, string $id): RedirectResponse
    {
        $item = InventoryItem::findOrFail($id);
        $validated = $request->validate([
            'type'     => ['required', 'in:in,out,adjustment'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes'    => ['nullable', 'string'],
        ]);

        $qty = $validated['type'] === 'out' ? -$validated['quantity'] : $validated['quantity'];
        if ($validated['type'] === 'adjustment') {
            $qty = $validated['quantity'] - $item->quantity;
        }

        $newBalance = $item->quantity + $qty;
        if ($newBalance < 0) {
            return back()->with('error', 'Insufficient stock.');
        }

        $item->update(['quantity' => $newBalance]);

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'type'              => $validated['type'],
            'quantity'          => $qty,
            'balance_after'     => $newBalance,
            'reference_type'    => 'manual',
            'notes'             => $validated['notes'] ?? null,
            'created_by'        => auth()->id(),
        ]);

        return back()->with('success', 'Stock adjusted.');
    }
}
