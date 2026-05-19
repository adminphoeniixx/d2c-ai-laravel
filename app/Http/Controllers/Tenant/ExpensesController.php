<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpensesController extends Controller
{
    public function index(Request $request): Response
    {
        $expenses = Expense::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->latest('occurred_at')->paginate(50)->withQueryString();

        return Inertia::render('Tenant/Expenses/Index', [
            'expenses' => $expenses,
            'filters'  => $request->only(['category']),
            'totals'   => [
                'this_month' => (float) Expense::whereMonth('occurred_at', now()->month)
                                               ->whereYear('occurred_at', now()->year)
                                               ->sum('amount'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category'     => ['required', Rule::in(['ads', 'payroll', 'inventory', 'shipping', 'tools', 'rent', 'other'])],
            'source'       => ['nullable', Rule::in(['manual', 'voice', 'auto'])],
            'label'        => ['required', 'string', 'max:255'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'currency'     => ['nullable', 'string', 'size:3'],
            'occurred_at'  => ['nullable', 'date'],
            'voice_transcript' => ['nullable', 'string', 'max:2000'],
        ]);

        Expense::create(array_merge($data, [
            'occurred_at'         => $data['occurred_at'] ?? now(),
            'currency'            => $data['currency'] ?? 'INR',
            'source'              => $data['source'] ?? 'manual',
            'recorded_by_user_id' => $request->user()->id,
        ]));

        return back()->with('success', 'Expense recorded.');
    }
}
