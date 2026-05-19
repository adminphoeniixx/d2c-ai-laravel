<?php

declare(strict_types=1);

namespace App\Services\GST;

/**
 * Calculates GST split (CGST+SGST or IGST) for an order or line item.
 *
 * GST 2.0 rates (effective 22 Sep 2025):
 *   0%  — Fresh food, milk, education, health
 *   5%  — Essentials, apparel ≤₹2500, beauty, packaged food
 *  18%  — Standard: electronics, apparel >₹2500, most services
 *  40%  — Luxury/sin: premium cars, tobacco, aerated drinks
 */
class GSTCalculator
{
    /** Business categories → default GST rate. */
    public const CATEGORY_RATES = [
        'apparel'     => ['threshold' => 2500, 'below' => 5.0,  'above' => 18.0],
        'footwear'    => ['threshold' => 2500, 'below' => 5.0,  'above' => 18.0],
        'electronics' => ['flat' => 18.0],
        'beauty'      => ['flat' => 5.0],
        'food'        => ['flat' => 5.0],
        'luxury'      => ['flat' => 40.0],
        'other'       => ['flat' => 18.0],
    ];

    /**
     * Determine the GST rate for a line item based on category + price.
     *
     * @param string $category  Business category (apparel, electronics, etc.)
     * @param float  $unitPrice Per-piece price in INR
     * @param float|null $overrideRate  Explicit rate (if product has custom HSN rate)
     */
    public static function rateForItem(string $category, float $unitPrice, ?float $overrideRate = null): float
    {
        if ($overrideRate !== null) {
            return $overrideRate;
        }

        $category = strtolower(trim($category));
        $config = self::CATEGORY_RATES[$category] ?? self::CATEGORY_RATES['other'];

        if (isset($config['flat'])) {
            return $config['flat'];
        }

        // Threshold-based (apparel, footwear) — price is always in INR
        return $unitPrice <= $config['threshold'] ? $config['below'] : $config['above'];
    }

    /**
     * Calculate GST split for an order.
     *
     * @param float  $taxableAmount   Total taxable value (excl. GST, or we reverse-calculate)
     * @param float  $gstRate         GST rate in percent (5, 18, 40)
     * @param string $sellerStateCode 2-digit GSTIN state code of seller
     * @param string|null $buyerStateCode  2-digit GSTIN state code of buyer (from shipping address)
     * @param bool   $priceIncludesGst Whether the price already includes GST
     *
     * @return array{
     *     taxable_amount: float,
     *     gst_rate: float,
     *     cgst_rate: float,
     *     sgst_rate: float,
     *     igst_rate: float,
     *     cgst_amount: float,
     *     sgst_amount: float,
     *     igst_amount: float,
     *     total_gst: float,
     *     is_intra_state: bool,
     *     place_of_supply: string,
     * }
     */
    public static function calculate(
        float   $taxableAmount,
        float   $gstRate,
        string  $sellerStateCode,
        ?string $buyerStateCode,
        bool    $priceIncludesGst = true,
    ): array {
        $isIntraState = $buyerStateCode !== null && $sellerStateCode === $buyerStateCode;

        // If price includes GST, reverse-calculate the taxable amount
        if ($priceIncludesGst && $gstRate > 0) {
            $taxableAmount = round($taxableAmount / (1 + $gstRate / 100), 2);
        }

        $totalGst = round($taxableAmount * $gstRate / 100, 2);

        if ($isIntraState) {
            $halfRate = round($gstRate / 2, 2);
            $cgst = round($totalGst / 2, 2);
            $sgst = $totalGst - $cgst; // Avoid rounding mismatch
            $igst = 0.0;
        } else {
            $halfRate = 0.0;
            $cgst = 0.0;
            $sgst = 0.0;
            $igst = $totalGst;
        }

        return [
            'taxable_amount' => $taxableAmount,
            'gst_rate'       => $gstRate,
            'cgst_rate'      => $isIntraState ? $halfRate : 0.0,
            'sgst_rate'      => $isIntraState ? $halfRate : 0.0,
            'igst_rate'      => $isIntraState ? 0.0 : $gstRate,
            'cgst_amount'    => $cgst,
            'sgst_amount'    => $sgst,
            'igst_amount'    => $igst,
            'total_gst'      => $totalGst,
            'is_intra_state' => $isIntraState,
            'place_of_supply' => $buyerStateCode
                ? StateCodeMap::stateName($buyerStateCode) . " ({$buyerStateCode})"
                : 'Unknown',
        ];
    }

    /**
     * Calculate GST for an entire Shopify order.
     *
     * @param array  $order           Raw Shopify order data
     * @param string $sellerStateCode Company's registered state code
     * @param string $businessCategory Company's business category
     * @param float|null $defaultGstRate Override rate (null = auto from category)
     */
    public static function calculateForShopifyOrder(
        array   $order,
        string  $sellerStateCode,
        string  $businessCategory,
        ?float  $defaultGstRate = null,
    ): array {
        // Determine buyer state from shipping address
        $buyerStateCode = null;
        $shippingProvince = $order['shipping_address']['province_code'] ?? null;
        if ($shippingProvince) {
            $buyerStateCode = StateCodeMap::shopifyProvinceToStateCode($shippingProvince);
        }

        // If no shipping address, try billing address
        if (!$buyerStateCode) {
            $billingProvince = $order['billing_address']['province_code'] ?? null;
            if ($billingProvince) {
                $buyerStateCode = StateCodeMap::shopifyProvinceToStateCode($billingProvince);
            }
        }

        // Calculate per line item
        $lineItems = [];
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;
        $totalTaxable = 0.0;

        foreach ($order['line_items'] ?? [] as $item) {
            $unitPrice = (float) ($item['price'] ?? 0);
            $quantity  = (int) ($item['quantity'] ?? 1);
            $lineTotal = $unitPrice * $quantity;

            // Determine GST rate for this item
            // For threshold-based categories (apparel, footwear), always calculate per-item
            // based on unit price vs ₹2,500 threshold
            $categoryConfig = self::CATEGORY_RATES[$businessCategory] ?? self::CATEGORY_RATES['other'];
            if (isset($categoryConfig['threshold'])) {
                $itemGstRate = self::rateForItem($businessCategory, $unitPrice);
            } else {
                $itemGstRate = $defaultGstRate ?? self::rateForItem($businessCategory, $unitPrice);
            }

            $gst = self::calculate(
                taxableAmount: $lineTotal,
                gstRate: $itemGstRate,
                sellerStateCode: $sellerStateCode,
                buyerStateCode: $buyerStateCode,
                priceIncludesGst: true,
            );

            $lineItems[] = [
                'product_name' => $item['title'] ?? $item['name'] ?? '',
                'sku'          => $item['sku'] ?? '',
                'quantity'     => $quantity,
                'unit_price'   => $unitPrice,
                'line_total'   => $lineTotal,
                'gst_rate'     => $itemGstRate,
                ...$gst,
            ];

            $totalCgst    += $gst['cgst_amount'];
            $totalSgst    += $gst['sgst_amount'];
            $totalIgst    += $gst['igst_amount'];
            $totalTaxable += $gst['taxable_amount'];
        }

        $isIntraState = $buyerStateCode !== null && $sellerStateCode === $buyerStateCode;

        return [
            'seller_state_code' => $sellerStateCode,
            'buyer_state_code'  => $buyerStateCode,
            'place_of_supply'   => $buyerStateCode
                ? StateCodeMap::stateName($buyerStateCode) . " ({$buyerStateCode})"
                : 'Unknown',
            'is_intra_state'    => $isIntraState,
            'taxable_amount'    => round($totalTaxable, 2),
            'cgst_amount'       => round($totalCgst, 2),
            'sgst_amount'       => round($totalSgst, 2),
            'igst_amount'       => round($totalIgst, 2),
            'total_gst'         => round($totalCgst + $totalSgst + $totalIgst, 2),
            'line_items'        => $lineItems,
        ];
    }
}
