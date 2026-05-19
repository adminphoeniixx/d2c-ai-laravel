<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Order;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdAnalyticsController extends Controller
{
    public function index(): Response
    {
        $last30 = Carbon::now()->subDays(30);

        $spend = (float) Expense::where('category', 'ads')->where('occurred_at', '>=', $last30)->sum('amount');
        $revenue = (float) Order::where('placed_at', '>=', $last30)->sum('total_amount');

        return Inertia::render('Tenant/AdAnalytics', [
            'kpis' => [
                ['label' => 'Ad Spend (30D)', 'value' => $spend,   'format' => 'currency'],
                ['label' => 'Revenue (30D)',  'value' => $revenue, 'format' => 'currency'],
                ['label' => 'Blended ROAS',   'value' => $spend > 0 ? round($revenue / $spend, 2) : 0, 'format' => 'number'],
                ['label' => 'CPM',            'value' => round($spend / max(1, 1000), 2), 'format' => 'currency'],
            ],
            'note' => 'Connect Meta Ads / Google Ads to pull platform-side metrics.',
        ]);
    }
}
