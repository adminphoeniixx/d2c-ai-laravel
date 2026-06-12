<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ExpensesController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $query = Expense::query()->orderByDesc('occurred_at');
        if ($from && $to) $query->whereBetween('occurred_at', [$from, $to]);
        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('source')) $query->where('source', $request->source);

        // KPI base query (same date range, no category/source filter)
        $kpiBase = Expense::query();
        if ($from && $to) $kpiBase->whereBetween('occurred_at', [$from, $to]);

        $allExpenses = (clone $kpiBase)->get();
        $total = $allExpenses->sum('amount');
        $gstPaid = $allExpenses->sum(fn($e) => floatval($e->extracted_data['gst_amount'] ?? 0));

        // P&L: category-wise breakdown
        $pnl = [];
        $grouped = $allExpenses->groupBy('category');
        foreach ($grouped as $cat => $items) {
            $catTotal = $items->sum('amount');
            $pnl[] = [
                'category' => $cat,
                'amount'   => $catTotal,
                'count'    => $items->count(),
                'percent'  => $total > 0 ? round(($catTotal / $total) * 100, 1) : 0,
                'gst'      => $items->sum(fn($e) => floatval($e->extracted_data['gst_amount'] ?? 0)),
            ];
        }
        usort($pnl, fn($a, $b) => $b['amount'] <=> $a['amount']);

        $totals = [
            'total'      => $total,
            'gst_paid'   => $gstPaid,
            'net_amount' => $total - $gstPaid,
            'entries'    => $allExpenses->count(),
            'ads'        => $grouped->get('ads', collect())->sum('amount'),
            'inventory'  => $grouped->get('inventory', collect())->sum('amount'),
            'logistics'  => $grouped->get('logistics', collect())->sum('amount'),
            'platform_fee' => $grouped->get('platform_fee', collect())->sum('amount'),
            'payroll'    => $grouped->get('payroll', collect())->sum('amount'),
            'rent'       => $grouped->get('rent', collect())->sum('amount'),
            'tools'      => $grouped->get('tools', collect())->sum('amount'),
            'marketing'  => $grouped->get('marketing', collect())->sum('amount'),
        ];

        return Inertia::render('Tenant/Expenses/Index', [
            'expenses' => $query->paginate(50),
            'filters'  => $request->only(['category', 'source', 'date_preset', 'from', 'to']),
            'totals'   => $totals,
            'pnl'      => $pnl,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255', 'amount' => 'required|numeric|min:0.01',
            'category' => 'required|string', 'occurred_at' => 'required|date',
        ]);

        Expense::create(array_merge($data, [
            'currency' => $request->currency ?? 'INR',
            'source'   => $request->source ?? 'manual',
            'recorded_by_user_id' => $request->user()->id,
        ]));

        return back()->with('success', 'Expense recorded.');
    }

    public function destroy(Request $request, $tenant, $id)
    {
        Expense::findOrFail($id)->delete();
        return back()->with('success', 'Expense deleted.');
    }

    protected function resolveDateRange(Request $request): array
    {
        $preset = $request->input('date_preset', 'this_month');

        if ($preset === 'custom' && $request->filled('from') && $request->filled('to')) {
            return [Carbon::parse($request->from)->startOfDay(), Carbon::parse($request->to)->endOfDay()];
        }

        return match ($preset) {
            'this_month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'last_3m'    => [Carbon::now()->subMonths(3)->startOfMonth(), Carbon::now()->endOfMonth()],
            'all'        => [null, null],
            default      => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };
    }
}
