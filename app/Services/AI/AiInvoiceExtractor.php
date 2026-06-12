<?php

declare(strict_types=1);

namespace App\Services\AI;

class AiInvoiceExtractor
{
    // System prompt kept SHORT to minimize input tokens
    private const SYSTEM = 'You extract invoice data from text. Return ONLY valid JSON, no explanation. Fields: source (meta/google/delhivery/shiprocket/razorpay/other), invoice_number, invoice_date (YYYY-MM-DD), period_from (YYYY-MM-DD), period_to (YYYY-MM-DD), subtotal (number), cgst (number), sgst (number), igst (number), tax (number, total of cgst+sgst+igst), total_amount (number), platform (meta/google/other), currency (INR/USD), gstin, invoice_type (freight/vas/cod/other — use "vas" if description mentions Waybill/Whatsapp/VAS, use "freight" for Freight Services), hsn_sac (string), transactions (array of {date,amount,status,description}). Omit fields you cannot find. For Meta billing reports, extract each daily payment as a transaction. Skip "Funded" entries.';

    /**
     * Extract invoice data from PDF text using AI.
     * Falls back to regex if AI is unavailable.
     */
    public static function extract(string $pdfText): ?array
    {
        if (empty(trim($pdfText))) return null;

        // Send full text — invoice PDFs are small (2-3 pages = ~2K tokens max)
        $trimmed = $pdfText;

        $result = DoAiClient::light(self::SYSTEM, $trimmed);

        if (!$result || isset($result['_text'])) return null;

        return $result;
    }
}
