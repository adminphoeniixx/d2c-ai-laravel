<?php

declare(strict_types=1);

namespace App\Services\Banking;

use App\Services\AI\DoAiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Bank Statement Parser with full step-by-step logging.
 * Every parse attempt is traced in $this->log so the controller
 * can write it to banking_upload_logs for debugging.
 */
class BankStatementParser
{
    /** Accumulated trace log — read by controller after parseFile() */
    public array $trace = [];

    protected static array $categoryPatterns = [
        'Logistics'       => ['delhivery','shiprocket','bluedart','dtdc','ecom express','xpressbees','shadowfax','amazon logistics','ekart'],
        'Platform Fee'    => ['shopify','amazon','flipkart','meesho','myntra','nykaa','ajio','snapdeal','magento','woocommerce'],
        'Payment Gateway' => ['razorpay','paytm','phonepe','googlepay','cashfree','ccavenue','stripe','payu','billdesk'],
        'Advertising'     => ['google ads','meta','facebook','instagram','youtube','twitter','linkedin','snapchat','tiktok'],
        'GST & Tax'       => ['gst','tds','income tax','advance tax','customs','cgst','sgst','igst'],
        'Salary'          => ['salary','payroll','wages','neft sal','neft salary'],
        'Rent'            => ['rent','lease','property'],
        'Utilities'       => ['electricity','water','gas','broadband','internet','mobile','telephone','jio','airtel','bsnl','vodafone'],
        'Banking'         => ['bank charges','ecs','nach','emi','loan','interest','service charge','atm'],
    ];

    private function step(string $step, array $data = []): void
    {
        $entry = array_merge(['step' => $step, 'ts' => now()->toISOString()], $data);
        $this->trace[] = $entry;
        Log::info("BankParser [$step]", $data);
    }

    /* ─────────────────────────────────────────────────────────────
     *  Public entry point
     * ─────────────────────────────────────────────────────────────*/

    public function parseFile(string $filePath, string $extension, ?string $password = null): array
    {
        $ext = strtolower($extension);
        $this->trace = [];

        $this->step('start', ['ext' => $ext, 'size' => filesize($filePath)]);

        // ── CSV / TXT ──────────────────────────────────────────────
        if (in_array($ext, ['csv', 'txt'])) {
            $this->step('mode', ['method' => 'rule-based CSV']);

            $rawContent = file_get_contents($filePath);
            $this->step('file_read', ['bytes' => strlen($rawContent), 'preview' => substr($rawContent, 0, 300)]);

            $bankInfo = $this->detectBankFromContent($rawContent);
            $this->step('bank_detect', $bankInfo);

            $rows = $this->readCsv($filePath);
            $this->step('csv_rows', ['count' => count($rows), 'headers' => !empty($rows) ? array_keys($rows[0]) : []]);

            if (empty($rows)) {
                $this->step('fail', ['reason' => 'No rows found after header detection']);
                return ['transactions' => [], 'bank' => $bankInfo, 'errors' => ['No data rows found. Check the file has a valid header row with Date and Narration/Description columns.'], 'trace' => $this->trace];
            }

            $format = $bankInfo['format'] ?? $this->detectFormat(array_keys($rows[0]));
            $this->step('format_detect', ['format' => $format]);

            $transactions = [];
            $skipped = 0;
            foreach ($rows as $i => $row) {
                try {
                    $parsed = $this->parseRow($row, $format);
                    if ($parsed && $parsed['amount'] > 0) {
                        $parsed['category'] = $this->categorize($parsed['description'] ?? '');
                        $parsed['vendor']   = $this->extractVendor($parsed['description'] ?? '');
                        $transactions[] = $parsed;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $skipped++;
                }
            }

            $this->step('parse_done', [
                'parsed'  => count($transactions),
                'skipped' => $skipped,
                'sample'  => array_slice($transactions, 0, 3),
            ]);

            return ['transactions' => $transactions, 'bank' => $bankInfo, 'trace' => $this->trace];
        }

        // ── PDF ────────────────────────────────────────────────────
        if ($ext === 'pdf') {
            $this->step('mode', ['method' => 'PDF → pdftotext → AI']);

            $textResult = $this->extractPdfText($filePath, $password);

            if (is_array($textResult)) {
                $this->step('fail', $textResult);
                return array_merge($textResult, ['trace' => $this->trace]);
            }

            $this->step('pdf_text', ['chars' => strlen($textResult), 'preview' => substr($textResult, 0, 500)]);

            if (empty(trim($textResult))) {
                $this->step('fail', ['reason' => 'pdftotext returned empty — scanned/image PDF']);
                return ['transactions' => [], 'bank' => [], 'errors' => ['This PDF appears to be scanned (image-based). Please export your statement as CSV from your bank portal.'], 'trace' => $this->trace];
            }

            $bankInfo = $this->detectBankFromContent($textResult);
            $this->step('bank_detect', $bankInfo);

            // Try rule-based text parsing first — fast, no AI calls, no timeouts
            $transactions = $this->parsePdfText($textResult);
            $this->step('rule_based_result', ['count' => count($transactions)]);

            // Only use AI if rule-based found nothing
            if (empty($transactions)) {
                $this->step('rule_based_empty_trying_ai', []);
                $transactions = $this->extractWithAI($textResult, $bankInfo);
            }

            foreach ($transactions as &$t) {
                if (empty($t['category'])) $t['category'] = $this->categorize($t['description'] ?? '');
                if (empty($t['vendor']))   $t['vendor']   = $this->extractVendor($t['description'] ?? '');
            }
            unset($t);

            $this->step('result', ['transactions' => count($transactions), 'sample' => array_slice($transactions, 0, 3)]);

            return ['transactions' => $transactions, 'bank' => $bankInfo, 'trace' => $this->trace];
        }

        // ── XLSX / XLS ─────────────────────────────────────────────
        if (in_array($ext, ['xlsx', 'xls'])) {
            $this->step('mode', ['method' => 'XLSX → text → AI with rule-based fallback']);

            $rawText = $this->extractXlsxText($filePath);
            $this->step('xlsx_text', ['chars' => strlen($rawText), 'preview' => substr($rawText, 0, 500)]);

            if (empty(trim($rawText))) {
                $this->step('fail', ['reason' => 'XLSX text extraction returned empty']);
                return ['transactions' => [], 'bank' => [], 'errors' => ['Could not read Excel file.'], 'trace' => $this->trace];
            }

            $bankInfo = $this->detectBankFromContent($rawText);
            $this->step('bank_detect', $bankInfo);

            $transactions = $this->extractWithAI($rawText, $bankInfo);

            if (empty($transactions)) {
                $this->step('ai_empty_trying_fallback', []);
                $tmpCsv = tempnam(sys_get_temp_dir(), 'bank_') . '.csv';
                $this->xlsxToCsv($filePath, $tmpCsv);
                $fallback = $this->parseCSV($tmpCsv);
                @unlink($tmpCsv);
                $transactions = $fallback['transactions'] ?? [];
                $this->step('fallback_result', ['transactions' => count($transactions)]);
            }

            foreach ($transactions as &$t) {
                if (empty($t['category'])) $t['category'] = $this->categorize($t['description'] ?? '');
                if (empty($t['vendor']))   $t['vendor']   = $this->extractVendor($t['description'] ?? '');
            }
            unset($t);

            $this->step('result', ['transactions' => count($transactions), 'sample' => array_slice($transactions, 0, 3)]);

            return ['transactions' => $transactions, 'bank' => $bankInfo, 'trace' => $this->trace];
        }

        $this->step('fail', ['reason' => "Unsupported extension: $ext"]);
        return ['transactions' => [], 'bank' => [], 'errors' => ["Unsupported file type: $ext"], 'trace' => $this->trace];
    }

    /* Legacy entry point */
    public function parse(string $csvPath): array
    {
        return $this->parseFile($csvPath, 'csv');
    }

    /* ─────────────────────────────────────────────────────────────
     *  AI extraction
     * ─────────────────────────────────────────────────────────────*/

    protected function extractWithAI(string $rawText, array $bankInfo): array
    {
        $bankHint = $bankInfo['name'] ? "This is a {$bankInfo['name']} bank statement." : 'This is an Indian bank statement.';

        $system = "You are a bank statement parser. Extract ALL transactions from the statement text. Return ONLY a valid JSON array, no explanation, no markdown fences.\nFormat: [{\"date\":\"YYYY-MM-DD\",\"type\":\"debit\",\"amount\":123.45,\"description\":\"...\",\"balance\":0,\"reference\":\"\"}]\nRules:\n- Convert ANY date format (DD Mon YYYY, DD/MM/YYYY, DD-MM-YYYY, etc.) to YYYY-MM-DD\n- Amount is always a positive number\n- type is \"debit\" for withdrawals/charges, \"credit\" for deposits/receipts\n- Skip header rows, opening balance, closing balance, summary rows\n- Description: use the full narration text\n- If a transaction description spans multiple lines, join them\n- Return [] if no transactions found";

        $ai = app(DoAiService::class);

        // Try with full text using heavy model first (large context, reliable)
        // Heavy model (Nemotron 120B) handles large bank statements without
        // the silent empty-response issue that DeepSeek Flash has.
        $fullText = mb_substr($rawText, 0, 30000); // Heavy model supports much more
        $this->step('ai_call', ['model' => 'heavy', 'chars' => strlen($fullText), 'bank' => $bankHint]);

        try {
            $response = $ai->heavy($system, "{$bankHint}\n\n{$fullText}", 0.0, 8000);
            $this->step('ai_response', ['length' => strlen($response ?? ''), 'preview' => substr($response ?? '', 0, 300)]);

            $transactions = $this->parseAiResponse($response);

            if (!empty($transactions)) {
                $this->step('ai_extracted', ['count' => count($transactions), 'model' => 'heavy']);
                return $transactions;
            }

            $this->step('ai_heavy_empty', ['trying' => 'chunked light model']);
        } catch (\Throwable $e) {
            $this->step('ai_heavy_error', ['error' => $e->getMessage()]);
        }

        // Fallback: chunked light model (4000 chars each)
        $this->step('ai_fallback_chunks', ['total_chars' => strlen($rawText)]);
        $chunks = str_split($rawText, 4000);
        $allTransactions = [];

        foreach ($chunks as $idx => $chunk) {
            try {
                $this->step('ai_light_chunk', ['chunk' => $idx + 1, 'of' => count($chunks)]);
                $r = $ai->light($system, "{$bankHint}\n\n{$chunk}", 0.0, 3000);
                $this->step('ai_light_response', ['chunk' => $idx + 1, 'length' => strlen($r ?? '')]);
                $txns = $this->parseAiResponse($r);
                $allTransactions = array_merge($allTransactions, $txns);
            } catch (\Throwable $e) {
                $this->step('ai_light_chunk_error', ['chunk' => $idx + 1, 'error' => $e->getMessage()]);
            }
        }

        // Deduplicate
        $seen = [];
        $unique = [];
        foreach ($allTransactions as $t) {
            $key = $t['date'] . '|' . $t['amount'] . '|' . substr($t['description'], 0, 30);
            if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $t; }
        }

        $this->step('ai_extracted', ['count' => count($unique), 'model' => 'light_chunked']);
        return $unique;
    }

    private function parseAiResponse(?string $response): array
    {
        if (empty($response)) return [];

        $json = preg_replace('/^```(?:json)?\s*/m', '', trim($response));
        $json = preg_replace('/\s*```$/m', '', $json);
        $json = trim($json);

        if (!str_starts_with($json, '[')) {
            preg_match('/\[.*\]/s', $json, $matches);
            $json = $matches[0] ?? '[]';
        }

        $data = json_decode($json, true);
        if (!is_array($data)) return [];

        $transactions = [];
        foreach ($data as $item) {
            if (empty($item['date']) || empty($item['amount'])) continue;
            $date = $this->parseDate((string) $item['date']);
            if (!$date) continue;
            $amount = (float) $item['amount'];
            if ($amount <= 0) continue;
            $transactions[] = [
                'date'        => $date,
                'type'        => strtolower($item['type'] ?? 'debit') === 'credit' ? 'credit' : 'debit',
                'amount'      => $amount,
                'balance'     => (float) ($item['balance'] ?? 0),
                'description' => (string) ($item['description'] ?? ''),
                'reference'   => (string) ($item['reference'] ?? ''),
            ];
        }
        return $transactions;
    }

    /* ─────────────────────────────────────────────────────────────
     *  File text extraction helpers
     * ─────────────────────────────────────────────────────────────*/

    protected function extractPdfText(string $filePath, ?string $password = null): string|array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'bank_pdf_') . '.txt';
        $cmd     = ['pdftotext', '-layout'];
        if ($password) { $cmd[] = '-upw'; $cmd[] = escapeshellarg($password); }
        $cmd[] = escapeshellarg($filePath);
        $cmd[] = escapeshellarg($tmpFile);

        $command = implode(' ', $cmd);
        $this->step('pdftotext_cmd', ['cmd' => $command]);

        exec($command . ' 2>&1', $output, $code);
        $outputStr = implode(' ', $output);

        $this->step('pdftotext_result', ['exit_code' => $code, 'output' => $outputStr]);

        if ($code !== 0) {
            @unlink($tmpFile);
            $err = strtolower($outputStr);
            if (str_contains($err, 'password') || str_contains($err, 'encrypted')) {
                return ['transactions' => [], 'bank' => [], 'errors' => ['This PDF is password-protected. Please enter the password and try again.'], 'needs_password' => true];
            }
            return ['transactions' => [], 'bank' => [], 'errors' => ["pdftotext failed (exit $code): $outputStr"]];
        }

        $text = file_get_contents($tmpFile);
        @unlink($tmpFile);
        return $text;
    }

    protected function extractXlsxText(string $filePath): string
    {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows  = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $lines = [];
            foreach ($rows as $row) {
                $cells   = array_map(fn ($v) => $v ?? '', $row);
                $lines[] = implode("\t", $cells);
            }
            return implode("\n", $lines);
        } catch (\Throwable $e) {
            $this->step('xlsx_error', ['error' => $e->getMessage()]);
            return '';
        }
    }

    protected function xlsxToCsv(string $filePath, string $outputPath): void
    {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows  = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            $fp = fopen($outputPath, 'w');
            foreach ($rows as $row) { fputcsv($fp, array_map(fn ($v) => $v ?? '', $row), ',', '"', '\\'); }
            fclose($fp);
        } catch (\Throwable $e) {
            $this->step('xlsx_to_csv_error', ['error' => $e->getMessage()]);
        }
    }

    /* ─────────────────────────────────────────────────────────────
     *  Rule-based PDF text parsing
     * ─────────────────────────────────────────────────────────────*/

    /**
     * Parse fixed-width PDF text from pdftotext -layout.
     * Handles Kotak (DD Mon YYYY), HDFC/ICICI (DD/MM/YYYY) and generic layouts.
     * Each transaction spans 1-2 lines — continuation lines are appended.
     */
    protected function parsePdfText(string $text): array
    {
        $lines        = explode("\n", $text);
        $transactions = [];
        $lastIdx      = -1;

        foreach ($lines as $line) {
            if (!trim($line)) continue;

            // Skip page headers, footers, account info lines
            if (preg_match('/page \d+ of \d+|statement generated|current account transactions|^\s*#\s+date\s+desc/i', $line)) continue;
            if (preg_match('/^\s*(account no\.|crn |branch |micr |ifsc|account type|account status|nominee|currency)/i', trim($line))) continue;

            // ── Pattern 1: Kotak style — serial  DD Mon YYYY  description  ref  amounts ──
            // The key insight: Kotak uses fixed-width columns with MANY spaces between columns.
            // Line format: "[serial]  DD Mon YYYY  Description  [Ref]  [Withdrawal]  [Deposit]  Balance"
            // All numbers at the end separated by large whitespace gaps.
            if (preg_match('/^\s*\d*\s{0,8}(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4})\s{2,}/i', $line, $dateMatch)) {
                $date = $this->parseDate(trim($dateMatch[1]));
                if (!$date) continue;

                // Extract all numbers from the line (they are the amounts)
                $afterDate = substr($line, strpos($line, $dateMatch[1]) + strlen($dateMatch[1]));

                // Find all currency amounts (numbers with commas and 2 decimal places)
                preg_match_all('/([\d,]+\.\d{2})/', $afterDate, $amounts);
                $nums = array_map([$this, 'toFloat'], $amounts[1] ?? []);

                if (empty($nums)) continue;

                // Balance is always the last number
                $balance = array_pop($nums);

                // Determine debit/credit from remaining numbers
                // If 2 remaining: first=withdrawal, second=deposit (one will be 0 or missing)
                // If 1 remaining: could be either
                $col1 = $nums[0] ?? 0; // withdrawal
                $col2 = $nums[1] ?? 0; // deposit

                if ($col2 > 0) { $type = 'credit'; $amount = $col2; }
                elseif ($col1 > 0) { $type = 'debit'; $amount = $col1; }
                else continue;

                // Extract description — everything between date and first number
                preg_match('/^\s*\d*\s{0,8}\d{1,2}\s+\w+\s+\d{4}\s{2,}(.*?)\s{3,}[\d,]+\.\d{2}/i', $line, $descMatch);
                $descRaw = trim($descMatch[1] ?? '');

                // Try to split description from reference number
                $desc = $descRaw; $ref = '';
                if (preg_match('/^(.*?)\s{2,}([A-Z0-9\/\-]{4,30})\s*$/i', $descRaw, $dr)) {
                    $desc = trim($dr[1]);
                    $ref  = trim($dr[2]);
                }

                $transactions[] = compact('date', 'type', 'amount', 'balance') + [
                    'description' => $desc, 'reference' => $ref,
                ];
                $lastIdx = count($transactions) - 1;
                continue;
            }

            // ── Pattern 2: DD/MM/YYYY style (HDFC, ICICI PDF exports) ──
            if (preg_match('/^\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\s{2,}/i', $line, $dateMatch)) {
                $date = $this->parseDate(trim($dateMatch[1]));
                if (!$date) continue;

                $afterDate = substr($line, strpos($line, $dateMatch[1]) + strlen($dateMatch[1]));
                preg_match_all('/([\d,]+\.\d{2})/', $afterDate, $amounts);
                $nums = array_map([$this, 'toFloat'], $amounts[1] ?? []);

                if (empty($nums)) continue;
                $balance = array_pop($nums);
                $col1 = $nums[0] ?? 0;
                $col2 = $nums[1] ?? 0;

                if ($col2 > 0) { $type = 'credit'; $amount = $col2; }
                elseif ($col1 > 0) { $type = 'debit'; $amount = $col1; }
                else continue;

                preg_match('/^\s*\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\s{2,}(.*?)\s{3,}[\d,]+\.\d{2}/i', $line, $descMatch);
                $desc = trim($descMatch[1] ?? '');

                $transactions[] = [
                    'date' => $date, 'type' => $type, 'amount' => $amount,
                    'balance' => $balance, 'description' => $desc, 'reference' => '',
                ];
                $lastIdx = count($transactions) - 1;
                continue;
            }

            // ── Continuation line — append to last transaction description ──
            if ($lastIdx >= 0 && preg_match('/^\s{10,}(\S.+?)\s*$/', $line, $m)) {
                $cont = trim($m[1]);
                if (!preg_match('/page \d+|phoeniixx|account no|^[A-Z\s]+$/', $cont)) {
                    $transactions[$lastIdx]['description'] .= ' ' . $cont;
                }
            }
        }

        $this->step('pdf_rule_based', ['count' => count($transactions)]);
        return $transactions;
    }

    /* ─────────────────────────────────────────────────────────────
     *  Rule-based CSV parsing
     * ─────────────────────────────────────────────────────────────*/

    protected function parseCSV(string $csvPath): array
    {
        $rawContent = file_get_contents($csvPath);
        $bankInfo   = $this->detectBankFromContent($rawContent);
        $rows       = $this->readCsv($csvPath);

        if (empty($rows)) return ['transactions' => [], 'bank' => $bankInfo];

        $format      = $bankInfo['format'] ?? $this->detectFormat(array_keys($rows[0]));
        $transactions = [];

        foreach ($rows as $row) {
            try {
                $parsed = $this->parseRow($row, $format);
                if ($parsed && $parsed['amount'] > 0) $transactions[] = $parsed;
            } catch (\Throwable $e) {}
        }

        return ['transactions' => $transactions, 'bank' => $bankInfo];
    }

    /* ─────────────────────────────────────────────────────────────
     *  AI batch categorization (called externally — kept for compat)
     * ─────────────────────────────────────────────────────────────*/

    public function aiCategorize(array $transactions): array
    {
        if (empty($transactions)) return $transactions;

        $ai     = app(DoAiService::class);
        $items  = array_map(fn ($t, $i) => ['i' => $i, 'd' => substr($t['description'] ?? '', 0, 80)], $transactions, array_keys($transactions));
        $chunks = array_chunk($items, 50);

        foreach ($chunks as $chunk) {
            $prompt = "Categorize these bank transactions. Return ONLY [{\"i\":index,\"category\":\"Category\",\"vendor\":\"Vendor\"}].\n" .
                      "Categories: " . implode(', ', array_keys(self::$categoryPatterns)) . ", Other\n" .
                      json_encode($chunk);
            try {
                $r = $ai->light("You are a bank transaction categorizer. Return only valid JSON.", $prompt, 0.0, 2000);
                if (!$r) continue;
                $r    = preg_replace('/^```(?:json)?\s*/m', '', trim($r));
                $r    = preg_replace('/\s*```$/m', '', $r);
                $data = json_decode(trim($r), true);
                if (!is_array($data)) continue;
                foreach ($data as $item) {
                    $idx = $item['i'] ?? null;
                    if ($idx !== null && isset($transactions[$idx])) {
                        if (!empty($item['category'])) $transactions[$idx]['category'] = $item['category'];
                        if (!empty($item['vendor']))   $transactions[$idx]['vendor']   = $item['vendor'];
                    }
                }
            } catch (\Throwable $e) {}
        }

        return $transactions;
    }

    /* ─────────────────────────────────────────────────────────────
     *  Detection and categorization
     * ─────────────────────────────────────────────────────────────*/

    protected function detectBankFromContent(string $content): array
    {
        $info  = ['name' => null, 'account_number' => null, 'ifsc' => null, 'format' => null];
        $upper = strtoupper($content);

        if      (str_contains($upper, 'HDFC BANK'))                           { $info['name'] = 'HDFC Bank';       $info['format'] = 'hdfc'; }
        elseif  (str_contains($upper, 'ICICI BANK') || str_contains($upper, 'ICICI')) { $info['name'] = 'ICICI Bank'; $info['format'] = 'icici'; }
        elseif  (str_contains($upper, 'STATE BANK') || str_contains($upper, 'SBI'))   { $info['name'] = 'SBI';       $info['format'] = 'sbi'; }
        elseif  (str_contains($upper, 'AXIS BANK'))                           { $info['name'] = 'Axis Bank';       $info['format'] = 'axis'; }
        elseif  (str_contains($upper, 'KOTAK'))                               { $info['name'] = 'Kotak Bank';      $info['format'] = 'kotak'; }
        elseif  (str_contains($upper, 'YES BANK'))                            { $info['name'] = 'Yes Bank';        $info['format'] = 'generic'; }
        elseif  (str_contains($upper, 'INDUSIND'))                            { $info['name'] = 'IndusInd Bank';   $info['format'] = 'generic'; }
        elseif  (str_contains($upper, 'IDFC'))                                { $info['name'] = 'IDFC Bank';       $info['format'] = 'generic'; }
        elseif  (str_contains($upper, 'FEDERAL BANK'))                        { $info['name'] = 'Federal Bank';    $info['format'] = 'generic'; }
        elseif  (str_contains($upper, 'BANK OF BARODA') || str_contains($upper, 'BOB')) { $info['name'] = 'Bank of Baroda'; $info['format'] = 'generic'; }
        elseif  (str_contains($upper, 'CANARA'))                              { $info['name'] = 'Canara Bank';     $info['format'] = 'generic'; }
        elseif  (str_contains($upper, 'UNION BANK'))                          { $info['name'] = 'Union Bank';      $info['format'] = 'generic'; }
        elseif  (str_contains($upper, 'RAZORPAY'))                            { $info['name'] = 'Razorpay';        $info['format'] = 'generic'; }
        elseif  (str_contains($upper, 'PAYTM'))                               { $info['name'] = 'Paytm';           $info['format'] = 'generic'; }

        if (preg_match('/Account\s*No[.:]?\s*([X\d]+)/i', $content, $m)) $info['account_number'] = $m[1];
        if (preg_match('/IFSC\s*[:\-]?\s*([A-Z]{4}0[A-Z0-9]{6})/i',    $content, $m)) $info['ifsc'] = $m[1];

        return $info;
    }

    public function extractVendor(string $narration): string
    {
        $narration = trim($narration);
        if (empty($narration)) return 'Miscellaneous';

        if (preg_match('/UPI[- \/]+([A-Za-z0-9\s]+?)(?:\/|@|-|\d{10}|$)/i', $narration, $m))
            return $this->normalizeVendor(trim($m[1]));
        if (preg_match('/(?:NEFT|RTGS|IMPS)[- \/]+(.+?)(?:\s*-\s*[A-Z0-9]{15,}|$)/i', $narration, $m))
            return $this->normalizeVendor(trim($m[1]));
        if (preg_match('/(?:ACH|NACH|ECS)[- ]+(.+?)(?:\s*-|\s+\d|$)/i', $narration, $m))
            return $this->normalizeVendor(trim($m[1]));

        $known = ['Razorpay','Paytm','PhonePe','Google Pay','Amazon','Flipkart','Swiggy','Zomato','Delhivery','Shiprocket','Shopify','Meta','Google'];
        foreach ($known as $vendor) {
            if (stripos($narration, $vendor) !== false) return $vendor;
        }

        $words = preg_split('/[\s\/\-_]+/', $narration);
        $skip  = ['NEFT','RTGS','IMPS','UPI','ACH','NACH','CR','DR','REF','UTR','BY','TO','FROM','THE','AND'];
        $name  = '';
        foreach ($words as $w) {
            if (strlen($w) > 2 && !in_array(strtoupper($w), $skip) && !is_numeric($w)) {
                $name .= ' ' . $w;
                if (str_word_count($name) >= 3) break;
            }
        }
        return trim($name) ?: 'Miscellaneous';
    }

    protected function normalizeVendor(string $name): string
    {
        $map = [
            'RAZORPAY PAYMENTS' => 'Razorpay', 'RAZORPAY'  => 'Razorpay',
            'DELHIVERY'         => 'Delhivery', 'SHIPROCKET' => 'Shiprocket',
            'SHOPIFY'           => 'Shopify',   'AMAZON'     => 'Amazon',
            'FLIPKART'          => 'Flipkart',  'META'       => 'Meta',
            'GOOGLE'            => 'Google',    'PAYTM'      => 'Paytm',
            'PHONEPE'           => 'PhonePe',
        ];
        $upper = strtoupper($name);
        foreach ($map as $k => $v) { if (str_contains($upper, $k)) return $v; }
        return ucwords(strtolower($name));
    }

    public function categorize(string $description): string
    {
        $desc = strtolower($description);
        foreach (self::$categoryPatterns as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($desc, $keyword)) return $category;
            }
        }
        return 'Other';
    }

    /* ─────────────────────────────────────────────────────────────
     *  Rule-based CSV internals
     * ─────────────────────────────────────────────────────────────*/

    protected function detectFormat(array $headers): string
    {
        $h = strtolower(implode('|', $headers));
        if (str_contains($h, 'narration') && str_contains($h, 'closing balance')) return 'hdfc';
        if (str_contains($h, 'transaction date') && str_contains($h, 'cr/dr'))    return 'icici';
        if (str_contains($h, 'txn date'))                                          return 'sbi';
        if (str_contains($h, 'particulars'))                                       return 'kotak';
        return 'generic';
    }

    protected function parseRow(array $row, string $format): ?array
    {
        $row = array_map('trim', $row);
        return match ($format) {
            'hdfc'  => $this->parseHdfc($row),
            'icici' => $this->parseIcici($row),
            'sbi'   => $this->parseSbi($row),
            default => $this->parseGeneric($row),
        };
    }

    protected function parseHdfc(array $r): ?array
    {
        $date   = $this->parseDate($r['Date'] ?? $r['date'] ?? '');
        if (!$date) return null;
        $debit  = $this->toFloat($r['Withdrawal Amt.'] ?? $r['Debit Amount'] ?? 0);
        $credit = $this->toFloat($r['Deposit Amt.']    ?? $r['Credit Amount'] ?? 0);
        if ($debit <= 0 && $credit <= 0) return null;
        return ['date' => $date, 'type' => $credit > 0 ? 'credit' : 'debit',
            'amount' => $credit > 0 ? $credit : $debit, 'balance' => $this->toFloat($r['Closing Balance'] ?? 0),
            'description' => $r['Narration'] ?? '', 'reference' => $r['Chq./Ref.No.'] ?? ''];
    }

    protected function parseIcici(array $r): ?array
    {
        $date   = $this->parseDate($r['Transaction Date'] ?? $r['Date'] ?? '');
        if (!$date) return null;
        $amount = $this->toFloat($r['Transaction Amount'] ?? $r['Amount'] ?? 0);
        $type   = strtoupper($r['Cr/Dr'] ?? '') === 'CR' ? 'credit' : 'debit';
        return ['date' => $date, 'type' => $type, 'amount' => abs($amount),
            'balance' => $this->toFloat($r['Balance'] ?? 0),
            'description' => $r['Remarks'] ?? '', 'reference' => $r['Cheque Number'] ?? ''];
    }

    protected function parseSbi(array $r): ?array
    {
        $date  = $this->parseDate($r['Txn Date'] ?? $r['Value Date'] ?? '');
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
            if (preg_match('/credit|deposit|cr\b/i',   $k) && $this->toFloat($val) > 0) { $amount = $this->toFloat($val); $type = 'credit'; }
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
            while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if (!empty($data[0])) $data[0] = str_replace("\xEF\xBB\xBF", '', $data[0]);
                $filtered = array_filter($data, fn ($v) => trim($v ?? '') !== '' && $v !== '********');
                if (count($filtered) < 3) continue;
                if (str_contains(implode('', $data), '****')) continue;
                if (str_contains(strtolower(implode('', $data)), 'statement summary')) break;

                if (!$headers) {
                    $joined = strtolower(implode('|', $data));
                    if (str_contains($joined, 'date') && (
                        str_contains($joined, 'narration') || str_contains($joined, 'description') ||
                        str_contains($joined, 'particular') || str_contains($joined, 'withdrawal') ||
                        str_contains($joined, 'debit') || str_contains($joined, 'amount')
                    )) {
                        $headers = array_map('trim', $data);
                        continue;
                    }
                    continue;
                }
                if (count($data) >= count($headers)) {
                    $rows[] = array_combine($headers, array_slice($data, 0, count($headers)));
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

        if (is_numeric($val)) {
            $num = (int) $val;
            if ($num > 40000 && $num < 55000) {
                try {
                    $date = Carbon::createFromDate(1900, 1, 1)->addDays($num - 2);
                    if ($date->year >= 2000 && $date->year <= 2100) return $date->format('Y-m-d');
                } catch (\Throwable $e) {}
            }
            return null;
        }

        try {
            $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd M Y', 'd-M-Y', 'd/M/Y', 'dmY', 'j/n/Y', 'j-n-Y'];
            foreach ($formats as $fmt) {
                $date = \DateTime::createFromFormat($fmt, $val);
                if (!$date) continue;
                if ($date->format($fmt) !== $val) continue;
                $year = (int) $date->format('Y');
                if ($year < 2000 || $year > 2100) continue;
                return $date->format('Y-m-d');
            }

            foreach (['d/m/y', 'd-m-y'] as $fmt) {
                $date = \DateTime::createFromFormat($fmt, $val);
                if (!$date) continue;
                if ($date->format($fmt) !== $val) continue;
                $year = (int) $date->format('Y');
                if ($year < 100) $year += 2000;
                if ($year < 2000 || $year > 2100) continue;
                return sprintf('%04d-%02d-%02d', $year, (int) $date->format('m'), (int) $date->format('d'));
            }

            if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $val, $m)) {
                $year = (int) $m[1];
                if ($year >= 2000 && $year <= 2100)
                    return sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[3]);
            }

            if (preg_match('/[A-Za-z]{3}/', $val)) {
                $parsed = Carbon::parse($val);
                if ($parsed->year >= 2000 && $parsed->year <= 2100) return $parsed->format('Y-m-d');
            }

            return null;
        } catch (\Throwable $e) { return null; }
    }

    protected function toFloat(mixed $val): float
    {
        return (float) str_replace([',', '"', '=', ' ', "\xc2\xa0"], '', (string) ($val ?? '0'));
    }
}
