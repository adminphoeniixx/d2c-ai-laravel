<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(): Response
    {
        try {
            $vendors = Vendor::withCount('purchaseOrders')->orderBy('name')->paginate(25);
        } catch (\Throwable $e) {
            $vendors = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
        }
        return Inertia::render('Tenant/Vendors/Index', ['vendors' => $vendors]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['nullable', 'email', 'max:180'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'gstin'    => ['nullable', 'string', 'max:15'],
            'address'  => ['nullable', 'string'],
            'city'     => ['nullable', 'string', 'max:60'],
            'state'    => ['nullable', 'string', 'max:60'],
            'pincode'  => ['nullable', 'string', 'max:10'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'notes'    => ['nullable', 'string'],
        ]);
        Vendor::create($validated);
        return back()->with('success', 'Vendor added.');
    }

    public function update(Request $request, string $tenant, string $id): RedirectResponse
    {
        $vendor = Vendor::findOrFail($id);
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['nullable', 'email', 'max:180'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'gstin'    => ['nullable', 'string', 'max:15'],
            'address'  => ['nullable', 'string'],
            'city'     => ['nullable', 'string', 'max:60'],
            'state'    => ['nullable', 'string', 'max:60'],
            'pincode'  => ['nullable', 'string', 'max:10'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'notes'    => ['nullable', 'string'],
        ]);
        $vendor->update($validated);
        return back()->with('success', 'Vendor updated.');
    }

    public function destroy(Request $request, string $tenant, string $id): RedirectResponse
    {
        Vendor::findOrFail($id)->delete();
        return back()->with('success', 'Vendor deleted.');
    }
}
