<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Tenant\AdSpendDaily;
use App\Models\Tenant\AdSpendManual;
use App\Models\Tenant\AiInsight;
use App\Models\Tenant\Expense;
use App\Models\Tenant\InventoryItem;
use App\Models\Tenant\LogisticsShipment;
use App\Models\Tenant\Order;
use App\Models\Tenant\PayrollRun;
use App\Models\Tenant\PgInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiInsightsGenerator
{
    protected DoAiService $ai;

    /** Pages the AI may suggest as the destination for an insight's action button. */
    protected const ACTION_PAGES = [
        'pnl', 'expenses', 'orders', 'inventory', 'ads', 'banking', 'logistics', 'payroll', 'ai',
    ];

    public function __construct()
    {
        $this->ai = new DoAiService();
    }

    /**
     * Generate a fresh set of insights for the current tenant and
     * return them as an array of associative arrays (not yet persisted).
     */
    public function generate(string $companyName): array
    {
        $metrics = $this->collectMetrics();

        $system = $this->systemPrompt($companyName);
        $user   = "Business metrics (JSON):\n" . json_encode($metrics, JSON_PARTIAL_OUTPUT_ON_ERROR);

        $raw = $this->ai->heavy($system, $user, temperature: 0.4);
        if (empty($raw)) {
            $raw = $this->ai->light($system, $user, temperature: 0.4);
        }

        $parsed = DoAiService::parseJson($raw);
        $items  = $parsed['insights'] ?? null;

        if (!is_array($items)) {
            return [];
        }

        $insights = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;

            $type = ($item['type'] ?? '') === 'opportunity' ? 'opportunity' : 'alert';

            $severity = $item['severity'] ?? 'medium';
            if (!in_array($severity, ['high', 'medium', 'low'], true)) $severity = 'medium';

            $title = trim((string) ($item['title'] ?? ''));
            $desc  = trim((string) ($item['description'] ?? ''));
            if ($title === '' || $desc === '') continue;

            $actionPage = $item['action_page'] ?? null;
            if (!in_array($actionPage, self::ACTION_PAGES, true)) $actionPage = null;

            $insights[] = [
                'type'         => $type,
                'severity'     => $severity,
                'title'        => mb_substr($title, 0, 150),
                'description'  => mb_substr($desc, 0, 1000),
                'action_label' => $item['action_label'] ? mb_substr(trim((string) $item['action_label']), 0, 60) : null,
                'action_page'  => $actionPage,
                'metric'       => null,
            ];
        }

        return $insights;
    }

    /**
     * Generate insights and persist them, replacing the previous set.
     * Returns the newly saved AiInsight models.
     */
    public function generateAndStore(string $companyName): \Illuminate\Support\Collection
    {
        $insights = $this->generate($companyName);

        AiInsight::query()->delete();

        $now = now();
        $rows = array_map(function ($i) use ($now) {
            $i['created_at'] = $now;
            $i['updated_at'] = $now;
            $i['metric'] = $i['metric'] !== null ? json_encode($i['metric']) : null;
            return $i;
        }, $insights);

        if (!empty($rows)) {
            AiInsight::insert($rows);
        }

        \App\Models\Tenant\AiInsightRun::create([
            'generated_at' => $now,
            'status'       => empty($insights) ? 'failed' : 'ok',
            'error'        => empty($insights) ? 'AI returned no usable insights' : null,
        ]);

        return AiInsight::orderByRaw("CASE severity WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE type WHEN 'alert' THEN 0 ELSE 1 END")
            ->get();
    }

    protected function systemPrompt(string $companyName): string
    {
        return <<<PROMPT
You are heyd2c's AI business analyst for the D2C company "{$companyName}".

You will be given a JSON object of business metrics (revenue, expenses, inventory, ads, logistics, etc.).
Generate a prioritized list of 5 to 8 actionable insights — a mix of ALERTS (problems worth fixing) and
OPPORTUNITIES (things worth capitalizing on) — based ONLY on the data given.

RULES:
- Every insight must be directly grounded in the numbers provided. Do not invent data.
- If a metric is zero, empty, missing, or simply not concerning, do NOT manufacture an insight about it.
- Use ₹ for currency (e.g. ₹1,23,456), and be specific with numbers/percentages.
- title: max 8 words, plain and specific (e.g. "Inventory running low on 3 SKUs").
- description: 1-2 sentences, specific numbers, and what to DO about it (the "how").
- action_label: max 4 words, an imperative button label (e.g. "Restock now", "Review pricing", "View P&L").
- action_page: the single most relevant page for the action, one of exactly:
  pnl, expenses, orders, inventory, ads, banking, logistics, payroll, ai
  (use "ai" only when the best next step is asking the AI Copilot a follow-up question).
- Order the array with the most important/urgent insight first.

Respond with ONLY a JSON object, no markdown, no explanation:
{"insights":[{"type":"alert|opportunity","severity":"high|medium|low","title":"...","description":"...","action_label":"...","action_page":"..."}]}
PROMPT;
    }

    /**
     * Collect a snapshot of business metrics for the current tenant,
     * covering this-month-to-date vs the same period last month where relevant.
     */
    protected function collectMetrics(): array
    {
        $now       = Carbon::now();
        $mtdStart  = $now->copy()->startOfMonth();
        $daysIn    = $now->day; // days elapsed this month, for like-for-like comparison

        $lastMonthStart = $mtdStart->copy()->subMonth();
        $lastMonthMtdEnd = $lastMonthStart->copy()->addDays($daysIn - 1)->endOfDay();
        $lastMonthFullEnd = $mtdStart->copy()->subDay()->endOfDay();

        $metrics = [
            'as_of' => $now->toDateString(),
            'days_elapsed_this_month' => $daysIn,
        ];

        // --- Revenue (month-to-date vs same period last month) ---
        $revenueMtd     = (float) Order::whereBetween('placed_at', [$mtdStart, $now])->sum('total_amount');
        $revenueLastMtd = (float) Order::whereBetween('placed_at', [$lastMonthStart, $lastMonthMtdEnd])->sum('total_amount');
        $revenueLastFull = (float) Order::whereBetween('placed_at', [$lastMonthStart, $lastMonthFullEnd])->sum('total_amount');

        $ordersMtd     = (int) Order::whereBetween('placed_at', [$mtdStart, $now])->count();
        $ordersLastMtd = (int) Order::whereBetween('placed_at', [$lastMonthStart, $lastMonthMtdEnd])->count();

        $metrics['revenue'] = [
            'this_month_mtd'   => round($revenueMtd, 2),
            'last_month_mtd'   => round($revenueLastMtd, 2),
            'last_month_full'  => round($revenueLastFull, 2),
            'pct_change_mtd'   => $this->pctChange($revenueMtd, $revenueLastMtd),
        ];
        $metrics['orders'] = [
            'this_month_mtd' => $ordersMtd,
            'last_month_mtd' => $ordersLastMtd,
        ];

        // --- Expenses by category (this month MTD vs last month MTD) ---
        $expCur  = $this->expensesByCategory($mtdStart, $now);
        $expPrev = $this->expensesByCategory($lastMonthStart, $lastMonthMtdEnd);
        $metrics['expenses_mtd'] = $expCur;
        $metrics['expenses_last_month_mtd'] = $expPrev;
        $metrics['total_expenses_mtd'] = round(array_sum($expCur), 2);

        // --- Net profit (rough, using 35% COGS assumption, consistent with P&L page) ---
        $cogsCur  = round($revenueMtd * 0.35, 2);
        $cogsPrev = round($revenueLastMtd * 0.35, 2);
        $netCur   = round($revenueMtd - $cogsCur - array_sum($expCur), 2);
        $netPrev  = round($revenueLastMtd - $cogsPrev - array_sum($expPrev), 2);
        $metrics['net_profit'] = [
            'this_month_mtd' => $netCur,
            'last_month_mtd' => $netPrev,
            'margin_pct_this_month_mtd' => $revenueMtd > 0 ? round(($netCur / $revenueMtd) * 100, 1) : 0,
            'margin_pct_last_month_mtd' => $revenueLastMtd > 0 ? round(($netPrev / $revenueLastMtd) * 100, 1) : 0,
        ];

        // --- Payment gateway charges as % of revenue ---
        if (Schema::hasTable('pg_invoices')) {
            $pgTotal = (float) (PgInvoice::query()
                ->selectRaw('COALESCE(SUM(total_charges),0) + COALESCE(SUM(gst_amount),0) as total')
                ->whereBetween('created_at', [$mtdStart, $now])
                ->value('total') ?? 0);

            if ($pgTotal > 0) {
                $metrics['payment_gateway'] = [
                    'charges_mtd' => round($pgTotal, 2),
                    'pct_of_revenue_mtd' => $revenueMtd > 0 ? round(($pgTotal / $revenueMtd) * 100, 2) : 0,
                ];
            }
        }

        // --- Payroll (this month vs last month, payroll_runs.month is "YYYY-MM") ---
        if (Schema::hasTable('payroll_runs')) {
            $payrollCur  = (float) PayrollRun::where('month', $now->format('Y-m'))->sum('total_gross');
            $payrollPrev = (float) PayrollRun::where('month', $lastMonthStart->format('Y-m'))->sum('total_gross');

            if ($payrollCur > 0 || $payrollPrev > 0) {
                $metrics['payroll'] = [
                    'this_month'         => round($payrollCur, 2),
                    'last_month'         => round($payrollPrev, 2),
                ];
            }
        }

        // --- Repeat customer rate (all-time, cheap aggregate) ---
        $repeat = DB::table('orders')
            ->selectRaw('customer_email, COUNT(*) as c')
            ->whereNotNull('customer_email')
            ->groupBy('customer_email')
            ->get();
        $totalCustomers = $repeat->count();
        $repeatCustomers = $repeat->where('c', '>', 1)->count();
        if ($totalCustomers > 0) {
            $metrics['repeat_customer_rate_pct'] = round(($repeatCustomers / $totalCustomers) * 100, 1);
        }

        // --- Top SKUs by profit (this month MTD) ---
        if (Schema::hasTable('order_items') && Schema::hasTable('inventory_items')) {
            $topSkus = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('inventory_items', 'inventory_items.sku', '=', 'order_items.sku')
                ->whereBetween('orders.placed_at', [$mtdStart, $now])
                ->selectRaw('order_items.sku, MAX(order_items.product_name) as product_name,
                    SUM(order_items.total_price - inventory_items.cost_price * order_items.quantity) as profit,
                    SUM(order_items.quantity) as units')
                ->groupBy('order_items.sku')
                ->orderByDesc('profit')
                ->limit(5)
                ->get();

            if ($topSkus->isNotEmpty()) {
                $metrics['top_skus_by_profit_mtd'] = $topSkus->map(fn ($r) => [
                    'sku'   => $r->sku,
                    'name'  => $r->product_name,
                    'profit'=> round((float) $r->profit, 2),
                    'units' => (int) $r->units,
                ])->values()->all();
            }

            // Lowest-margin SKUs that actually sold this month
            $lowMargin = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('inventory_items', 'inventory_items.sku', '=', 'order_items.sku')
                ->whereBetween('orders.placed_at', [$mtdStart, $now])
                ->where('inventory_items.selling_price', '>', 0)
                ->selectRaw('order_items.sku, MAX(order_items.product_name) as product_name,
                    MAX(inventory_items.selling_price) as selling_price,
                    MAX(inventory_items.cost_price) as cost_price,
                    SUM(order_items.quantity) as units')
                ->groupBy('order_items.sku')
                ->havingRaw('SUM(order_items.quantity) >= 1')
                ->get()
                ->map(fn ($r) => [
                    'sku' => $r->sku,
                    'name' => $r->product_name,
                    'margin_pct' => $r->selling_price > 0 ? round((($r->selling_price - $r->cost_price) / $r->selling_price) * 100, 1) : null,
                    'units' => (int) $r->units,
                ])
                ->filter(fn ($r) => $r['margin_pct'] !== null)
                ->sortBy('margin_pct')
                ->take(3)
                ->values();

            if ($lowMargin->isNotEmpty()) {
                $metrics['lowest_margin_skus_sold_mtd'] = $lowMargin->all();
            }
        }

        // --- Low stock items ---
        if (Schema::hasTable('inventory_items')) {
            $lowStock = InventoryItem::query()
                ->whereColumn('quantity', '<=', 'min_stock_level')
                ->where('status', 'active')
                ->orderBy('quantity')
                ->limit(8)
                ->get(['name', 'sku', 'quantity', 'min_stock_level']);

            if ($lowStock->isNotEmpty()) {
                $metrics['low_stock_items'] = $lowStock->map(fn ($i) => [
                    'sku' => $i->sku,
                    'name' => $i->name,
                    'quantity' => $i->quantity,
                    'min_stock_level' => $i->min_stock_level,
                ])->values()->all();
            }
        }

        // --- Ad spend & ROAS (this month MTD vs last month MTD) ---
        $adSpendMtd = 0.0;
        $adSpendLastMtd = 0.0;
        if (Schema::hasTable('ad_spend_daily')) {
            $adSpendMtd     += (float) AdSpendDaily::whereBetween('date', [$mtdStart, $now])->sum('spend');
            $adSpendLastMtd += (float) AdSpendDaily::whereBetween('date', [$lastMonthStart, $lastMonthMtdEnd])->sum('spend');
        }
        if (Schema::hasTable('ad_spend_manual')) {
            $adSpendMtd     += (float) AdSpendManual::whereBetween('date', [$mtdStart, $now])->sum('spend');
            $adSpendLastMtd += (float) AdSpendManual::whereBetween('date', [$lastMonthStart, $lastMonthMtdEnd])->sum('spend');
        }
        if ($adSpendMtd > 0 || $adSpendLastMtd > 0) {
            $metrics['ad_spend'] = [
                'this_month_mtd' => round($adSpendMtd, 2),
                'last_month_mtd' => round($adSpendLastMtd, 2),
                'roas_this_month_mtd' => $adSpendMtd > 0 ? round($revenueMtd / $adSpendMtd, 2) : null,
                'roas_last_month_mtd' => $adSpendLastMtd > 0 ? round($revenueLastMtd / $adSpendLastMtd, 2) : null,
            ];
        }

        // --- Logistics: RTO rate (last 30 days) ---
        if (Schema::hasTable('logistics_shipments')) {
            $since = $now->copy()->subDays(30);
            $total = LogisticsShipment::whereRaw('COALESCE(pickup_date, created_at) >= ?', [$since])->count();
            $rto   = LogisticsShipment::whereRaw('COALESCE(pickup_date, created_at) >= ?', [$since])
                ->whereIn('status', ['RTO', 'DTO'])
                ->count();

            if ($total > 0) {
                $metrics['logistics'] = [
                    'shipments_last_30d' => $total,
                    'rto_count_last_30d' => $rto,
                    'rto_rate_pct_last_30d' => round(($rto / $total) * 100, 1),
                ];
            }
        }

        // --- Top state by orders (all-time, cheap) ---
        if (Schema::hasTable('orders')) {
            $topState = DB::table('orders')
                ->selectRaw("COALESCE(shipping_address->>'province', shipping_address->>'state') as state, COUNT(*) as c")
                ->whereRaw("COALESCE(shipping_address->>'province', shipping_address->>'state') IS NOT NULL")
                ->groupBy('state')
                ->orderByDesc('c')
                ->first();

            if ($topState && $topState->state) {
                $metrics['top_state_by_orders'] = [
                    'state' => $topState->state,
                    'orders' => (int) $topState->c,
                ];
            }
        }

        return $metrics;
    }

    /**
     * @return array<string, float> category => total
     */
    protected function expensesByCategory(Carbon $from, Carbon $to): array
    {
        return Expense::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    protected function pctChange(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : null;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
