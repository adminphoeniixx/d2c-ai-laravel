<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GSTExportController extends Controller
{
    public function gstr1(Request $request): StreamedResponse
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->format('Y-m-d')))->startOfDay();
        $to   = Carbon::parse($request->input('to', now()->format('Y-m-d')))->endOfDay();

        $orders = Order::whereBetween('placed_at', [$from, $to])
            ->with('items')
            ->orderBy('placed_at')
            ->get();

        $company = app('current_company');
        $filename = "GSTR1_{$company->slug}_{$from->format('d_M')}_{$to->format('d_M_Y')}.csv";

        return response()->streamDownload(function () use ($orders, $company) {
            $handle = fopen('php://output', 'w');

            // GSTR-1 B2C header
            fputcsv($handle, [
                'GSTIN of Supplier',
                'Invoice Number',
                'Invoice Date',
                'Invoice Value',
                'Place of Supply',
                'Supply Type',
                'Taxable Value',
                'CGST Rate',
                'CGST Amount',
                'SGST Rate',
                'SGST Amount',
                'IGST Rate',
                'IGST Amount',
                'Total GST',
            ]);

            foreach ($orders as $order) {
                $isIntra = $order->is_intra_state;
                $halfRate = $order->gst_rate ? round($order->gst_rate / 2, 2) : 0;

                fputcsv($handle, [
                    $company->gstin ?? '',
                    $order->order_number,
                    $order->placed_at?->format('d-m-Y') ?? '',
                    number_format((float) $order->total_amount, 2, '.', ''),
                    $order->place_of_supply ?? 'Unknown',
                    $isIntra ? 'Intra-State' : 'Inter-State',
                    number_format((float) $order->taxable_amount, 2, '.', ''),
                    $isIntra ? $halfRate : 0,
                    number_format((float) $order->cgst_amount, 2, '.', ''),
                    $isIntra ? $halfRate : 0,
                    number_format((float) $order->sgst_amount, 2, '.', ''),
                    $isIntra ? 0 : $order->gst_rate,
                    number_format((float) $order->igst_amount, 2, '.', ''),
                    number_format((float) ($order->cgst_amount + $order->sgst_amount + $order->igst_amount), 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function ordersExport(Request $request): StreamedResponse
    {
        $orders = Order::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('placed_at')
            ->get();

        $company = app('current_company');
        $filename = "Orders_{$company->slug}_" . now()->format('Y_m_d') . ".csv";

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Order Number', 'Date', 'Customer', 'Email', 'Phone',
                'Status', 'Financial Status', 'Fulfillment', 'Provider',
                'Subtotal', 'Shipping', 'Discount', 'Tax', 'Total',
                'Taxable Amount', 'CGST', 'SGST', 'IGST', 'GST Rate',
                'Place of Supply', 'Intra/Inter', 'Items', 'Currency',
            ]);

            foreach ($orders as $o) {
                fputcsv($handle, [
                    $o->order_number,
                    $o->placed_at?->format('d-m-Y H:i') ?? '',
                    $o->customer_name ?? '',
                    $o->customer_email ?? '',
                    $o->customer_phone ?? '',
                    $o->status,
                    $o->financial_status ?? '',
                    $o->fulfillment_status ?? '',
                    $o->provider,
                    number_format((float) $o->subtotal, 2, '.', ''),
                    number_format((float) $o->total_shipping, 2, '.', ''),
                    number_format((float) $o->total_discount, 2, '.', ''),
                    number_format((float) $o->total_tax, 2, '.', ''),
                    number_format((float) $o->total_amount, 2, '.', ''),
                    number_format((float) ($o->taxable_amount ?? 0), 2, '.', ''),
                    number_format((float) ($o->cgst_amount ?? 0), 2, '.', ''),
                    number_format((float) ($o->sgst_amount ?? 0), 2, '.', ''),
                    number_format((float) ($o->igst_amount ?? 0), 2, '.', ''),
                    $o->gst_rate ?? '',
                    $o->place_of_supply ?? '',
                    $o->is_intra_state ? 'Intra' : 'Inter',
                    $o->line_item_count,
                    $o->currency,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
