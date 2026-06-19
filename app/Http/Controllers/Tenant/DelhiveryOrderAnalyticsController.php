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
        $codRtoCount   = 0;
        $total         = 0;

        $base->select([
            'id', 'provider', 'status', 'fulfillment_status', 'total_amount', 'placed_at',
            'customer_name', 'shipping_address', 'raw_payload',
        ])->chunkById(500, function ($orders) use (
            &$statusCounts, &$codTotal, &$prepaidTotal, &$codCount, &$prepaidCount,
            &$rtoCount, &$deliveredCount, &$inTransitCount, &$ofdCount, &$pendingCount,
            &$pincodeCounts, &$stateCounts, &$monthlyCounts, &$codAtRisk, &$codRtoCount, &$total
        ) {

        foreach ($orders as $order) {
            $total++;
            $raw = is_array($order->raw_payload)
                ? $order->raw_payload
                : json_decode($order->raw_payload, true);

            $fields      = $this->extractOrderFields($order, $raw);
            $note        = $fields['note'];
            $isCod       = $fields['is_cod'];
            $amount      = (float) ($order->total_amount ?? 0);
            $shipPostcode= $fields['postcode'];
            $shipState   = $fields['state'];
            $month       = $order->placed_at ? \Carbon\Carbon::parse($order->placed_at)->format('Y-m') : null;

            $deliveryStatus = $this->normalizeNote($note, $order->provider, $order->status, $order->fulfillment_status);
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
                if (in_array($deliveryStatus, ['RTO', 'RTO Initiated'])) {
                    $codRtoCount++;
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
        });

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

    /**
     * WooCommerce and Shopify use completely different JSON shapes for the
     * same logical data — orders from both land in raw_payload as-is from
     * each platform's own API, so every field name needs a provider branch:
     *   - note:     WooCommerce "customer_note"      vs Shopify "note"
     *   - payment:  WooCommerce "payment_method"=cod  vs Shopify "gateway"/
     *               "payment_gateway_names" (COD gateways are typically
     *               named things like "Cash on Delivery (COD)")
     *   - address:  WooCommerce "shipping.postcode"/"state" vs Shopify
     *               "shipping_address.zip"/"province" (falls back to
     *               billing_address the same way WooCommerce falls back
     *               to billing)
     */
    private function extractOrderFields($order, ?array $raw): array
    {
        if ($order->provider === 'shopify') {
            $note      = strtoupper(trim($raw['note'] ?? ''));
            $gateways  = $raw['payment_gateway_names'] ?? [];
            $gatewayStr= is_array($gateways) ? implode(' ', $gateways) : (string) ($raw['gateway'] ?? '');
            $isCod     = str_contains(strtolower($gatewayStr), 'cod')
                || str_contains(strtolower($gatewayStr), 'cash on delivery')
                || str_contains(strtolower($gatewayStr), 'cash');
            $postcode  = $raw['shipping_address']['zip'] ?? $raw['billing_address']['zip'] ?? null;
            $state     = $raw['shipping_address']['province'] ?? $raw['billing_address']['province'] ?? null;
        } else {
            // woocommerce (default — also covers any future provider
            // that happens to share this shape, e.g. a WooCommerce-style
            // CSV import)
            $note      = strtoupper(trim($raw['customer_note'] ?? ''));
            $payMethod = $raw['payment_method'] ?? '';
            $isCod     = $payMethod === 'cod' || str_contains(strtolower($payMethod), 'cash');
            $postcode  = $raw['shipping']['postcode'] ?? $raw['billing']['postcode'] ?? null;
            $state     = $raw['shipping']['state'] ?? $raw['billing']['state'] ?? null;
        }

        return ['note' => $note, 'is_cod' => $isCod, 'postcode' => $postcode, 'state' => $state];
    }

    private function normalizeNote(string $note, string $provider, string $orderStatus, ?string $fulfillmentStatus): string
    {
        // Direct match on the note field always takes priority when present —
        // Delhivery (or any courier integration) writing real delivery status
        // into the note is more reliable than either platform's own status.
        foreach (self::STATUS_MAP as $key => $normalized) {
            if (str_contains($note, $key)) return $normalized;
        }

        if ($provider === 'shopify') {
            // Shopify has no single "status" — financial_status (paid/refunded/
            // voided) and fulfillment_status (fulfilled/partial/null) are
            // separate axes. orders.status is synced from financial_status.
            return match(true) {
                $fulfillmentStatus === 'fulfilled'        => 'Delivered',
                $orderStatus === 'voided'                  => 'Cancelled',
                $orderStatus === 'refunded'                => 'Refunded',
                $fulfillmentStatus === 'partial'           => 'In Transit',
                default                                     => 'Pending',
            };
        }

        // woocommerce
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
