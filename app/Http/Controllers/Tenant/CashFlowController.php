<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Order;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CashFlowController extends Controller
{
    public function index(): Response
    {
        $labels = $in = $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
            $labels[] = $start->format('M');
            $in[]  = (float) Order::whereBetween('placed_at', [$start, $end])->sum('total_amount');
            $out[] = (float) Expense::whereBetween('occurred_at', [$start, $end])->sum('amount');
        }

        return Inertia::render('Tenant/CashFlow', [
            'labels'   => $labels,
            'inflows'  => $in,
            'outflows' => $out,
            'runway'   => 'Calculated at '.round(array_sum($in) / max(1, array_sum($out)), 1).'x coverage',
        ]);
    }
}
