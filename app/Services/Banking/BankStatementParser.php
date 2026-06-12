<?php

declare(strict_types=1);

namespace App\Services\Banking;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BankStatementParser
{
    protected static array $categoryPatterns = [
        'Logistics' => ['delhivery', 'shiprocket', 'bluedart', 'ecom express', 'dtdc', 'xpressbees', 'shadowfax', 'courier', 'freight', 'india post', 'ekart'],
        'Payment Gateway' => ['razorpay', 'cashfree', 'payu', 'stripe', 'instamojo', 'payment aggregator', 'escrow', 'ccavenue', 'phonepe merchant'],
        'Ads' => ['meta', 'facebook', 'google ads', 'googleads', 'fb ads', 'instagram ad'],
        'Platform Fee' => ['shopify', 'woocommerce', 'marketplace', 'amazon seller', 'flipkart seller', 'myntra'],
        'Salary' => ['Salary', 'sal/', 'payroll', 'wages', 'employee'],
        'GST & Tax' => ['gst', 'tds', 'advance tax', 'income tax', 'professional tax', 'epf', 'esic', 'tax payment'],
        'Rent' => ['Rent', 'lease', 'office space'],
        'Utilities' => ['electricity', 'water', 'internet', 'broadband', 'airtel', 'jio', 'vodafone', 'bsnl'],
        'Bank Charges' => ['bank charge', 'sms charge', 'maintenance', 'annual fee', 'int.pd', 'int pd', 'interest'],
        'Transfer' => ['self transfer', 'fund transfer', 'own account', 'tpt-', 'tpt/'],
        'Refund' => ['Refund', 'reversal', 'chargeback', 'return'],
        'Sales' => ['settlement', 'payout', 'remittance', 'cod remit'],
        'Inventory' => ['raw material', 'packaging', 'supplier', 'manufacturer', 'stock', 'purchase order'],
        'Software' => ['saas', 'subscription', 'hosting', 'domain', 'aws', 'cloud', 'zoho', 'freshworks'],
    ];

    /**
     * Parse a bank statement CSV. Returns bank info + transactions.
     */
    public function parse(string $csvPath): array
    {
        $rawContent = file_get_contents($csvPath);
        $bankInfo = $this->detectBankFromContent($rawContent);

        $rows = $this->readCsv($csvPath);
        if (empty($rows)) return ['transactions' => [], 'bank' => $bankInfo, 'errors' => ['No data rows found']];

        $headers = array_keys($rows[0]);
        $format = $bankInfo['format'] ?? $this->detectFormat($headers);

        $transactions = [];
        foreach ($rows as $i => $row) {
            try {
                $parsed = $this->parseRow($row, $format);
                if ($parsed && $parsed['amount'] > 0) {
                    $parsed['category'] = $this->categorize($parsed['description'] ?? '');
                    $parsed['vendor'] = $this->extractVendor($parsed['description'] ?? '');
                    $transactions[] = $parsed;
                }
            } catch (\Throwable $e) {}
        }

        return [
            'transactions' => $transactions,
            'bank'         => $bankInfo,
            'format'       => $format,
            'total_rows'   => count($rows),
            'parsed'       => count($transactions),
        ];
    }

    /**
     * Detect bank name and account info from raw CSV content (header rows).
     */
    protected function detectBankFromContent(string $content): array
    {
        $info = ['name' => null, 'account_number' => null, 'ifsc' => null, 'format' => null];
        $upper = strtoupper($content);

        if (str_contains($upper, 'HDFC BANK')) {
            $info['name'] = 'HDFC Bank';
            $info['format'] = 'hdfc';
            if (preg_match('/Account No\s*:\s*(\d+)/i', $content, $m)) $info['account_number'] = $m[1];
            if (preg_match('/IFSC\s*:\s*([A-Z0-9]+)/i', $content, $m)) $info['ifsc'] = $m[1];
        } elseif (str_contains($upper, 'ICICI BANK') || str_contains($upper, 'ICICI')) {
            $info['name'] = 'ICICI Bank'; $info['format'] = 'icici';
        } elseif (str_contains($upper, 'STATE BANK') || str_contains($upper, 'SBI')) {
            $info['name'] = 'SBI'; $info['format'] = 'sbi';
        } elseif (str_contains($upper, 'AXIS BANK')) {
            $info['name'] = 'Axis Bank'; $info['format'] = 'axis';
        } elseif (str_contains($upper, 'KOTAK')) {
            $info['name'] = 'Kotak Bank'; $info['format'] = 'kotak';
        } elseif (str_contains($upper, 'RAZORPAY')) {
            $info['name'] = 'Razorpay'; $info['format'] = 'generic';
        } elseif (str_contains($upper, 'PAYTM') || str_contains($upper, 'PAYTM PAYMENTS')) {
            $info['name'] = 'Paytm'; $info['format'] = 'generic';
        }

        // Try to extract account number from filename or content
        if (!$info['account_number'] && preg_match('/(\d{10,18})/', $content, $m)) {
            // Only if it looks like an account number (not a transaction amount)
        }

        return $info;
    }

    /**
     * Extract vendor/counterparty name from NEFT/RTGS/UPI narration.
     */
    public function extractVendor(string $narration): string
    {
        $narration = trim($narration);
        if (empty($narration)) return 'Miscellaneous';

        // NEFT CR-{IFSC}-{VENDOR}-{ACCOUNT_HOLDER}-{REF}
        if (preg_match('/NEFT\s*(?:CR|DR)-[A-Z0-9]+-(.+?)-(HITAKSH|[A-Z\s]+ENTERPRISES|[A-Z\s]+LTD|[A-Z\s]+PVT)/i', $narration, $m)) {
            return $this->normalizeVendor(trim($m[1]));
        }

        // RTGS-{IFSC}-{VENDOR}-{REF}
        if (preg_match('/RTGS[\/\-][A-Z0-9]+-(.+?)-/i', $narration, $m)) {
            return $this->normalizeVendor(trim($m[1]));
        }

        // UPI-{NAME}-{VPA}
        if (preg_match('/UPI[-\/](.+?)[-\/].*@/i', $narration, $m)) {
            return $this->normalizeVendor(trim($m[1]));
        }

        // IMPS-{NAME}
        if (preg_match('/IMPS[-\/].*?[-\/](.+?)[-\/]/i', $narration, $m)) {
            return $this->normalizeVendor(trim($m[1]));
        }

        // TPT (self transfer)
        if (preg_match('/TPT/i', $narration)) return 'Self Transfer';

        // ATM
        if (preg_match('/ATM/i', $narration)) return 'ATM Withdrawal';

        return 'Miscellaneous';
    }

    /**
     * Normalize vendor names — consistent casing, remove noise.
     */
    protected function normalizeVendor(string $name): string
    {
        $name = trim($name);
        if (empty($name)) return 'Miscellaneous';

        // Known vendors — normalize to clean names
        $known = [
            'RAZORPAY PAYMENTS PVT LTD PAYMENT AGGREGATOR ESCR' => 'Razorpay',
            'RAZORPAY PAYMENTS PVT LTD' => 'Razorpay',
            'RAZORPAY' => 'Razorpay',
            'DELHIVERY  LIMITED' => 'Delhivery',
            'DELHIVERY LIMITED' => 'Delhivery',
            'DELHIVERY' => 'Delhivery',
            'WHITE WIZARD TECHNOLOGIES PRIVATE LIMITED' => 'White Wizard Technologies',
            'SHIPROCKET' => 'Shiprocket',
            'BLUEDART EXPRESS LIMITED' => 'BlueDart',
            'ECOM EXPRESS' => 'Ecom Express',
            'FLIPKART' => 'Flipkart',
            'AMAZON' => 'Amazon',
            'MYNTRA' => 'Myntra',
            'META PLATFORMS' => 'Meta Ads',
            'FACEBOOK' => 'Meta Ads',
            'GOOGLE' => 'Google',
        ];

        $upper = strtoupper($name);
        foreach ($known as $pattern => $normalized) {
            if (str_contains($upper, $pattern)) return $normalized;
        }

        // Title case for unknown vendors
        return Str::title(strtolower($name));
    }

    /**
     * Batch AI categorize + vendor extract.
     */
    public function aiCategorize(array $transactions): array
    {
        $descriptions = array_map(fn ($t) => $t['description'] ?? '', $transactions);
        $aiCategories = \App\Services\AI\AiBankCategorizer::categorize($descriptions);

        if (count($aiCategories) === count($transactions)) {
            foreach ($transactions as $i => &$t) {
                $t['category'] = $aiCategories[$i];
            }
        }
        return $transactions;
    }

    public function categorize(string $description): string
    {
        $desc = strtolower($description);
        foreach (self::$categoryPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($desc, $pattern)) return $category;
            }
        }
        if (str_contains($desc, 'UPI')) return 'UPI';
        return 'Miscellaneous';
    }

    protected function detectFormat(array $headers): string
    {
        $h = strtolower(implode('|', $headers));
        if (str_contains($h, 'narration') && str_contains($h, 'closing balance')) return 'hdfc';
        if (str_contains($h, 'transaction date') && str_contains($h, 'cr/dr')) return 'icici';
        if (str_contains($h, 'txn date')) return 'sbi';
        if (str_contains($h, 'tran date')) return 'axis';
        if (str_contains($h, 'particulars')) return 'kotak';
        return 'generic';
    }

    protected function parseRow(array $row, string $format): ?array
    {
        $row = array_map('trim', $row);
        return match ($format) {
            'hdfc' => $this->parseHdfc($row),
            'icici' => $this->parseIcici($row),
            'sbi' => $this->parseSbi($row),
            default => $this->parseGeneric($row),
        };
    }

    protected function parseHdfc(array $r): ?array
    {
        $date = $this->parseDate($r['Date'] ?? $r['date'] ?? '');
        if (!$date) return null;
        $debit = $this->toFloat($r['Withdrawal Amt.'] ?? $r['Debit Amount'] ?? 0);
        $credit = $this->toFloat($r['Deposit Amt.'] ?? $r['Credit Amount'] ?? 0);
        if ($debit <= 0 && $credit <= 0) return null;

        return [
            'date' => $date, 'type' => $credit > 0 ? 'credit' : 'debit',
            'amount' => $credit > 0 ? $credit : $debit,
            'balance' => $this->toFloat($r['Closing Balance'] ?? 0),
            'description' => $r['Narration'] ?? $r['narration'] ?? '',
            'reference' => $r['Chq./Ref.No.'] ?? $r['Chq/Ref Number'] ?? '',
        ];
    }

    protected function parseIcici(array $r): ?array
    {
        $date = $this->parseDate($r['Transaction Date'] ?? $r['Date'] ?? '');
        if (!$date) return null;
        $amount = $this->toFloat($r['Transaction Amount'] ?? $r['Amount'] ?? 0);
        $type = strtoupper($r['Cr/Dr'] ?? '') === 'CR' ? 'credit' : 'debit';
        return ['date' => $date, 'type' => $type, 'amount' => abs($amount),
            'balance' => $this->toFloat($r['Balance'] ?? 0),
            'description' => $r['Remarks'] ?? $r['Transaction Remarks'] ?? '', 'reference' => $r['Cheque Number'] ?? ''];
    }

    protected function parseSbi(array $r): ?array
    {
        $date = $this->parseDate($r['Txn Date'] ?? $r['Value Date'] ?? '');
        if (!$date) return null;
        $debit = $this->toFloat($r['Debit'] ?? 0); $credit = $this->toFloat($r['Credit'] ?? 0);
        if ($debit <= 0 && $credit <= 0) return null;
        return ['date' => $date, 'type' => $credit > 0 ? 'credit' : 'debit',
            'amount' => $credit > 0 ? $credit : $debit, 'balance' => $this->toFloat($r['Balance'] ?? 0),
            'description' => $r['Description'] ?? '', 'reference' => $r['Ref No./Cheque No.'] ?? ''];
    }

    protected function parseGeneric(array $r): ?array
    {
        $date = null; $amount = 0; $desc = ''; $balance = 0; $ref = ''; $type = 'debit';
        foreach ($r as $key => $val) {
            $k = strtolower($key);
            if (!$date && preg_match('/date/i', $k)) $date = $this->parseDate($val);
            if (preg_match('/debit|withdrawal|dr\b/i', $k) && $this->toFloat($val) > 0) { $amount = $this->toFloat($val); $type = 'debit'; }
            if (preg_match('/credit|deposit|cr\b/i', $k) && $this->toFloat($val) > 0) { $amount = $this->toFloat($val); $type = 'credit'; }
            if (preg_match('/narr|desc|particular|remark/i', $k)) $desc = $val;
            if (preg_match('/balance|closing/i', $k)) $balance = $this->toFloat($val);
            if (preg_match('/ref|chq|cheque|utr/i', $k)) $ref = $val;
        }
        if (!$date || $amount <= 0) return null;
        return ['date' => $date, 'type' => $type, 'amount' => $amount, 'balance' => $balance, 'description' => $desc, 'reference' => $ref];
    }

    protected function readCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $headers = null;
            while (($data = fgetcsv($handle)) !== false) {
                // Clean BOM
                if (!empty($data[0])) $data[0] = str_replace("\xEF\xBB\xBF", '', $data[0]);

                $filtered = array_filter($data, fn ($v) => trim($v ?? '') !== '' && $v !== '********' && $v !== '******************');
                if (count($filtered) < 3) continue;

                // Skip star rows and summary rows
                if (str_contains(implode('', $data), '****')) continue;
                if (str_contains(strtolower(implode('', $data)), 'statement summary')) break;

                if (!$headers) {
                    // Find the actual header row (contains Date and Narration/Description)
                    $joined = strtolower(implode('|', $data));
                    if (str_contains($joined, 'date') && (str_contains($joined, 'narration') || str_contains($joined, 'description') || str_contains($joined, 'particular') || str_contains($joined, 'withdrawal') || str_contains($joined, 'debit'))) {
                        $headers = array_map(fn ($h) => trim($h), $data);
                        continue;
                    }
                    continue; // Skip non-header rows
                }

                if (count($data) >= count($headers)) {
                    $row = array_combine($headers, array_slice($data, 0, count($headers)));
                    $rows[] = $row;
                }
            }
            fclose($handle);
        }
        return $rows;
    }

    protected function parseDate(string $val): ?string
    {
        $val = trim($val);
        if (empty($val) || str_contains($val, '****')) return null;
        try {
            foreach (['d/m/Y', 'd-m-Y', 'd/m/y', 'd-m-y', 'Y-m-d', 'm/d/Y', 'd M Y'] as $fmt) {
                $date = \DateTime::createFromFormat($fmt, $val);
                if ($date) return $date->format('Y-m-d');
            }
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable $e) { return null; }
    }

    protected function toFloat(mixed $val): float
    {
        return (float) str_replace([',', '"', '=', ' '], '', (string) ($val ?? '0'));
    }
}
