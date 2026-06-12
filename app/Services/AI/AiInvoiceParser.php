<?php

declare(strict_types=1);

namespace App\Services\AI;

class AiInvoiceParser
{
    protected DoAiService $ai;

    public function __construct()
    {
        $this->ai = new DoAiService();
    }

    /**
     * Parse invoice from PDF text using AI.
     * Returns structured data — works with ANY invoice format.
     */
    public function parse(string $pdfText): ?array
    {
        // Trim to save tokens — only send first 3000 chars
        $text = mb_substr($pdfText, 0, 3000);

        $system = 'You extract invoice data from text. Return ONLY valid JSON, no explanation. Fields: source (meta/google/delhivery/shiprocket/razorpay/amazon/flipkart/generic), platform (meta/google/other for ads, logistics for delivery, payment for razorpay), invoice_number, invoice_date (YYYY-MM-DD), period_from (YYYY-MM-DD or null), period_to (YYYY-MM-DD or null), subtotal (number), tax (number), total_amount (number), currency (INR/USD), invoice_type (freight/vas/ads/settlement/other), gstin (seller GSTIN if found), customer_gstin (buyer GSTIN if found), line_items (array of {date, description, amount, status} for daily entries like Meta billing or Delhivery shipments). Omit null fields. All amounts as numbers without commas.';

        $result = $this->ai->light($system, $text);
        return DoAiService::parseJson($result);
    }

    /**
     * Extract from PDF file path.
     */
    public function parseFromFile(string $pdfPath): ?array
    {
        $text = $this->extractPdfText($pdfPath);
        if (empty($text)) return null;
        return $this->parse($text);
    }

    protected function extractPdfText(string $path): string
    {
        $output = [];
        exec("pdftotext -layout " . escapeshellarg($path) . " - 2>/dev/null", $output);
        return implode("\n", $output);
    }
}
