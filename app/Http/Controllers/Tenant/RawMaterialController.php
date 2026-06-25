<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\RawMaterial;
use App\Models\Tenant\RawMaterialTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RawMaterialController extends Controller
{
    public function index(Request $request): Response
    {
        $items = RawMaterial::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('status'),   fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where(function ($qq) use ($request) {
                $n = '%' . $request->string('q') . '%';
                $qq->where('name', 'ilike', $n)->orWhere('sku', 'ilike', $n);
            }))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $totals = [
            'total_items' => RawMaterial::count(),
            'low_stock'   => RawMaterial::whereColumn('quantity', '<=', 'reorder_level')->where('reorder_level', '>', 0)->count(),
            'total_value' => (float) (RawMaterial::selectRaw('COALESCE(SUM(quantity * cost_per_unit), 0) as v')->value('v') ?? 0),
        ];

        $categories = RawMaterial::distinct()->whereNotNull('category')->orderBy('category')->pluck('category');

        return Inertia::render('Tenant/RawMaterials/Index', [
            'items'      => $items,
            'totals'     => $totals,
            'categories' => $categories,
            'filters'    => $request->only(['category', 'status', 'q']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:200'],
            'sku'           => ['nullable', 'string', 'max:60'],
            'category'      => ['nullable', 'string', 'max:80'],
            'unit'          => ['nullable', 'string', 'max:20'],
            'quantity'      => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'supplier'      => ['nullable', 'string', 'max:150'],
            'location'      => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string'],
        ]);

        $material = RawMaterial::create($validated);

        // If initial quantity > 0, create an opening stock transaction
        if (!empty($validated['quantity']) && $validated['quantity'] > 0) {
            RawMaterialTransaction::create([
                'raw_material_id'  => $material->id,
                'type'             => 'in',
                'quantity'         => $validated['quantity'],
                'cost_per_unit'    => $validated['cost_per_unit'] ?? 0,
                'total_cost'       => ($validated['quantity'] ?? 0) * ($validated['cost_per_unit'] ?? 0),
                'transaction_date' => now()->toDateString(),
                'reason'           => 'opening_stock',
                'notes'            => 'Initial stock entry',
            ]);
        }

        return back()->with('success', 'Raw material added.');
    }

    public function update(Request $request, string $tenant, int $id): RedirectResponse
    {
        $material  = RawMaterial::findOrFail($id);
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:200'],
            'sku'           => ['nullable', 'string', 'max:60'],
            'category'      => ['nullable', 'string', 'max:80'],
            'unit'          => ['nullable', 'string', 'max:20'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'supplier'      => ['nullable', 'string', 'max:150'],
            'location'      => ['nullable', 'string', 'max:100'],
            'status'        => ['nullable', 'in:active,discontinued'],
            'notes'         => ['nullable', 'string'],
        ]);

        $material->update($validated);
        return back()->with('success', 'Updated.');
    }

    public function destroy(string $tenant, int $id): RedirectResponse
    {
        RawMaterial::findOrFail($id)->delete();
        return back()->with('success', 'Deleted.');
    }

    /* ── Transactions ──────────────────────────────────────────── */

    public function transactions(Request $request, string $tenant, int $id): Response
    {
        $material = RawMaterial::findOrFail($id);

        $transactions = RawMaterialTransaction::where('raw_material_id', $id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Tenant/RawMaterials/Transactions', [
            'material'     => $material,
            'transactions' => $transactions,
        ]);
    }

    public function addTransaction(Request $request, string $tenant, int $id): RedirectResponse
    {
        $material  = RawMaterial::findOrFail($id);
        $validated = $request->validate([
            'type'             => ['required', 'in:in,out'],
            'quantity'         => ['required', 'numeric', 'min:0.001'],
            'cost_per_unit'    => ['nullable', 'numeric', 'min:0'],
            'transaction_date' => ['required', 'date'],
            'reference'        => ['nullable', 'string', 'max:100'],
            'reason'           => ['nullable', 'string', 'max:80'],
            'notes'            => ['nullable', 'string'],
        ]);

        $qty       = (float) $validated['quantity'];
        $costUnit  = (float) ($validated['cost_per_unit'] ?? $material->cost_per_unit);
        $totalCost = $qty * $costUnit;

        DB::connection('tenant')->transaction(function () use ($material, $validated, $qty, $costUnit, $totalCost) {
            RawMaterialTransaction::create([
                'raw_material_id'  => $material->id,
                'type'             => $validated['type'],
                'quantity'         => $qty,
                'cost_per_unit'    => $costUnit,
                'total_cost'       => $totalCost,
                'transaction_date' => $validated['transaction_date'],
                'reference'        => $validated['reference'] ?? null,
                'reason'           => $validated['reason'] ?? null,
                'notes'            => $validated['notes'] ?? null,
            ]);

            // Update stock quantity
            if ($validated['type'] === 'in') {
                $material->increment('quantity', $qty);
                // Update cost per unit (weighted average)
                $newQty  = $material->quantity;
                $newCost = $newQty > 0
                    ? (($material->getOriginal('quantity') * $material->cost_per_unit) + $totalCost) / $newQty
                    : $costUnit;
                $material->update(['cost_per_unit' => round($newCost, 2)]);
            } else {
                $material->decrement('quantity', $qty);
            }
        });

        return back()->with('success', ucfirst($validated['type']) . ' transaction recorded.');
    }
}
