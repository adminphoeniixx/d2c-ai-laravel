<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class TransactionCategorizer
{
    protected static string $systemPrompt = <<<'PROMPT'
You categorize Indian business bank transactions. Given a JSON array of transactions, return a JSON array of category strings in the SAME ORDER.

Categories (use exactly these strings):
salary, vendor, gst_tax, ads, logistics, platform_fee, sales, rent, utilities, refund, interest, bank_charges, cash, transfer, upi, other

Rules:
- salary: salary/wages/payroll payments
- vendor: NEFT/RTGS to suppliers, raw material, purchase payments
- gst_tax: GST, TDS, income tax, PF, ESI, professional tax
- ads: Meta/Facebook/Google Ads/Instagram ad spend
- logistics: Delhivery, Shiprocket, BlueDart, Ecom Express, courier, freight
- platform_fee: Shopify, Razorpay, Cashfree, PayU, Stripe fees
- sales: settlements, payouts, COD remittance, revenue credits
- rent: rent, lease payments
- utilities: electricity, internet, phone bills
- refund: refunds, reversals, chargebacks
- interest: bank interest
- bank_charges: SMS charges, maintenance, annual fees
- cash: ATM, cash withdrawal/deposit
- transfer: self transfer, own account transfer
- upi: UPI transactions not matching other categories
- other: anything else

Return ONLY a JSON array of strings. Example: ["salary","vendor","ads","upi","other"]
PROMPT;

    /**
     * Categorize a batch of transactions using AI.
     * Returns array of categories in same order as input.
     */
    public static function categorize(array $transactions): array
    {
        if (empty($transactions)) return [];

        // Build compact input — just descriptions and amounts
        $items = [];
        foreach ($transactions as $i => $txn) {
            $items[] = [
                'i' => $i,
                'd' => substr($txn['description'] ?? '', 0, 100), // trim descriptions
                'a' => $txn['amount'] ?? 0,
                't' => $txn['type'] ?? 'debit',
            ];
        }

        $response = DoInferenceClient::light(
            self::$systemPrompt,
            "Categorize these " . count($items) . " transactions:\n" . json_encode($items),
            config('ai.limits.bank_categorize_batch', 800)
        );

        $parsed = DoInferenceClient::parseJson($response);

        if (is_array($parsed) && count($parsed) === count($transactions)) {
            return $parsed;
        }

        // If AI returned wrong count or failed, use keyword fallback
        Log::info('AI categorization incomplete, using keyword fallback');
        return self::keywordFallback($transactions);
    }

    /**
     * Keyword-based fallback categorization.
     */
    public static function keywordFallback(array $transactions): array
    {
        $patterns = [
            'salary'       => ['salary', 'sal/', 'payroll', 'wages'],
            'vendor'       => ['neft', 'rtgs', 'vendor', 'supplier', 'purchase order'],
            'gst_tax'      => ['gst', 'tds', 'advance tax', 'income tax', 'professional tax', 'pt/', 'epf', 'esic', 'pf/'],
            'ads'          => ['meta', 'facebook', 'google ads', 'googleads', 'fb ads', 'instagram'],
            'logistics'    => ['delhivery', 'shiprocket', 'bluedart', 'ecom express', 'dtdc', 'xpressbees', 'courier', 'freight'],
            'platform_fee' => ['shopify', 'woocommerce', 'razorpay', 'cashfree', 'payu', 'stripe', 'instamojo'],
            'sales'        => ['settlement', 'payout', 'remittance', 'cod remit'],
            'rent'         => ['rent', 'lease'],
            'utilities'    => ['electricity', 'water', 'internet', 'broadband', 'airtel', 'jio', 'vodafone'],
            'refund'       => ['refund', 'reversal', 'chargeback'],
            'interest'     => ['interest', 'int.pd'],
            'bank_charges' => ['bank charge', 'sms charge', 'maintenance', 'annual fee'],
            'cash'         => ['atm', 'cash withdrawal', 'cash deposit'],
            'transfer'     => ['self transfer', 'fund transfer', 'own account'],
        ];

        $results = [];
        foreach ($transactions as $txn) {
            $desc = strtolower($txn['description'] ?? '');
            $cat = 'other';
            foreach ($patterns as $category => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($desc, $kw)) { $cat = $category; break 2; }
                }
            }
            if ($cat === 'other' && str_contains($desc, 'upi')) $cat = 'upi';
            $results[] = $cat;
        }

        return $results;
    }
}
