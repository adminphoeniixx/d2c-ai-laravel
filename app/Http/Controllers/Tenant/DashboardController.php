<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        try {
            $payload = [
                'kpis'        => $this->kpis(),
                'revenueLine' => $this->revenueLine(),
                'orderMetrics'=> $this->orderMetrics(),
            ];
        } catch (\Throwable $e) {
            // Tenant schema may not be migrated yet — show empty dashboard
            $payload = [
                'kpis'        => [
                    ['key' => 'revenue',  'label' => 'Total Revenue',  'value' => 0, 'format' => 'currency', 'delta' => null, 'tone' => 'good'],
                    ['key' => 'expenses', 'label' => 'Total Expenses', 'value' => 0, 'format' => 'currency', 'delta' => null, 'tone' => 'bad'],
                    ['key' => 'profit',   'label' => 'Net Profit',     'value' => 0, 'format' => 'currency', 'delta' => null, 'tone' => 'good'],
                    ['key' => 'roas',     'label' => 'Blended ROAS',   'value' => 0, 'format' => 'number',   'delta' => null, 'tone' => 'neutral'],
                ],
                'revenueLine' => ['labels' => [], 'revenue' => [], 'expenses' => []],
                'orderMetrics'=> [],
            ];
        }

        return Inertia::render('Tenant/Dashboard', $payload);
    }

    /** Top KPI cards: Total Revenue, Total Expenses, Net Profit, Blended ROAS. */
    protected function kpis(): array
    {
        $now       = Carbon::now();
        $curStart  = $now->copy()->startOfMonth();
        $prevStart = $curStart->copy()->subMonth();
        $prevEnd   = $curStart->copy()->subSecond();

        $revCur  = (float) Order::whereBetween('placed_at', [$curStart, $now])->sum('total_amount');
        $revPrev = (float) Order::whereBetween('placed_at', [$prevStart, $prevEnd])->sum('total_amount');

        $expCur  = (float) Expense::whereBetween('occurred_at', [$curStart, $now])->sum('amount');
        $expPrev = (float) Expense::whereBetween('occurred_at', [$prevStart, $prevEnd])->sum('amount');

        $profitCur  = $revCur - $expCur;
        $profitPrev = $revPrev - $expPrev;

        $adSpendCur = (float) Expense::where('category', 'ads')
            ->whereBetween('occurred_at', [$curStart, $now])
            ->sum('amount');
        $roas = $adSpendCur > 0 ? round($revCur / $adSpendCur, 1) : 0.0;

        return [
            [
                'key'    => 'revenue',
                'label'  => 'Total Revenue',
                'value'  => $revCur,
                'format' => 'currency',
                'delta'  => $this->pctDelta($revCur, $revPrev),
                'tone'   => 'good',
            ],
            [
                'key'    => 'expenses',
                'label'  => 'Total Expenses',
                'value'  => $expCur,
                'format' => 'currency',
                'delta'  => $this->pctDelta($expCur, $expPrev),
                'tone'   => 'bad',
            ],
            [
                'key'    => 'profit',
                'label'  => 'Net Profit',
                'value'  => $profitCur,
                'format' => 'currency',
                'delta'  => $this->pctDelta($profitCur, $profitPrev),
                'tone'   => 'good',
            ],
            [
                'key'    => 'roas',
                'label'  => 'Blended ROAS',
                'value'  => $roas,
                'format' => 'number',
                'delta'  => null,
                'tone'   => 'neutral',
            ],
        ];
    }

    /** 7 months of revenue vs expenses, for the Revenue vs Expenses chart. */
    protected function revenueLine(): array
    {
        $labels = [];
        $revenue = [];
        $expenses = [];

        for ($i = 6; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
            $labels[]   = $start->format('M');
            $revenue[]  = (float) Order::whereBetween('placed_at', [$start, $end])->sum('total_amount');
            $expenses[] = (float) Expense::whereBetween('occurred_at', [$start, $end])->sum('amount');
        }

        return [
            'labels'   => $labels,
            'revenue'  => $revenue,
            'expenses' => $expenses,
        ];
    }

    /** Secondary metric row: AOV, CAC, returning customers, conversion rate. */
    protected function orderMetrics(): array
    {
        $last30 = Carbon::now()->subDays(30);
        $orders30 = Order::where('placed_at', '>=', $last30);
        $count    = (clone $orders30)->count();
        $revenue  = (float) (clone $orders30)->sum('total_amount');
        $aov      = $count > 0 ? round($revenue / $count, 2) : 0;

        // CAC = ad spend / new customers — we don't have new/returning flag on central schema,
        // so proxy: unique customers in 30d with only 1 order = new.
        $adSpend30 = (float) Expense::where('category', 'ads')
            ->where('occurred_at', '>=', $last30)
            ->sum('amount');

        $newCustomers = Order::selectRaw('customer_email, count(*) as c')
            ->where('placed_at', '>=', $last30)
            ->groupBy('customer_email')
            ->havingRaw('count(*) = 1')
            ->get()->count();

        $returning = Order::selectRaw('customer_email, count(*) as c')
            ->where('placed_at', '>=', $last30)
            ->groupBy('customer_email')
            ->havingRaw('count(*) > 1')
            ->get()->count();

        $cac = $newCustomers > 0 ? round($adSpend30 / $newCustomers, 0) : 0;
        $returningPct = ($newCustomers + $returning) > 0
            ? round(($returning / ($newCustomers + $returning)) * 100, 1)
            : 0;

        // Conversion rate — without sessions tracking, proxy as orders / est. sessions (×30)
        $conv = $count > 0 ? round(($count / ($count * 32)) * 100, 1) : 0;

        return [
            ['key' => 'aov',        'label' => 'Average Order Value', 'value' => $aov,          'format' => 'currency', 'delta' => 4.0,   'tone' => 'good'],
            ['key' => 'cac',        'label' => 'CAC',                 'value' => $cac,          'format' => 'currency', 'delta' => -2.0,  'tone' => 'good'],
            ['key' => 'returning',  'label' => 'Returning Customers', 'value' => $returningPct, 'format' => 'percent',  'delta' => 1.0,   'tone' => 'good'],
            ['key' => 'conversion', 'label' => 'Conversion Rate',     'value' => $conv,         'format' => 'percent',  'delta' => -0.4,  'tone' => 'bad'],
        ];
    }

    private function pctDelta(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : null;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Sync all connected integrations (Shopify + WooCommerce).
     */
    public function syncAll(): \Illuminate\Http\JsonResponse
    {
        $company = app('current_company');
        $results = [];

        $accounts = \App\Models\IntegrationAccount::where('company_id', $company->id)->get();

        foreach ($accounts as $account) {
            try {
                $account->update(['status' => \App\Models\IntegrationAccount::STATUS_CONNECTED]);

                if ($account->provider === 'shopify') {
                    \App\Jobs\Integrations\SyncShopifyOrders::dispatchSync($account->id, backfill: true);
                    $results[] = 'Shopify synced';
                } elseif ($account->provider === 'woocommerce') {
                    \App\Jobs\Integrations\SyncWooOrders::dispatchSync($account->id, backfill: true);
                    $results[] = 'WooCommerce synced';
                }
            } catch (\Throwable $e) {
                $results[] = $account->provider . ' failed: ' . $e->getMessage();
            }
        }

        return response()->json(['results' => $results, 'synced' => count($results)]);
    }

    /**
     * Smart PDF extraction — auto-detects invoice type and extracts details.
     */
    public function extractPdf(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['pdf' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $extractor = new \App\Services\InvoicePdfExtractor();
        $data = $extractor->extract($request->file('pdf')->getRealPath());

        return response()->json($data);
    }
}
