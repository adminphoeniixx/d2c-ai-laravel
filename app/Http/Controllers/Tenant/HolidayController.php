<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) $request->input('year', now()->year);

        $holidays = Holiday::whereYear('date', $year)
            ->orderBy('date')
            ->get();

        return Inertia::render('Tenant/HR/Holidays/Index', [
            'holidays' => $holidays,
            'year'     => $year,
            'totals'   => [
                'total'    => $holidays->count(),
                'paid'     => $holidays->where('is_paid', true)->count(),
                'national' => $holidays->where('type', 'national')->count(),
                'company'  => $holidays->where('type', 'company')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date'    => ['required', 'date', 'unique:holidays,date'],
            'name'    => ['required', 'string', 'max:120'],
            'type'    => ['required', 'in:national,company,optional,restricted'],
            'is_paid' => ['nullable', 'boolean'],
            'notes'   => ['nullable', 'string', 'max:500'],
        ]);

        Holiday::create($validated);

        return back()->with('success', 'Holiday added.');
    }

    public function update(Request $request, string $tenant, Holiday $holiday): RedirectResponse
    {
        $validated = $request->validate([
            'date'    => ['required', 'date'],
            'name'    => ['required', 'string', 'max:120'],
            'type'    => ['required', 'in:national,company,optional,restricted'],
            'is_paid' => ['nullable', 'boolean'],
            'notes'   => ['nullable', 'string', 'max:500'],
        ]);

        $holiday->update($validated);

        return back()->with('success', 'Holiday updated.');
    }

    public function destroy(Request $request, string $tenant, Holiday $holiday): RedirectResponse
    {
        $holiday->delete();
        return back()->with('success', 'Holiday removed.');
    }
}
