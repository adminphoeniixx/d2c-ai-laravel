<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PnLReportController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->date('from') ?? Carbon::now()->startOfMonth();
        $to   = $request->date('to')   ?? Carbon::now();

        $revenue     = (float) Order::whereBetween('placed_at', [$from, $to])->sum('total_amount');
        $refunds     = 0; // extend later
        $grossRevenue= $revenue - $refunds;

        $cogsRate    = 0.35; // simple assumption; real value from products.cost
        $cogs        = round($grossRevenue * $cogsRate, 2);

        $expensesByCat = Expense::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $totalExpenses = (float) $expensesByCat->sum();
        $netProfit = $grossRevenue - $cogs - $totalExpenses;

        return Inertia::render('Tenant/PnL', [
            'period'   => ['from' => $from, 'to' => $to],
            'revenue'  => $grossRevenue,
            'cogs'     => $cogs,
            'expenses' => $expensesByCat,
            'totalExpenses' => $totalExpenses,
            'netProfit'     => $netProfit,
            'margin'        => $grossRevenue > 0 ? round(($netProfit / $grossRevenue) * 100, 2) : 0,
        ]);
    }
}
