<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GSTController extends Controller
{
    public function index(): Response
    {
        $company = app('current_company');

        $from = request('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = request('to', now()->format('Y-m-d'));

        $start = Carbon::parse($from)->startOfDay();
        $end   = Carbon::parse($to)->endOfDay();

        try {
            $summary = $this->periodSummary($start, $end);
            $monthlySummary = $this->monthlySummary($start, $end);
            $stateWise = $this->stateWiseBreakdown($start, $end);
            $orders = $this->ordersWithGST($start, $end);
        } catch (\Throwable $e) {
            $summary = ['cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total_gst' => 0, 'taxable_amount' => 0, 'order_count' => 0, 'total_revenue' => 0];
            $monthlySummary = [];
            $stateWise = [];
            $orders = [];
        }

        return Inertia::render('Tenant/GSTSummary', [
            'company' => [
                'gstin'                 => $company->gstin,
                'registered_state_code' => $company->registered_state_code,
                'business_category'     => $company->business_category,
                'default_gst_rate'      => $company->default_gst_rate,
            ],
            'summary'        => $summary,
            'monthlySummary' => $monthlySummary,
            'stateWise'      => $stateWise,
            'orders'         => $orders,
            'filters'        => [
                'from' => $start->format('Y-m-d'),
                'to'   => $end->format('Y-m-d'),
            ],
        ]);
    }

    protected function periodSummary(Carbon $start, Carbon $end): array
    {
        $data = Order::whereBetween('placed_at', [$start, $end])
            ->selectRaw('
                COUNT(*) as order_count,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(SUM(taxable_amount), 0) as taxable_amount,
                COALESCE(SUM(cgst_amount), 0) as cgst,
                COALESCE(SUM(sgst_amount), 0) as sgst,
                COALESCE(SUM(igst_amount), 0) as igst,
                COALESCE(SUM(cgst_amount + sgst_amount + igst_amount), 0) as total_gst
            ')
            ->first();

        return [
            'order_count'    => (int) $data->order_count,
            'total_revenue'  => round((float) $data->total_revenue, 2),
            'taxable_amount' => round((float) $data->taxable_amount, 2),
            'cgst'           => round((float) $data->cgst, 2),
            'sgst'           => round((float) $data->sgst, 2),
            'igst'           => round((float) $data->igst, 2),
            'total_gst'      => round((float) $data->total_gst, 2),
        ];
    }

    protected function monthlySummary(Carbon $start, Carbon $end): array
    {
        $cursor = $start->copy()->startOfMonth();
        $endMonth = $end->copy()->endOfMonth();
        $months = [];

        while ($cursor->lte($endMonth)) {
            $mStart = $cursor->copy()->startOfMonth();
            $mEnd   = $cursor->copy()->endOfMonth();

            if ($mStart->lt($start)) $mStart = $start->copy();
            if ($mEnd->gt($end)) $mEnd = $end->copy()->endOfDay();

            $data = Order::whereBetween('placed_at', [$mStart, $mEnd])
                ->selectRaw('
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as total_revenue,
                    COALESCE(SUM(taxable_amount), 0) as taxable_amount,
                    COALESCE(SUM(cgst_amount), 0) as cgst,
                    COALESCE(SUM(sgst_amount), 0) as sgst,
                    COALESCE(SUM(igst_amount), 0) as igst,
                    COALESCE(SUM(cgst_amount + sgst_amount + igst_amount), 0) as total_gst
                ')
                ->first();

            $months[] = [
                'month'          => $cursor->format('M Y'),
                'month_short'    => $cursor->format('M'),
                'order_count'    => (int) $data->order_count,
                'total_revenue'  => round((float) $data->total_revenue, 2),
                'taxable_amount' => round((float) $data->taxable_amount, 2),
                'cgst'           => round((float) $data->cgst, 2),
                'sgst'           => round((float) $data->sgst, 2),
                'igst'           => round((float) $data->igst, 2),
                'total_gst'      => round((float) $data->total_gst, 2),
            ];

            $cursor->addMonth();
        }

        return $months;
    }

    protected function stateWiseBreakdown(Carbon $start, Carbon $end): array
    {
        return Order::whereNotNull('place_of_supply')
            ->whereBetween('placed_at', [$start, $end])
            ->groupBy('place_of_supply', 'is_intra_state')
            ->selectRaw('
                place_of_supply,
                is_intra_state,
                COUNT(*) as order_count,
                COALESCE(SUM(total_amount), 0) as revenue,
                COALESCE(SUM(cgst_amount), 0) as cgst,
                COALESCE(SUM(sgst_amount), 0) as sgst,
                COALESCE(SUM(igst_amount), 0) as igst
            ')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'state'          => $row->place_of_supply,
                'is_intra_state' => (bool) $row->is_intra_state,
                'type'           => $row->is_intra_state ? 'CGST+SGST' : 'IGST',
                'order_count'    => (int) $row->order_count,
                'revenue'        => round((float) $row->revenue, 2),
                'cgst'           => round((float) $row->cgst, 2),
                'sgst'           => round((float) $row->sgst, 2),
                'igst'           => round((float) $row->igst, 2),
            ])
            ->toArray();
    }

    protected function ordersWithGST(Carbon $start, Carbon $end): array
    {
        return Order::whereBetween('placed_at', [$start, $end])
            ->orderByDesc('placed_at')
            ->limit(100)
            ->get(['id', 'order_number', 'customer_name', 'total_amount', 'taxable_amount',
                   'cgst_amount', 'sgst_amount', 'igst_amount', 'gst_rate',
                   'place_of_supply', 'is_intra_state', 'placed_at'])
            ->toArray();
    }
}
