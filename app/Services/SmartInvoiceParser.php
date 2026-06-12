<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;

class SmartInvoiceParser
{
    /**
     * Parse any invoice PDF and return structured data.
     * Auto-detects: Meta Ads, Google Ads, Delhivery, Razorpay, generic.
     */
    public function parse(string $pdfPath): array
    {
        $text = $this->extractText($pdfPath);
        if (empty($text)) return ['type' => 'unknown', 'error' => 'Could not extract text from PDF'];

        $type = $this->detectType($text);

        return match ($type) {
            'meta_ads'   => $this->parseMetaAds($text),
            'delhivery'  => $this->parseDelhivery($text),
            'razorpay'   => $this->parseRazorpay($text),
            'google_ads' => $this->parseGoogleAds($text),
            default      => $this->parseGeneric($text),
        };
    }

    protected function detectType(string $text): string
    {
        $lower = strtolower($text);

        if (str_contains($lower, 'meta ads') || str_contains($lower, 'facebook india online')
            || str_contains($lower, 'billing report') && str_contains($lower, 'transaction id')) {
            return 'meta_ads';
        }
        if (str_contains($lower, 'delhivery') || str_contains($lower, 'aapcs9575e')) {
            return 'delhivery';
        }
        if (str_contains($lower, 'razorpay') || str_contains($lower, 'razorpay software')) {
            return 'razorpay';
        }
        if (str_contains($lower, 'google ads') || str_contains($lower, 'google asia pacific')) {
            return 'google_ads';
        }
        return 'generic';
    }

    /**
     * Parse Meta Ads billing report PDF.
     */
    protected function parseMetaAds(string $text): array
    {
        $result = [
            'type'           => 'meta_ads',
            'platform'       => 'meta',
            'invoice_number' => null,
            'invoice_date'   => null,
            'period_from'    => null,
            'period_to'      => null,
            'subtotal'       => 0,
            'tax'            => 0,
            'total_amount'   => 0,
            'currency'       => 'INR',
            'gstin'          => null,
            'transactions'   => [],
        ];

        // Extract billing period: "Billing Report: 5/1/2026 - 6/1/2026"
        if (preg_match('/Billing\s*Report:\s*(\d{1,2}\/\d{1,2}\/\d{4})\s*-\s*(\d{1,2}\/\d{1,2}\/\d{4})/i', $text, $m)) {
            $result['period_from'] = Carbon::createFromFormat('n/j/Y', $m[1])->format('Y-m-d');
            $result['period_to'] = Carbon::createFromFormat('n/j/Y', $m[2])->format('Y-m-d');
            $result['invoice_date'] = $result['period_to'];
        }

        // Extract GSTIN
        if (preg_match('/GSTIN:\s*(\d{2}[A-Z0-9]{13})/i', $text, $m)) {
            $result['gstin'] = $m[1];
        }

        // Extract Account ID
        if (preg_match('/Account:\s*(\d+)/i', $text, $m)) {
            $result['invoice_number'] = 'META-' . $m[1];
        }

        // Extract total and GST
        if (preg_match('/Total\s*Amount\s*Billed\s*([\d,]+\.?\d*)\s*INR/i', $text, $m)) {
            $result['subtotal'] = $this->toFloat($m[1]);
        }
        if (preg_match('/GST\s*Amount\s*in\s*INR:\s*([\d,]+\.?\d*)/i', $text, $m)) {
            $result['tax'] = $this->toFloat($m[1]);
        }
        $result['total_amount'] = $result['subtotal'] + $result['tax'];

        // Extract daily transactions
        // Pattern: date, transaction_id, payment_method, amount INR, status
        preg_match_all('/(\d{1,2}\/\d{1,2}\/\d{4})\s+(\d[\d-]+\d)\s+(N\/A|Visa[^)]*\d{4})\s+([\d,]+\.?\d*)\s*INR\s+(Paid|Funded|Pending)/i', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $status = trim($m[5]);
            // Skip "Funded" entries (those are top-ups, not spend)
            if ($status === 'Funded') continue;

            $amount = $this->toFloat($m[4]);
            if ($amount <= 0) continue;

            $result['transactions'][] = [
                'date'        => Carbon::createFromFormat('n/j/Y', $m[1])->format('Y-m-d'),
                'reference'   => trim($m[2]),
                'description' => 'Meta Ads - Daily Spend',
                'amount'      => $amount,
                'type'        => 'debit',
                'category'    => 'ads',
            ];
        }

        return $result;
    }

    /**
     * Parse Delhivery invoice PDF.
     */
    protected function parseDelhivery(string $text): array
    {
        $result = [
            'type'           => 'delhivery',
            'platform'       => 'logistics',
            'invoice_number' => null,
            'invoice_date'   => null,
            'period_from'    => null,
            'period_to'      => null,
            'subtotal'       => 0,
            'tax'            => 0,
            'total_amount'   => 0,
            'currency'       => 'INR',
            'gstin'          => null,
            'transactions'   => [],
            'invoice_type'   => 'freight',
        ];

        // Invoice Number
        if (preg_match('/Invoice\s*Number\s*:?\s*([A-Z0-9]+)/i', $text, $m)) {
            $result['invoice_number'] = $m[1];
        }

        // Invoice Date
        if (preg_match('/Invoice\s*Date\s*:?\s*(\d{1,2}-[A-Z]{3}-\d{2,4})/i', $text, $m)) {
            try { $result['invoice_date'] = Carbon::parse($m[1])->format('Y-m-d'); } catch (\Throwable $e) {}
        }

        // Period
        if (preg_match('/duration\s*of\s*(\d{1,2}\s*\w+,?\s*\d{2,4})\s*-\s*(\d{1,2}\s*\w+,?\s*\d{2,4})/i', $text, $m)) {
            try {
                $result['period_from'] = Carbon::parse($m[1])->format('Y-m-d');
                $result['period_to'] = Carbon::parse($m[2])->format('Y-m-d');
            } catch (\Throwable $e) {}
        }

        // Customer GSTIN
        if (preg_match('/GSTIN\s*:\s*(\d{2}[A-Z0-9]{13})/', $text, $m)) {
            $result['gstin'] = $m[1];
        }

        // Amounts
        if (preg_match('/Sub\s*Total\s*([\d,]+\.?\d*)/i', $text, $m)) {
            $result['subtotal'] = $this->toFloat($m[1]);
        }
        if (preg_match('/Total\s*All\s*amount\s*in\s*\(INR\)\s*([\d,]+\.?\d*)/i', $text, $m)) {
            $result['total_amount'] = $this->toFloat($m[1]);
        }
        $result['tax'] = $result['total_amount'] - $result['subtotal'];

        // Detect invoice type
        if (preg_match('/Waybill\s*Journey|WhatsApp|VAS/i', $text)) {
            $result['invoice_type'] = 'vas';
        }

        // Single transaction
        if ($result['total_amount'] > 0) {
            $result['transactions'][] = [
                'date'        => $result['invoice_date'] ?? now()->format('Y-m-d'),
                'reference'   => $result['invoice_number'],
                'description' => 'Delhivery - ' . ucfirst($result['invoice_type']) . ' charges',
                'amount'      => $result['total_amount'],
                'type'        => 'debit',
                'category'    => 'logistics',
            ];
        }

        return $result;
    }

    protected function parseRazorpay(string $text): array
    {
        $result = [
            'type' => 'razorpay', 'platform' => 'platform_fee',
            'invoice_number' => null, 'invoice_date' => null,
            'subtotal' => 0, 'tax' => 0, 'total_amount' => 0,
            'currency' => 'INR', 'transactions' => [],
        ];

        if (preg_match('/Invoice\s*#?\s*:?\s*([\w-]+)/i', $text, $m)) $result['invoice_number'] = $m[1];
        if (preg_match('/Total\s*:?\s*₹?\s*([\d,]+\.?\d*)/i', $text, $m)) $result['total_amount'] = $this->toFloat($m[1]);

        return $result;
    }

    protected function parseGoogleAds(string $text): array
    {
        $result = [
            'type' => 'google_ads', 'platform' => 'google',
            'invoice_number' => null, 'invoice_date' => null,
            'subtotal' => 0, 'tax' => 0, 'total_amount' => 0,
            'currency' => 'INR', 'transactions' => [],
        ];

        if (preg_match('/Invoice\s*number\s*:?\s*([\w-]+)/i', $text, $m)) $result['invoice_number'] = $m[1];
        if (preg_match('/Subtotal\s*:?\s*₹?\s*([\d,]+\.?\d*)/i', $text, $m)) $result['subtotal'] = $this->toFloat($m[1]);
        if (preg_match('/Tax\s*:?\s*₹?\s*([\d,]+\.?\d*)/i', $text, $m)) $result['tax'] = $this->toFloat($m[1]);
        if (preg_match('/Total\s*:?\s*₹?\s*([\d,]+\.?\d*)/i', $text, $m)) $result['total_amount'] = $this->toFloat($m[1]);

        return $result;
    }

    protected function parseGeneric(string $text): array
    {
        $result = [
            'type' => 'generic', 'platform' => 'other',
            'invoice_number' => null, 'invoice_date' => null,
            'subtotal' => 0, 'tax' => 0, 'total_amount' => 0,
            'currency' => 'INR', 'transactions' => [],
        ];

        if (preg_match('/Invoice\s*#?\s*:?\s*([\w-]+)/i', $text, $m)) $result['invoice_number'] = $m[1];
        if (preg_match('/Total\s*:?\s*₹?\s*([\d,]+\.?\d*)/i', $text, $m)) $result['total_amount'] = $this->toFloat($m[1]);

        return $result;
    }

    protected function extractText(string $path): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($path);
            return $pdf->getText();
        } catch (\Throwable $e) {
            Log::warning('PDF text extraction failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    protected function toFloat(string $val): float
    {
        return (float) str_replace([',', ' '], '', $val);
    }
}
