<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use Illuminate\Http\Request;

class DelhiveryOrderAnalyticsController extends Controller
{
    private const STATUS_MAP = [
        'DELIVERED'           => 'Delivered',
        'SHIPPED'             => 'In Transit',
        'READY_TO_SHIP'       => 'Ready to Ship',
        'OUT_FOR_DELIVERY'    => 'Out For Delivery',
        'RETURNING_TO_ORIGIN' => 'RTO Initiated',
        'RETURNED_TO_ORIGIN'  => 'RTO',
        'RETURN_TO_ORIGIN'    => 'RTO',
        'CANCELLED'           => 'Cancelled',
        'LOST'                => 'Lost',
        'NDR'                 => 'NDR',
        'UNDELIVERED'         => 'NDR',
    ];

    public function analytics(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        $base = Order::query();
        if ($from) $base->where('placed_at', '>=', $from);
        if ($to)   $base->where('placed_at', '<=', $to . ' 23:59:59');

        $orders = $base->get([
            'id', 'status', 'total_amount', 'placed_at',
            'customer_name', 'shipping_address', 'raw_payload',
        ]);

        $statusCounts  = [];
        $codTotal      = 0;
        $prepaidTotal  = 0;
        $codCount      = 0;
        $prepaidCount  = 0;
        $rtoCount      = 0;
        $deliveredCount= 0;
        $inTransitCount= 0;
        $ofdCount      = 0;
        $pendingCount  = 0;
        $pincodeCounts = [];
        $stateCounts   = [];
        $monthlyCounts = [];
        $codAtRisk     = 0;

        foreach ($orders as $order) {
            $raw = is_array($order->raw_payload)
                ? $order->raw_payload
                : json_decode($order->raw_payload, true);

            $note        = strtoupper(trim($raw['customer_note'] ?? ''));
            $payMethod   = $raw['payment_method'] ?? '';
            $isCod       = $payMethod === 'cod' || str_contains(strtolower($payMethod), 'cash');
            $amount      = (float) ($order->total_amount ?? 0);
            $shipPostcode= $raw['shipping']['postcode'] ?? $raw['billing']['postcode'] ?? null;
            $shipState   = $raw['shipping']['state'] ?? $raw['billing']['state'] ?? null;
            $month       = $order->placed_at ? \Carbon\Carbon::parse($order->placed_at)->format('Y-m') : null;

            $deliveryStatus = $this->normalizeNote($note, $order->status);
            $statusCounts[$deliveryStatus] = ($statusCounts[$deliveryStatus] ?? 0) + 1;

            if ($deliveryStatus === 'Delivered')                         $deliveredCount++;
            elseif (in_array($deliveryStatus, ['RTO', 'RTO Initiated'])) $rtoCount++;
            elseif ($deliveryStatus === 'In Transit')                    $inTransitCount++;
            elseif ($deliveryStatus === 'Out For Delivery')              $ofdCount++;
            else                                                          $pendingCount++;

            if ($isCod) {
                $codCount++;
                $codTotal += $amount;
                if (!in_array($deliveryStatus, ['Delivered', 'RTO', 'RTO Initiated'])) {
                    $codAtRisk += $amount;
                }
            } else {
                $prepaidCount++;
                $prepaidTotal += $amount;
            }

            if ($shipPostcode) {
                if (!isset($pincodeCounts[$shipPostcode]))
                    $pincodeCounts[$shipPostcode] = ['count' => 0, 'delivered' => 0, 'rto' => 0];
                $pincodeCounts[$shipPostcode]['count']++;
                if ($deliveryStatus === 'Delivered') $pincodeCounts[$shipPostcode]['delivered']++;
                if (in_array($deliveryStatus, ['RTO','RTO Initiated'])) $pincodeCounts[$shipPostcode]['rto']++;
            }

            if ($shipState) {
                if (!isset($stateCounts[$shipState]))
                    $stateCounts[$shipState] = ['count' => 0, 'delivered' => 0, 'rto' => 0, 'revenue' => 0];
                $stateCounts[$shipState]['count']++;
                $stateCounts[$shipState]['revenue'] += $amount;
                if ($deliveryStatus === 'Delivered') $stateCounts[$shipState]['delivered']++;
                if (in_array($deliveryStatus, ['RTO','RTO Initiated'])) $stateCounts[$shipState]['rto']++;
            }

            if ($month) {
                if (!isset($monthlyCounts[$month]))
                    $monthlyCounts[$month] = ['total' => 0, 'delivered' => 0, 'rto' => 0, 'cod' => 0];
                $monthlyCounts[$month]['total']++;
                if ($deliveryStatus === 'Delivered') $monthlyCounts[$month]['delivered']++;
                if (in_array($deliveryStatus, ['RTO','RTO Initiated'])) $monthlyCounts[$month]['rto']++;
                if ($isCod) $monthlyCounts[$month]['cod']++;
            }
        }

        $total = $orders->count();

        arsort($pincodeCounts);
        $topPincodes = array_values(array_slice(array_map(
            fn($pin, $d) => [
                'pincode'      => $pin,
                'count'        => $d['count'],
                'delivered'    => $d['delivered'],
                'rto'          => $d['rto'],
                'success_rate' => $d['count'] ? round($d['delivered'] / $d['count'] * 100, 1) : 0,
            ], array_keys($pincodeCounts), $pincodeCounts), 0, 20));

        $topRtoPincodes = array_values(array_slice(
            array_filter(array_map(fn($p) => $p, $topPincodes), fn($p) => $p['rto'] > 0), 0, 10));

        uasort($stateCounts, fn($a, $b) => $b['count'] <=> $a['count']);
        $stateBreakdown = array_values(array_map(fn($state, $d) => [
            'state'        => $state,
            'count'        => $d['count'],
            'delivered'    => $d['delivered'],
            'rto'          => $d['rto'],
            'revenue'      => round($d['revenue'], 0),
            'success_rate' => $d['count'] ? round($d['delivered'] / $d['count'] * 100, 1) : 0,
        ], array_keys($stateCounts), $stateCounts));

        ksort($monthlyCounts);
        $monthlyTrend = array_values(array_map(fn($m, $d) => [
            'month'     => $m,
            'total'     => $d['total'],
            'delivered' => $d['delivered'],
            'rto'       => $d['rto'],
            'cod'       => $d['cod'],
            'rto_rate'  => $d['total'] ? round($d['rto'] / $d['total'] * 100, 1) : 0,
        ], array_keys($monthlyCounts), $monthlyCounts));

        arsort($statusCounts);
        $statusBreakdown = array_values(array_map(
            fn($s, $c) => ['status' => $s, 'count' => $c],
            array_keys($statusCounts), $statusCounts));

        $codRtoCount = $orders->filter(function($o) {
            $raw = is_array($o->raw_payload) ? $o->raw_payload : json_decode($o->raw_payload, true);
            $payMethod = $raw['payment_method'] ?? '';
            $isCod = $payMethod === 'cod' || str_contains(strtolower($payMethod), 'cash');
            $note  = strtoupper(trim($raw['customer_note'] ?? ''));
            return $isCod && in_array($this->normalizeNote($note, $o->status), ['RTO', 'RTO Initiated']);
        })->count();

        return response()->json([
            'total'            => $total,
            'delivered'        => $deliveredCount,
            'in_transit'       => $inTransitCount,
            'out_for_delivery' => $ofdCount,
            'rto_count'        => $rtoCount,
            'pending'          => $pendingCount,
            'rto_rate'         => $total ? round($rtoCount / $total * 100, 1) : 0,
            'delivery_rate'    => $total ? round($deliveredCount / $total * 100, 1) : 0,
            'cod_count'        => $codCount,
            'prepaid_count'    => $prepaidCount,
            'cod_total'        => round($codTotal, 0),
            'prepaid_total'    => round($prepaidTotal, 0),
            'cod_at_risk'      => round($codAtRisk, 0),
            'cod_rto_rate'     => $codCount ? round($codRtoCount / $codCount * 100, 1) : 0,
            'status_breakdown' => $statusBreakdown,
            'state_breakdown'  => $stateBreakdown,
            'pincode_breakdown'=> $topPincodes,
            'top_rto_pincodes' => $topRtoPincodes,
            'monthly_trend'    => $monthlyTrend,
        ]);
    }

    private function normalizeNote(string $note, string $orderStatus): string
    {
        foreach (self::STATUS_MAP as $key => $normalized) {
            if (str_contains($note, $key)) return $normalized;
        }
        return match($orderStatus) {
            'completed'  => 'Delivered',
            'cancelled'  => 'Cancelled',
            'processing' => 'Processing',
            'on-hold'    => 'On Hold',
            'refunded'   => 'Refunded',
            default      => 'Pending',
        };
    }
}
