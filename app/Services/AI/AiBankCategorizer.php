<?php

declare(strict_types=1);

namespace App\Services\AI;

class AiBankCategorizer
{
    private const SYSTEM = 'Categorize Indian D2C brand bank transactions. Return ONLY a JSON array of category strings, one per transaction, same order.

Categories (use exact spelling):
- Logistics (Delhivery, Shiprocket, BlueDart, Ecom Express, DTDC, Xpressbees, Shadowfax, courier, freight)
- Payment Gateway (Razorpay, Cashfree, PayU, Stripe, Instamojo, payment aggregator, escrow)
- Ads (Meta, Facebook, Google Ads, Instagram ad spend)
- Platform Fee (Shopify, WooCommerce, marketplace commission, Amazon seller, Flipkart seller)
- Salary (salary, payroll, wages, employee payment)
- GST & Tax (GST, TDS, income tax, advance tax, professional tax, EPF, ESIC)
- Rent (rent, lease, office space)
- Utilities (electricity, water, internet, broadband, phone)
- Bank Charges (bank charge, SMS charge, maintenance fee, interest)
- Transfer (self transfer, own account, TPT, fund transfer between own accounts)
- Refund (refund, reversal, chargeback)
- Sales (settlement, payout, COD remittance)
- Inventory (raw material, packaging, supplier, manufacturer)
- Software (SaaS, subscription, hosting, domain, cloud)
- UPI (UPI payments not matching above)
- Miscellaneous (anything else)';

    public static function categorize(array $descriptions): array
    {
        if (empty($descriptions)) return [];

        $chunks = array_chunk($descriptions, 50, true);
        $results = [];

        foreach ($chunks as $chunk) {
            $lines = [];
            foreach (array_values($chunk) as $i => $desc) {
                $lines[] = ($i + 1) . '. ' . mb_substr(trim($desc), 0, 100);
            }

            $aiResult = DoAiClient::light(self::SYSTEM, implode("\n", $lines));

            if (is_array($aiResult) && !isset($aiResult['_text'])) {
                foreach (array_values($aiResult) as $cat) {
                    $results[] = is_string($cat) ? trim($cat) : 'Miscellaneous';
                }
            } else {
                $results = array_merge($results, array_fill(0, count($chunk), 'Miscellaneous'));
            }
        }

        return $results;
    }
}
