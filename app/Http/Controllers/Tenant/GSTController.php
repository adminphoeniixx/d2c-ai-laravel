<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\PgInvoice;
use App\Services\GST\GSTCalculator;
use App\Services\GST\StateCodeMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class GSTController extends Controller
{
    /**
     * Recalculate GST on all existing orders using stored raw_payload.
     * Called after GSTIN is added/changed.
     */
    public function recalculate(): RedirectResponse
    {
        $company = app('current_company');

        if (empty($company->gstin) || empty($company->registered_state_code)) {
            return back()->with('error', 'Set your GSTIN in Settings first.');
        }

        $sellerStateCode = $company->registered_state_code;
        $businessCategory = $company->business_category ?? 'other';
        $defaultGstRate = $company->default_gst_rate;

        $orders = Order::whereNotNull('raw_payload')->get();

        $updated = 0;

        foreach ($orders as $order) {
            try {
                $rawPayload = is_string($order->raw_payload)
                    ? json_decode($order->raw_payload, true)
                    : $order->raw_payload;

                if (empty($rawPayload)) continue;

                // Determine buyer state
                $buyerStateCode = null;
                $shippingProvince = $rawPayload['shipping_address']['province_code'] ?? null;
                if ($shippingProvince) {
                    $buyerStateCode = StateCodeMap::shopifyProvinceToStateCode($shippingProvince);
                }
                if (!$buyerStateCode) {
                    $billingProvince = $rawPayload['billing_address']['province_code'] ?? null;
                    if ($billingProvince) {
                        $buyerStateCode = StateCodeMap::shopifyProvinceToStateCode($billingProvince);
                    }
                }

                // For WooCommerce orders, try state field
                if (!$buyerStateCode) {
                    $state = $rawPayload['shipping']['state'] ?? $rawPayload['billing']['state'] ?? null;
                    if ($state) {
                        $buyerStateCode = StateCodeMap::shopifyProvinceToStateCode($state);
                    }
                }

                // Build line items for calculation
                $lineItems = $rawPayload['line_items'] ?? [];
                if (empty($lineItems)) {
                    // Simple calculation from total amount
                    $gst = GSTCalculator::calculate(
                        taxableAmount: (float) $order->total_amount,
                        gstRate: $defaultGstRate ?? 18,
                        sellerStateCode: $sellerStateCode,
                        buyerStateCode: $buyerStateCode,
                        priceIncludesGst: true,
                    );

                    $order->update([
                        'taxable_amount'  => $gst['taxable_amount'],
                        'cgst_amount'     => $gst['cgst_amount'],
                        'sgst_amount'     => $gst['sgst_amount'],
                        'igst_amount'     => $gst['igst_amount'],
                        'gst_rate'        => $gst['gst_rate'],
                        'place_of_supply' => $gst['place_of_supply'],
                        'is_intra_state'  => $gst['is_intra_state'],
                        'buyer_state_code'=> $buyerStateCode,
                    ]);
                } else {
                    // Fix line item prices for Shopify format
                    foreach ($lineItems as $idx => $li) {
                        $lineItems[$idx]['price'] = (string) ($li['price_set']['presentment_money']['amount'] ?? $li['price'] ?? '0');
                    }
                    $rawPayload['line_items'] = $lineItems;

                    $gstData = GSTCalculator::calculateForShopifyOrder(
                        order: $rawPayload,
                        sellerStateCode: $sellerStateCode,
                        businessCategory: $businessCategory,
                        defaultGstRate: $defaultGstRate,
                    );

                    $order->update([
                        'taxable_amount'  => $gstData['taxable_amount'],
                        'cgst_amount'     => $gstData['cgst_amount'],
                        'sgst_amount'     => $gstData['sgst_amount'],
                        'igst_amount'     => $gstData['igst_amount'],
                        'gst_rate'        => $gstData['line_items'][0]['gst_rate'] ?? $defaultGstRate,
                        'place_of_supply' => $gstData['place_of_supply'],
                        'is_intra_state'  => $gstData['is_intra_state'],
                        'buyer_state_code'=> $gstData['buyer_state_code'],
                    ]);

                    // Update order items GST too — match by position
                    $orderItems = OrderItem::where('order_id', $order->id)->orderBy('id')->get();
                    foreach ($gstData['line_items'] as $idx => $gstItem) {
                        $orderItem = $orderItems[$idx] ?? null;
                        if ($orderItem) {
                            $orderItem->update([
                                'gst_rate'       => $gstItem['gst_rate'],
                                'taxable_amount' => $gstItem['taxable_amount'],
                                'cgst_amount'    => $gstItem['cgst_amount'],
                                'sgst_amount'    => $gstItem['sgst_amount'],
                                'igst_amount'    => $gstItem['igst_amount'],
                            ]);
                        }
                    }
                }

                $updated++;
            } catch (\Throwable $e) {
                \Log::warning("GST recalc failed for order {$order->id}", ['error' => $e->getMessage()]);
                continue;
            }
        }

        return back()->with('success', "GST recalculated for {$updated} orders.");
    }
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
            $reconciliation = $this->reconciliation($start, $end, $summary);
        } catch (\Throwable $e) {
            $summary = ['cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total_gst' => 0, 'taxable_amount' => 0, 'order_count' => 0, 'total_revenue' => 0];
            $monthlySummary = [];
            $stateWise = [];
            $orders = [];
            $reconciliation = $this->emptyReconciliation();
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
            'reconciliation' => $reconciliation,
            'filters'        => [
                'from' => $start->format('Y-m-d'),
                'to'   => $end->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * GST reconciliation: output tax (collected on sales) vs input tax
     * credit (GST paid on expenses & payment-gateway invoices) for the period.
     */
    protected function reconciliation(Carbon $start, Carbon $end, array $summary): array
    {
        $outputGst = $summary['total_gst'];

        // ITC from manually-logged / AI-extracted expenses (extracted_data.gst_amount)
        $expenseItc = (float) Expense::whereBetween('occurred_at', [$start, $end])
            ->get()
            ->sum(fn ($e) => (float) ($e->extracted_data['gst_amount'] ?? 0));

        // ITC from payment gateway invoices (total_charges + gst_amount = GST on PG fees)
        $pgItc = 0.0;
        $pgInvoiceCount = 0;
        if (Schema::hasTable('pg_invoices')) {
            $pgRow = PgInvoice::query()
                ->where(function ($q) use ($start, $end) {
                    $q->where(function ($q2) use ($start, $end) {
                        $q2->whereNotNull('period_start')
                           ->whereNotNull('period_end')
                           ->where('period_start', '<=', $end)
                           ->where('period_end', '>=', $start);
                    })->orWhere(function ($q2) use ($start, $end) {
                        $q2->where(function ($q3) {
                            $q3->whereNull('period_start')->orWhereNull('period_end');
                        })->whereBetween('created_at', [$start, $end]);
                    });
                })
                ->selectRaw('COALESCE(SUM(gst_amount),0) as gst, COUNT(*) as cnt')
                ->first();

            $pgItc = round((float) ($pgRow->gst ?? 0), 2);
            $pgInvoiceCount = (int) ($pgRow->cnt ?? 0);
        }

        $totalItc = round($expenseItc + $pgItc, 2);
        $net = round($outputGst - $totalItc, 2);

        return [
            'output_gst' => $outputGst,
            'itc' => [
                'from_expenses'    => round($expenseItc, 2),
                'from_pg_invoices' => $pgItc,
                'pg_invoice_count' => $pgInvoiceCount,
                'total'            => $totalItc,
            ],
            'net_gst_payable' => $net,
            'status' => $net > 0 ? 'payable' : ($net < 0 ? 'credit_carried_forward' : 'nil'),
        ];
    }

    protected function emptyReconciliation(): array
    {
        return [
            'output_gst' => 0,
            'itc' => ['from_expenses' => 0, 'from_pg_invoices' => 0, 'pg_invoice_count' => 0, 'total' => 0],
            'net_gst_payable' => 0,
            'status' => 'nil',
        ];
    }


    protected function periodSummary(Carbon $start, Carbon $end): array
    {
        $data = Order::whereBetween('placed_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
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
                ->where('status', '!=', 'cancelled')
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
            ->where('status', '!=', 'cancelled')
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
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('placed_at')
            ->limit(100)
            ->get(['id', 'order_number', 'customer_name', 'total_amount', 'taxable_amount',
                   'cgst_amount', 'sgst_amount', 'igst_amount', 'gst_rate',
                   'place_of_supply', 'is_intra_state', 'placed_at'])
            ->toArray();
    }
}
