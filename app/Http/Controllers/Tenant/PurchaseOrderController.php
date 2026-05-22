<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        try {
            $orders = PurchaseOrder::with('vendor:id,name')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->latest('order_date')
                ->paginate(25)
                ->withQueryString();
            $totals = [
                'total' => PurchaseOrder::count(),
                'pending' => PurchaseOrder::whereIn('status', ['draft', 'sent'])->count(),
                'total_value' => PurchaseOrder::sum('total_amount'),
            ];
        } catch (\Throwable $e) {
            $orders = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
            $totals = ['total' => 0, 'pending' => 0, 'total_value' => 0];
        }

        return Inertia::render('Tenant/PurchaseOrders/Index', [
            'orders'  => $orders,
            'totals'  => $totals,
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(): Response
    {
        $vendors = Vendor::orderBy('name')->get(['id', 'name', 'gstin']);
        $nextPO = 'PO-' . str_pad((string) (PurchaseOrder::max('id') + 1), 3, '0', STR_PAD_LEFT);

        return Inertia::render('Tenant/PurchaseOrders/Create', [
            'vendors' => $vendors,
            'nextPO'  => $nextPO,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'po_number'     => ['required', 'string', 'max:30', 'unique:purchase_orders,po_number'],
            'vendor_id'     => ['required', 'exists:vendors,id'],
            'order_date'    => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'notes'         => ['nullable', 'string'],
            'items'         => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:200'],
            'items.*.sku'          => ['nullable', 'string', 'max:60'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'     => ['nullable', 'numeric', 'min:0', 'max:40'],
        ]);

        $subtotal = 0;
        $taxTotal = 0;

        foreach ($validated['items'] as &$item) {
            $lineTotal = $item['quantity'] * $item['unit_price'];
            $taxRate = $item['tax_rate'] ?? 0;
            $lineTax = round($lineTotal * $taxRate / 100, 2);
            $item['tax_amount'] = $lineTax;
            $item['total_price'] = round($lineTotal + $lineTax, 2);
            $subtotal += $lineTotal;
            $taxTotal += $lineTax;
        }

        $po = PurchaseOrder::create([
            'po_number'     => $validated['po_number'],
            'vendor_id'     => $validated['vendor_id'],
            'status'        => 'draft',
            'order_date'    => $validated['order_date'],
            'expected_date' => $validated['expected_date'] ?? null,
            'subtotal'      => $subtotal,
            'tax_amount'    => $taxTotal,
            'total_amount'  => $subtotal + $taxTotal,
            'notes'         => $validated['notes'] ?? null,
            'created_by'    => auth()->id(),
        ]);

        foreach ($validated['items'] as $item) {
            PurchaseOrderItem::create(array_merge($item, ['purchase_order_id' => $po->id]));
        }

        $slug = request()->route('tenant') ?? '';
        return redirect()->route('tenant.purchase-orders.show', ['tenant' => $slug, 'id' => $po->id])
            ->with('success', 'Purchase Order created.');
    }

    public function show(Request $request, string $tenant, string $id): Response
    {
        $po = PurchaseOrder::with(['vendor', 'items'])->findOrFail($id);
        return Inertia::render('Tenant/PurchaseOrders/Show', ['po' => $po]);
    }

    public function updateStatus(Request $request, string $tenant, string $id): RedirectResponse
    {
        $po = PurchaseOrder::findOrFail($id);
        $validated = $request->validate(['status' => ['required', 'in:draft,sent,partial,received,cancelled']]);
        $po->update($validated);
        if ($validated['status'] === 'received') {
            $po->update(['received_date' => now()]);
        }
        return back()->with('success', 'Status updated.');
    }
}
