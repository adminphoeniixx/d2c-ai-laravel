<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AI\AiInvoiceExtractor;
use Illuminate\Support\Str;

class InvoicePdfExtractor
{
    /**
     * Extract invoice details from a PDF file.
     * Strategy: AI first (smart), regex fallback (dumb but works offline).
     */
    public function extract(string $pdfPath): array
    {
        $text = $this->extractText($pdfPath);
        if (empty($text)) {
            return ['error' => 'Could not read PDF', 'source' => 'unknown'];
        }

        // Try AI extraction first
        $aiResult = AiInvoiceExtractor::extract($text);
        if ($aiResult && !empty($aiResult['source'] ?? $aiResult['invoice_number'] ?? $aiResult['total_amount'] ?? null)) {
            $aiResult['_method'] = 'ai';
            return $aiResult;
        }

        // Fallback: regex extraction
        return $this->regexExtract($text);
    }

    protected function extractText(string $path): string
    {
        try {
            $output = [];
            exec("pdftotext -layout " . escapeshellarg($path) . " - 2>/dev/null", $output, $rc);
            if ($rc === 0 && !empty($output)) return implode("\n", $output);
        } catch (\Throwable $e) {}
        return '';
    }

    /**
     * Regex-based fallback extraction (no AI needed).
     */
    protected function regexExtract(string $text): array
    {
        $data = ['source' => 'unknown', '_method' => 'regex'];
        $t = strtolower($text);

        // Detect source
        if (str_contains($t, 'meta ads') || str_contains($t, 'facebook india') || str_contains($t, 'billing report')) {
            $data['source'] = 'meta'; $data['platform'] = 'meta';
        } elseif (str_contains($t, 'delhivery')) {
            $data['source'] = 'delhivery'; $data['type'] = 'logistics';
        } elseif (str_contains($t, 'google ads') || str_contains($t, 'google asia')) {
            $data['source'] = 'google'; $data['platform'] = 'google';
        } elseif (str_contains($t, 'razorpay')) {
            $data['source'] = 'razorpay';
        }

        // Common patterns
        if (preg_match('/Invoice\s*Number\s*:?\s*([A-Z0-9\-\/]+)/i', $text, $m)) $data['invoice_number'] = $m[1];

        // Meta's unique per-invoice identifier is "Transaction ID", a long
        // numeric string (often hyphenated, e.g. "27463631903324598-...").
        // The Account ID / "Tax invoice for <id>" number repeats across
        // EVERY invoice from the same ad account, so it must never be used
        // as the uniqueness key — doing so collides unrelated invoices and
        // causes one to silently overwrite another on re-upload.
        if (preg_match('/Transaction\s*ID\s*:?\s*([0-9\-]+)/i', $text, $m)) {
            $data['transaction_id'] = $m[1];
        }

        // Only fall back to an Account-ID-derived invoice_number if this
        // platform genuinely has no transaction_id to key on (e.g. an
        // older/different invoice format) — never for the common Meta case.
        if (empty($data['invoice_number']) && empty($data['transaction_id'])
            && preg_match('/Account(?:\s*ID)?\s*:?\s*(\d+)/i', $text, $m)) {
            $data['invoice_number'] = 'META-' . $m[1];
        }
        if (preg_match('/Invoice\s*Date\s*:?\s*(\d{1,2}-[A-Z]{3}-\d{2,4})/i', $text, $m)) $data['invoice_date'] = $this->parseDate($m[1]);
        if (preg_match('/Billing Report:\s*(\d{1,2}\/\d{1,2}\/\d{4})\s*-\s*(\d{1,2}\/\d{1,2}\/\d{4})/i', $text, $m)) {
            $data['period_from'] = $this->parseDate($m[1]);
            $data['period_to'] = $this->parseDate($m[2]);
            $data['invoice_date'] = $data['invoice_date'] ?? $data['period_to'];
        }
        if (preg_match('/duration of\s+(\d{1,2}\s+\w+,?\s+\d{2,4})\s*-\s*(\d{1,2}\s+\w+,?\s+\d{2,4})/i', $text, $m)) {
            $data['period_from'] = $this->parseDate($m[1]);
            $data['period_to'] = $this->parseDate($m[2]);
        }
        if (preg_match('/Total Amount Billed\s*([\d,]+\.?\d*)\s*INR/i', $text, $m)) $data['subtotal'] = $this->toFloat($m[1]);
        if (preg_match('/Sub Total\s*([\d,]+\.?\d*)/i', $text, $m)) $data['subtotal'] = $data['subtotal'] ?? $this->toFloat($m[1]);
        if (preg_match('/GST Amount.*?:\s*([\d,]+\.?\d*)/i', $text, $m)) $data['tax'] = $this->toFloat($m[1]);
        if (preg_match('/CGST\s*@\d+%\s*([\d,]+\.?\d*)/i', $text, $m)) $data['cgst'] = $this->toFloat($m[1]);
        if (preg_match('/SGST\s*@\d+%\s*([\d,]+\.?\d*)/i', $text, $m)) $data['sgst'] = $this->toFloat($m[1]);
        if (!isset($data['tax'])) $data['tax'] = ($data['cgst'] ?? 0) + ($data['sgst'] ?? 0);
        if (preg_match('/Total All amount.*?\(INR\)\s*([\d,]+\.?\d*)/i', $text, $m)) $data['total_amount'] = $this->toFloat($m[1]);
        $data['total_amount'] = $data['total_amount'] ?? (($data['subtotal'] ?? 0) + ($data['tax'] ?? 0));
        if (preg_match('/TDS Amount.*?:\s*([\d,]+\.?\d*)/i', $text, $m)) $data['tds'] = $this->toFloat($m[1]);
        if (preg_match('/Freight\s+Services/i', $text)) $data['invoice_type'] = 'freight';
        elseif (preg_match('/Waybill\s+Journey|Whatsapp/i', $text)) $data['invoice_type'] = 'vas';

        return $data;
    }

    protected function parseDate(string $val): ?string
    {
        $val = trim(str_replace(',', '', $val));
        try {
            foreach (['d-M-y', 'd-M-Y', 'n/j/Y', 'm/d/Y', 'd/m/Y', 'Y-m-d', 'd M y', 'd M Y'] as $fmt) {
                $date = \DateTime::createFromFormat($fmt, $val);
                if ($date) return $date->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable $e) { return null; }
    }

    protected function toFloat(string $val): float
    {
        return (float) str_replace([',', ' '], '', $val);
    }
}
