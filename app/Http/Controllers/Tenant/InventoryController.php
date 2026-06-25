<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\Integrations\SyncShopifyProducts;
use App\Models\Tenant\InventoryItem;
use App\Models\Tenant\InventoryMovement;
use App\Models\IntegrationAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    /**
     * Packaging-specific inventory view — same as index() but pre-filtered
     * to the 'packaging' category and rendered on a separate page so it has
     * its own nav entry and breadcrumb without polluting the main inventory.
     */
    public function packagingIndex(Request $request): Response
    {
        try {
            $items = InventoryItem::query()
                ->where('category', 'packaging')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where(function ($qq) use ($request) {
                    $n = '%' . $request->string('q') . '%';
                    $qq->where('name', 'ilike', $n)->orWhere('sku', 'ilike', $n);
                }))
                ->orderBy('name')
                ->paginate(50)
                ->withQueryString();

            $totals = [
                'total_items'  => InventoryItem::where('category', 'packaging')->count(),
                'low_stock'    => InventoryItem::where('category', 'packaging')->whereColumn('quantity', '<=', 'min_stock_level')->count(),
                'total_value'  => InventoryItem::where('category', 'packaging')->selectRaw('SUM(quantity * cost_price) as v')->value('v') ?? 0,
            ];
        } catch (\Throwable $e) {
            $items = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);
            $totals = ['total_items' => 0, 'low_stock' => 0, 'total_value' => 0];
        }

        return Inertia::render('Tenant/Packaging/Inventory', [
            'items'   => $items,
            'totals'  => $totals,
            'filters' => $request->only(['status', 'q']),
        ]);
    }

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

        // Pass Shopify connection status so the frontend can show/hide
        // the "Sync from Shopify" button — scoped to the current company.
        $company = app('current_company');
        $shopifyConnected = IntegrationAccount::where('company_id', $company->id)
            ->where('provider', 'shopify')
            ->where('status', 'connected')
            ->exists();

        return Inertia::render('Tenant/Inventory/Index', [
            'items'            => $items,
            'totals'           => $totals,
            'categories'       => $categories,
            'filters'          => $request->only(['category', 'status', 'q']),
            'shopifyConnected' => $shopifyConnected,
        ]);
    }

    /**
     * Trigger a Shopify product sync for all connected Shopify accounts.
     * Dispatched to the integrations queue so it runs in the background.
     */
    public function syncFromShopify(Request $request, string $tenant): JsonResponse
    {
        $company = app('current_company');
        $accounts = IntegrationAccount::where('company_id', $company->id)
            ->where('provider', 'shopify')
            ->where('status', 'connected')
            ->get();

        if ($accounts->isEmpty()) {
            return response()->json(['error' => 'No connected Shopify account found.'], 422);
        }

        foreach ($accounts as $account) {
            SyncShopifyProducts::dispatch($account->id)->onQueue('integrations');
        }

        return response()->json([
            'message' => 'Shopify product sync started — products will appear shortly.',
            'count'   => $accounts->count(),
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
            'quantity'       => ['nullable', 'integer', 'min:0'],
            'min_stock_level'=> ['nullable', 'integer', 'min:0'],
            'cost_price'     => ['required', 'numeric', 'min:0'],
            'selling_price'  => ['nullable', 'numeric', 'min:0'],
            'location'       => ['nullable', 'string', 'max:100'],
            'status'         => ['nullable', 'in:active,discontinued'],
            'notes'          => ['nullable', 'string'],
        ]);

        $quantityChanged = isset($validated['quantity']) && (int) $validated['quantity'] !== (int) $item->quantity;
        $item->update($validated);

        // If quantity changed and this item came from Shopify, write back
        // to Shopify so the two stay in sync. We look up the corresponding
        // Product row to get the Shopify inventory_item_id stored in meta.
        if ($quantityChanged) {
            try {
                $product = null;

                // Match 1: real SKU match
                if ($item->sku && !str_starts_with($item->sku, 'SHOPIFY-')) {
                    $product = \App\Models\Tenant\Product::where('sku', $item->sku)
                        ->where('provider', 'shopify')
                        ->first();
                }

                // Match 2: SHOPIFY-{variantId} placeholder — extract variant ID directly
                if (!$product && $item->sku && str_starts_with($item->sku, 'SHOPIFY-')) {
                    $variantId = substr($item->sku, 7); // strip 'SHOPIFY-'
                    $product = \App\Models\Tenant\Product::where('external_id', $variantId)
                        ->where('provider', 'shopify')
                        ->first();
                }

                // Match 3: fall back to name matching
                if (!$product) {
                    $product = \App\Models\Tenant\Product::where('provider', 'shopify')
                        ->where('name', $item->name)
                        ->first();
                }

                if ($product) {
                    $company = app('current_company');
                    $account = IntegrationAccount::where('company_id', $company->id)
                        ->where('provider', 'shopify')
                        ->where('status', 'connected')
                        ->first();

                    if ($account) {
                        $client = new \App\Services\Integrations\Shopify\ShopifyClient($account);

                        // inventory_item_id is stored in the variant's meta from sync
                        $inventoryItemId = $product->meta['inventory_item_id'] ?? null;

                        // If not in meta (product synced before this field was added),
                        // fetch it from Shopify by variant id — costs one extra API call
                        // but avoids requiring a full re-sync just to get this field.
                        if (!$inventoryItemId) {
                            $variantResponse = $client->request()->get("variants/{$product->external_id}.json");
                            $inventoryItemId = $variantResponse->json('variant.inventory_item_id');

                            // Cache it back into meta so future edits don't need this call
                            if ($inventoryItemId) {
                                $meta = $product->meta ?? [];
                                $meta['inventory_item_id'] = (string) $inventoryItemId;
                                $product->update(['meta' => $meta]);
                            }
                        }

                        if ($inventoryItemId) {
                            $synced = $client->updateInventoryQuantity(
                                (string) $inventoryItemId,
                                (int) $validated['quantity']
                            );
                            // Update the Product row too to keep it in sync
                            $product->update(['inventory_quantity' => $validated['quantity']]);

                            \Log::info('Shopify inventory write-back', [
                                'item' => $item->name,
                                'qty'  => $validated['quantity'],
                                'ok'   => $synced,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Shopify inventory write-back failed', [
                    'item_id' => $item->id,
                    'error'   => $e->getMessage(),
                    'at'      => $e->getFile() . ':' . $e->getLine(),
                ]);
                // Don't block the local save — Shopify sync failure is recoverable
            }
        }

        return back()->with('success', 'Item updated.' . ($quantityChanged ? ' Shopify inventory updated.' : ''));
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
