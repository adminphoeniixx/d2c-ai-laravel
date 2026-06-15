<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AdSpendDaily;
use App\Models\Tenant\AdSpendManual;
use App\Models\Tenant\Expense;
use App\Models\Tenant\LogisticsShipment;
use App\Models\Tenant\Order;
use App\Models\Tenant\PayrollRun;
use App\Models\Tenant\PgInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PnLReportController extends Controller
{
    public function index(Request $request): Response
    {
        $range = $request->query('range', 'this_month');
        [$from, $to] = $this->resolveRange($request, $range);

        $summary = $this->computeForRange($from, $to);

        // Monthly breakdown across the selected range
        $monthly = [];
        $cursor  = $from->copy()->startOfMonth();
        $rangeEndMonth = $to->copy()->startOfMonth();

        while ($cursor->lte($rangeEndMonth)) {
            $monthFrom = $cursor->copy();
            if ($monthFrom->lt($from)) $monthFrom = $from->copy();

            $monthTo = $cursor->copy()->endOfMonth();
            if ($monthTo->gt($to)) $monthTo = $to->copy();

            $data = $this->computeForRange($monthFrom, $monthTo);
            $monthly[] = array_merge(['month' => $cursor->format('Y-m'), 'label' => $cursor->format('M Y')], $data);

            $cursor->addMonth();
        }

        return Inertia::render('Tenant/PnL', array_merge($summary, [
            'period' => [
                'from'  => $from->toDateString(),
                'to'    => $to->toDateString(),
                'range' => $range,
            ],
            'monthly' => $monthly,
        ]));
    }

    /**
     * Compute revenue, COGS, expense heads, and net profit for an
     * arbitrary date range. Used for both the overall summary and
     * each row of the monthly breakdown.
     */
    protected function computeForRange(Carbon $from, Carbon $to): array
    {
        $revenue      = (float) Order::whereBetween('placed_at', [$from, $to])->sum('total_amount');
        $refunds      = 0.0; // extend later if/when returns/refunds are tracked
        $grossRevenue = $revenue - $refunds;

        // NOTE: COGS is currently a flat assumption pending per-SKU cost data
        // being reliably populated across all tenants (inventory_items.cost_price).
        $cogsRate = 0.35;
        $cogs     = round($grossRevenue * $cogsRate, 2);

        // 1) Manually-logged expenses, grouped by category (Title Case) — covers
        //    Inventory and any other custom expense heads.
        $expensesByCat = Expense::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->get()
            ->reduce(function (array $carry, $row) {
                $label = Str::title(str_replace(['_', '-'], ' ', (string) $row->category));
                $carry[$label] = ($carry[$label] ?? 0) + (float) $row->total;
                return $carry;
            }, []);

        // 2) Payment gateway charges (processing fees + GST on those fees).
        //    Filtered by the invoice's actual billing period when known,
        //    falling back to upload date for older invoices without period dates.
        if (Schema::hasTable('pg_invoices')) {
            $pgTotal = (float) PgInvoice::query()
                ->where(function ($q) use ($from, $to) {
                    $q->where(function ($q2) use ($from, $to) {
                        $q2->whereNotNull('period_start')
                           ->whereNotNull('period_end')
                           ->where('period_start', '<=', $to)
                           ->where('period_end', '>=', $from);
                    })->orWhere(function ($q2) use ($from, $to) {
                        $q2->where(function ($q3) {
                            $q3->whereNull('period_start')->orWhereNull('period_end');
                        })->whereBetween('created_at', [$from, $to]);
                    });
                })
                ->selectRaw('COALESCE(SUM(total_charges),0) + COALESCE(SUM(gst_amount),0) as total')
                ->value('total');

            if ($pgTotal > 0) {
                $expensesByCat['Payment Gateway Charges'] = ($expensesByCat['Payment Gateway Charges'] ?? 0) + $pgTotal;
            }
        }

        // 3) Ad spend — synced campaigns + manually uploaded invoices (date-based already).
        $adSpend = 0.0;
        if (Schema::hasTable('ad_spend_daily')) {
            $adSpend += (float) AdSpendDaily::whereBetween('date', [$from, $to])->sum('spend');
        }
        if (Schema::hasTable('ad_spend_manual')) {
            $adSpend += (float) AdSpendManual::whereBetween('date', [$from, $to])->sum('spend');
        }
        if ($adSpend > 0) {
            $expensesByCat['Ad Spend'] = ($expensesByCat['Ad Spend'] ?? 0) + $adSpend;
        }

        // 4) Logistics / shipping costs — prefer pickup date (actual shipment
        //    activity) over the record's created_at (sync/upload date).
        if (Schema::hasTable('logistics_shipments')) {
            $logistics = (float) LogisticsShipment::query()
                ->whereRaw('COALESCE(pickup_date, created_at) BETWEEN ? AND ?', [$from, $to])
                ->sum('total_amount');

            if ($logistics > 0) {
                $expensesByCat['Logistics & Shipping'] = ($expensesByCat['Logistics & Shipping'] ?? 0) + $logistics;
            }
        }

        // 5) Payroll — payroll_runs.month is "YYYY-MM"; include any run whose
        //    month falls within the selected range.
        if (Schema::hasTable('payroll_runs')) {
            $months = [];
            $cursor = $from->copy()->startOfMonth();
            $end    = $to->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $months[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }

            $payroll = (float) PayrollRun::whereIn('month', $months)->sum('total_gross');
            if ($payroll > 0) {
                $expensesByCat['Payroll'] = ($expensesByCat['Payroll'] ?? 0) + $payroll;
            }
        }

        arsort($expensesByCat);

        $totalExpenses = array_sum($expensesByCat);
        $netProfit     = $grossRevenue - $cogs - $totalExpenses;

        return [
            'revenue'       => round($grossRevenue, 2),
            'cogs'          => $cogs,
            'expenses'      => $expensesByCat,
            'totalExpenses' => round($totalExpenses, 2),
            'netProfit'     => round($netProfit, 2),
            'margin'        => $grossRevenue > 0 ? round(($netProfit / $grossRevenue) * 100, 2) : 0,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveRange(Request $request, string $range): array
    {
        $now = Carbon::now();

        // Explicit from/to always wins (custom range picker)
        if ($request->filled('from') && $request->filled('to')) {
            return [
                $request->date('from')->startOfDay(),
                $request->date('to')->endOfDay(),
            ];
        }

        return match ($range) {
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'ytd' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfDay(),
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfDay(),
            ],
        };
    }
}
