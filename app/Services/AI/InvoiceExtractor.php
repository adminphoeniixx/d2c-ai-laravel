<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class InvoiceExtractor
{
    protected static string $systemPrompt = <<<'PROMPT'
You are an invoice data extractor for Indian businesses. Given invoice text, extract ALL details into JSON. Be precise with numbers. Return ONLY valid JSON, no explanation.

Required JSON structure:
{
  "source": "meta|google|delhivery|shiprocket|bluedart|razorpay|amazon|flipkart|other",
  "type": "ads|logistics|payment_gateway|marketplace|other",
  "platform": "meta|google|delhivery|shiprocket|other",
  "invoice_number": "string or null",
  "invoice_date": "YYYY-MM-DD or null",
  "period_from": "YYYY-MM-DD or null",
  "period_to": "YYYY-MM-DD or null",
  "subtotal": 0.00,
  "cgst": 0.00,
  "sgst": 0.00,
  "igst": 0.00,
  "tax": 0.00,
  "tds": 0.00,
  "total_amount": 0.00,
  "currency": "INR",
  "vendor_name": "string or null",
  "vendor_gstin": "string or null",
  "customer_gstin": "string or null",
  "hsn_sac": "string or null",
  "transactions": [
    {"date": "YYYY-MM-DD", "description": "string", "amount": 0.00, "type": "debit|credit"}
  ]
}

Rules:
- Dates must be YYYY-MM-DD format
- All amounts as numbers, not strings
- tax = cgst + sgst + igst
- For Meta Ads: extract each daily payment as a transaction, skip "Funded" entries
- For Delhivery: identify freight vs VAS invoice type
- If field not found, use null for strings, 0 for numbers
- transactions array can be empty if no line items found
PROMPT;

    /**
     * Extract invoice data from PDF text using AI.
     * Falls back to regex if AI unavailable.
     */
    public static function extract(string $pdfText): array
    {
        if (empty(trim($pdfText))) {
            return ['error' => 'Empty text', 'source' => 'unknown'];
        }

        // Trim text to save tokens — keep first 3000 chars + last 1000 chars
        $trimmed = self::trimText($pdfText, 3000, 1000);

        $response = DoInferenceClient::light(
            self::$systemPrompt,
            "Extract invoice data from this text:\n\n" . $trimmed,
            config('ai.limits.invoice_extract', 1500)
        );

        $parsed = DoInferenceClient::parseJson($response);

        if ($parsed) {
            return $parsed;
        }

        // Fallback to basic regex extraction
        Log::info('AI extraction failed, falling back to regex');
        return self::regexFallback($pdfText);
    }

    /**
     * Extract text from PDF file, then parse with AI.
     */
    public static function extractFromFile(string $pdfPath): array
    {
        $text = self::pdfToText($pdfPath);
        if (empty($text)) {
            return ['error' => 'Could not read PDF', 'source' => 'unknown'];
        }
        return self::extract($text);
    }

    protected static function pdfToText(string $path): string
    {
        try {
            $output = [];
            exec("pdftotext -layout " . escapeshellarg($path) . " - 2>/dev/null", $output);
            return implode("\n", $output);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Trim text to save tokens. Keep start + end (invoices have totals at the end).
     */
    protected static function trimText(string $text, int $startChars, int $endChars): string
    {
        $text = preg_replace('/\n{3,}/', "\n\n", $text); // collapse blank lines
        $text = preg_replace('/[ \t]{3,}/', '  ', $text); // collapse spaces

        if (strlen($text) <= ($startChars + $endChars + 100)) {
            return $text;
        }

        return substr($text, 0, $startChars) . "\n...[trimmed]...\n" . substr($text, -$endChars);
    }

    /**
     * Basic regex fallback when AI is unavailable.
     */
    protected static function regexFallback(string $text): array
    {
        $data = ['source' => 'unknown', 'type' => 'other'];
        $t = strtolower($text);

        if (str_contains($t, 'meta ads') || str_contains($t, 'facebook india')) {
            $data['source'] = 'meta'; $data['type'] = 'ads'; $data['platform'] = 'meta';
        } elseif (str_contains($t, 'delhivery')) {
            $data['source'] = 'delhivery'; $data['type'] = 'logistics'; $data['platform'] = 'delhivery';
        } elseif (str_contains($t, 'google ads')) {
            $data['source'] = 'google'; $data['type'] = 'ads'; $data['platform'] = 'google';
        }

        if (preg_match('/Invoice\s*(Number|No|#)\s*:?\s*([A-Z0-9\-]+)/i', $text, $m)) $data['invoice_number'] = $m[2];
        if (preg_match('/Total.*?([\d,]+\.?\d*)\s*(INR)?/i', $text, $m)) $data['total_amount'] = (float) str_replace(',', '', $m[1]);

        return $data;
    }
}
