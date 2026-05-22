<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AdCampaign;
use App\Models\Tenant\AdSpendDaily;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdAnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        $since = Carbon::now()->subDays($days);

        try {
            // Platform-level spend from ad_spend_daily (synced data)
            $metaSpend = (float) AdSpendDaily::where('platform', 'meta')->where('date', '>=', $since)->sum('spend');
            $googleSpend = (float) AdSpendDaily::where('platform', 'google')->where('date', '>=', $since)->sum('spend');
            $totalAdSpend = $metaSpend + $googleSpend;

            // Fallback: if no synced data, use manual expenses
            if ($totalAdSpend <= 0) {
                $totalAdSpend = (float) Expense::where('category', 'ads')->where('occurred_at', '>=', $since)->sum('amount');
            }

            $revenue = (float) Order::where('placed_at', '>=', $since)->sum('total_amount');

            // Per-platform metrics
            $metaMetrics = AdSpendDaily::where('platform', 'meta')->where('date', '>=', $since)
                ->selectRaw('SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions, SUM(conversion_value) as conversion_value')
                ->first();

            $googleMetrics = AdSpendDaily::where('platform', 'google')->where('date', '>=', $since)
                ->selectRaw('SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions, SUM(conversion_value) as conversion_value')
                ->first();

            // Daily spend chart data
            $dailySpend = AdSpendDaily::where('date', '>=', $since)
                ->selectRaw("date, platform, SUM(spend) as spend")
                ->groupBy('date', 'platform')
                ->orderBy('date')
                ->get()
                ->groupBy(fn ($row) => $row->date->format('Y-m-d'));

            // Campaign breakdown
            $campaigns = AdCampaign::withSum(['dailySpend as total_spend' => fn ($q) => $q->where('date', '>=', $since)], 'spend')
                ->withSum(['dailySpend as total_clicks' => fn ($q) => $q->where('date', '>=', $since)], 'clicks')
                ->withSum(['dailySpend as total_impressions' => fn ($q) => $q->where('date', '>=', $since)], 'impressions')
                ->withSum(['dailySpend as total_conversions' => fn ($q) => $q->where('date', '>=', $since)], 'conversions')
                ->orderByDesc('total_spend')
                ->limit(20)
                ->get();

        } catch (\Throwable $e) {
            // Tables may not exist yet
            $totalAdSpend = (float) Expense::where('category', 'ads')->where('occurred_at', '>=', $since)->sum('amount');
            $revenue = (float) Order::where('placed_at', '>=', $since)->sum('total_amount');
            $metaSpend = 0;
            $googleSpend = 0;
            $metaMetrics = null;
            $googleMetrics = null;
            $dailySpend = collect();
            $campaigns = collect();
        }

        return Inertia::render('Tenant/AdAnalytics', [
            'days' => $days,
            'kpis' => [
                ['label' => "Ad Spend ({$days}D)",  'value' => $totalAdSpend, 'format' => 'currency'],
                ['label' => "Revenue ({$days}D)",   'value' => $revenue,      'format' => 'currency'],
                ['label' => 'Blended ROAS',         'value' => $totalAdSpend > 0 ? round($revenue / $totalAdSpend, 2) : 0, 'format' => 'number'],
            ],
            'platforms' => [
                'meta' => [
                    'spend'       => $metaSpend,
                    'impressions' => (int) ($metaMetrics->impressions ?? 0),
                    'clicks'      => (int) ($metaMetrics->clicks ?? 0),
                    'conversions' => (int) ($metaMetrics->conversions ?? 0),
                    'conv_value'  => (float) ($metaMetrics->conversion_value ?? 0),
                    'ctr'         => ($metaMetrics && $metaMetrics->impressions > 0) ? round($metaMetrics->clicks / $metaMetrics->impressions * 100, 2) : 0,
                    'roas'        => ($metaSpend > 0 && $metaMetrics) ? round(($metaMetrics->conversion_value ?? 0) / $metaSpend, 2) : 0,
                ],
                'google' => [
                    'spend'       => $googleSpend,
                    'impressions' => (int) ($googleMetrics->impressions ?? 0),
                    'clicks'      => (int) ($googleMetrics->clicks ?? 0),
                    'conversions' => (int) ($googleMetrics->conversions ?? 0),
                    'conv_value'  => (float) ($googleMetrics->conversion_value ?? 0),
                    'ctr'         => ($googleMetrics && $googleMetrics->impressions > 0) ? round($googleMetrics->clicks / $googleMetrics->impressions * 100, 2) : 0,
                    'roas'        => ($googleSpend > 0 && $googleMetrics) ? round(($googleMetrics->conversion_value ?? 0) / $googleSpend, 2) : 0,
                ],
            ],
            'campaigns' => $campaigns,
            'dailySpend' => $dailySpend,
        ]);
    }
}
