<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AdInvoice;
use App\Models\Tenant\AdSpendManual;
use App\Models\Tenant\BankAccount;
use App\Models\Tenant\BankTransaction;
use App\Models\Tenant\DeliveryPartner;
use App\Models\Tenant\LogisticsInvoice;
use App\Services\Ads\AdSpendCsvParser;
use App\Services\Banking\BankStatementParser;
use App\Services\BunnyCDN;
use App\Services\Logistics\DelhiveryCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmartUploadController extends Controller
{
    /**
     * Smart upload — auto-detect file type and process accordingly.
     * Accepts PDFs (invoices) and CSVs (transaction data).
     */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'hint' => ['nullable', 'string'], // optional hint: ads, logistics, banking
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $hint = $request->input('hint', '');

        try {
            if ($ext === 'pdf') {
                return $this->processPdf($file, $hint);
            } elseif (in_array($ext, ['csv', 'txt', 'xls', 'xlsx'])) {
                return $this->processCsv($file, $hint);
            }

            return back()->with('error', 'Unsupported file type. Upload PDF or CSV files.');
        } catch (\Throwable $e) {
            Log::error('Smart upload failed', ['error' => $e->getMessage(), 'file' => $file->getClientOriginalName()]);
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    protected function processPdf($file, string $hint): RedirectResponse
    {
        $text = $this->extractPdfText($file->getRealPath());
        $lower = strtolower($text);

        // Upload to CDN
        $pdfUrl = null;
        try {
            $path = 'invoices/' . date('Y-m') . '/' . Str::random(8) . '.pdf';
            $cdn = new BunnyCDN();
            $cdn->upload($path, file_get_contents($file->getRealPath()));
            $pdfUrl = config('services.bunny.cdn_url') . '/' . $path;
        } catch (\Throwable $e) {
            $pdfUrl = $file->store('invoices', 'public');
        }

        // Auto-detect type
        if ($hint === 'ads' || str_contains($lower, 'meta ads') || str_contains($lower, 'facebook india')
            || (str_contains($lower, 'billing report') && str_contains($lower, 'transaction id'))) {
            return $this->processMetaAdsPdf($text, $pdfUrl);
        }

        if ($hint === 'ads' || str_contains($lower, 'google ads') || str_contains($lower, 'google asia pacific')) {
            return $this->processGoogleAdsPdf($text, $pdfUrl);
        }

        if (str_contains($lower, 'delhivery') || str_contains($lower, 'aapcs9575e')) {
            return $this->processDelhiveryPdf($text, $pdfUrl);
        }

        if (str_contains($lower, 'razorpay')) {
            return $this->processGenericInvoicePdf($text, $pdfUrl, 'platform_fee', 'Razorpay');
        }

        return $this->processGenericInvoicePdf($text, $pdfUrl, 'other', 'Invoice');
    }

    protected function processMetaAdsPdf(string $text, ?string $pdfUrl): RedirectResponse
    {
        // Extract period
        $periodFrom = $periodTo = $invoiceDate = now()->format('Y-m-d');
        if (preg_match('/Billing\s*Report:\s*(\d{1,2}\/\d{1,2}\/\d{4})\s*-\s*(\d{1,2}\/\d{1,2}\/\d{4})/i', $text, $m)) {
            $periodFrom = \Carbon\Carbon::createFromFormat('n/j/Y', $m[1])->format('Y-m-d');
            $periodTo = \Carbon\Carbon::createFromFormat('n/j/Y', $m[2])->format('Y-m-d');
            $invoiceDate = $periodTo;
        }

        $invoiceNumber = 'META-' . date('Ymd');
        if (preg_match('/Account:\s*(\d+)/i', $text, $m)) $invoiceNumber = 'META-' . $m[1];

        $subtotal = 0; $tax = 0;
        if (preg_match('/Total\s*Amount\s*Billed\s*([\d,]+\.?\d*)\s*INR/i', $text, $m)) $subtotal = $this->toFloat($m[1]);
        if (preg_match('/GST\s*Amount\s*in\s*INR:\s*([\d,]+\.?\d*)/i', $text, $m)) $tax = $this->toFloat($m[1]);

        // Create ad invoice
        $invoice = AdInvoice::create([
            'platform' => 'meta', 'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate, 'period_from' => $periodFrom, 'period_to' => $periodTo,
            'subtotal' => $subtotal, 'tax' => $tax, 'total_amount' => $subtotal + $tax,
            'file_url' => $pdfUrl,
        ]);

        // Extract daily transactions
        $imported = 0;
        preg_match_all('/(\d{1,2}\/\d{1,2}\/\d{4})\s+(\d[\d\s-]+\d)\s+(N\/A|Visa[^\d]*\d{4})\s+([\d,]+\.?\d*)\s*INR\s+(Paid|Funded|Pending)/i', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            if (trim($m[5]) === 'Funded') continue; // Skip top-ups
            $amount = $this->toFloat($m[4]);
            if ($amount <= 0.01) continue;

            AdSpendManual::create([
                'ad_invoice_id' => $invoice->id, 'platform' => 'meta',
                'date' => \Carbon\Carbon::createFromFormat('n/j/Y', $m[1])->format('Y-m-d'),
                'campaign_name' => 'Meta Ads', 'spend' => $amount,
                'source' => 'pdf',
            ]);
            $imported++;
        }

        $invoice->update(['entry_count' => $imported]);

        // Also create banking transaction for the total
        $this->createBankingEntry($invoiceDate, $subtotal + $tax, "Meta Ads - {$periodFrom} to {$periodTo}", $invoiceNumber, 'ads');

        return back()->with('success', "Meta Ads billing report imported: ₹" . number_format($subtotal, 2) . " spend + ₹" . number_format($tax, 2) . " GST. {$imported} daily entries created.");
    }

    protected function processGoogleAdsPdf(string $text, ?string $pdfUrl): RedirectResponse
    {
        $invoiceNumber = 'GADS-' . date('Ymd');
        if (preg_match('/Invoice\s*number\s*:?\s*([\w-]+)/i', $text, $m)) $invoiceNumber = $m[1];

        $total = 0;
        if (preg_match('/Total\s*:?\s*₹?\s*([\d,]+\.?\d*)/i', $text, $m)) $total = $this->toFloat($m[1]);

        $invoice = AdInvoice::create([
            'platform' => 'google', 'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->format('Y-m-d'), 'total_amount' => $total, 'file_url' => $pdfUrl,
        ]);

        $this->createBankingEntry(now()->format('Y-m-d'), $total, "Google Ads Invoice {$invoiceNumber}", $invoiceNumber, 'ads');

        return back()->with('success', "Google Ads invoice imported: ₹" . number_format($total, 2));
    }

    protected function processDelhiveryPdf(string $text, ?string $pdfUrl): RedirectResponse
    {
        $invoiceNumber = '';
        if (preg_match('/Invoice\s*Number\s*:?\s*([A-Z0-9]+)/i', $text, $m)) $invoiceNumber = $m[1];

        $invoiceDate = now()->format('Y-m-d');
        if (preg_match('/Invoice\s*Date\s*:?\s*(\d{1,2}-[A-Z]{3}-\d{2,4})/i', $text, $m)) {
            try { $invoiceDate = \Carbon\Carbon::parse($m[1])->format('Y-m-d'); } catch (\Throwable $e) {}
        }

        $periodFrom = $periodTo = null;
        if (preg_match('/duration\s*of\s*(\d{1,2}\s*\w+,?\s*\d{2,4})\s*-\s*(\d{1,2}\s*\w+,?\s*\d{2,4})/i', $text, $m)) {
            try {
                $periodFrom = \Carbon\Carbon::parse($m[1])->format('Y-m-d');
                $periodTo = \Carbon\Carbon::parse($m[2])->format('Y-m-d');
            } catch (\Throwable $e) {}
        }

        $subtotal = 0; $total = 0;
        if (preg_match('/Sub\s*Total\s*([\d,]+\.?\d*)/i', $text, $m)) $subtotal = $this->toFloat($m[1]);
        if (preg_match('/Total\s*All\s*amount\s*in\s*\(INR\)\s*([\d,]+\.?\d*)/i', $text, $m)) $total = $this->toFloat($m[1]);
        $tax = $total - $subtotal;

        $type = preg_match('/Waybill\s*Journey|WhatsApp|VAS/i', $text) ? 'vas' : 'freight';

        // Find Delhivery partner
        $partner = DeliveryPartner::firstOrCreate(['slug' => 'delhivery'], ['name' => 'Delhivery']);

        $logInvoice = LogisticsInvoice::create([
            'delivery_partner_id' => $partner->id, 'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate, 'period_from' => $periodFrom, 'period_to' => $periodTo,
            'type' => $type, 'subtotal' => $subtotal, 'cgst' => $tax / 2, 'sgst' => $tax / 2,
            'total_amount' => $total, 'file_url' => $pdfUrl,
        ]);

        $this->createBankingEntry($invoiceDate, $total, "Delhivery - {$type} invoice {$invoiceNumber}", $invoiceNumber, 'logistics');

        return back()->with('success', "Delhivery {$type} invoice {$invoiceNumber} imported: ₹" . number_format($total, 2) . ". Upload the CSV for shipment-level details.");
    }

    protected function processGenericInvoicePdf(string $text, ?string $pdfUrl, string $category, string $vendor): RedirectResponse
    {
        $invoiceNumber = '';
        if (preg_match('/Invoice\s*#?\s*:?\s*([\w-]+)/i', $text, $m)) $invoiceNumber = $m[1];

        $total = 0;
        if (preg_match('/Total\s*:?\s*₹?\s*([\d,]+\.?\d*)/i', $text, $m)) $total = $this->toFloat($m[1]);
        if ($total <= 0 && preg_match('/Grand\s*Total\s*:?\s*₹?\s*([\d,]+\.?\d*)/i', $text, $m)) $total = $this->toFloat($m[1]);

        if ($total > 0) {
            $this->createBankingEntry(now()->format('Y-m-d'), $total, "{$vendor} Invoice {$invoiceNumber}", $invoiceNumber, $category);
        }

        return back()->with('success', "{$vendor} invoice processed: ₹" . number_format($total, 2));
    }

    protected function processCsv($file, string $hint): RedirectResponse
    {
        $path = $file->getRealPath();
        $firstLine = strtolower(file($path)[0] ?? '');

        // Detect CSV type
        if ($hint === 'banking' || str_contains($firstLine, 'narration') || str_contains($firstLine, 'closing balance')
            || str_contains($firstLine, 'cr/dr') || str_contains($firstLine, 'debit') && str_contains($firstLine, 'credit')) {
            return $this->processBankCsv($path);
        }

        if ($hint === 'logistics' || str_contains($firstLine, 'waybill_num') || str_contains($firstLine, 'waybill_order_id')) {
            return $this->processLogisticsCsv($path, $firstLine);
        }

        if ($hint === 'ads' || str_contains($firstLine, 'amount spent') || str_contains($firstLine, 'campaign')
            || str_contains($firstLine, 'impressions') && str_contains($firstLine, 'clicks')) {
            return $this->processAdsCsv($path);
        }

        // Try bank statement as fallback
        return $this->processBankCsv($path);
    }

    protected function processBankCsv(string $path): RedirectResponse
    {
        $parser = new BankStatementParser();
        $result = $parser->parse($path);

        if (empty($result['transactions'])) {
            return back()->with('error', 'No transactions found. Unsupported CSV format.');
        }

        // Get or create default bank account
        $account = BankAccount::first() ?? BankAccount::create([
            'name' => 'Primary Account', 'type' => 'current',
        ]);

        $batch = Str::random(16);
        $imported = 0;
        foreach ($result['transactions'] as $t) {
            BankTransaction::create([
                'bank_account_id' => $account->id, 'date' => $t['date'], 'type' => $t['type'],
                'amount' => $t['amount'], 'balance' => $t['balance'] ?? null,
                'description' => $t['description'] ?? null, 'reference' => $t['reference'] ?? null,
                'category' => $t['category'] ?? 'other', 'source' => 'import', 'upload_batch' => $batch,
            ]);
            $imported++;
        }

        return back()->with('success', "Bank statement imported ({$result['format']} format): {$imported} transactions.");
    }

    protected function processLogisticsCsv(string $path, string $firstLine): RedirectResponse
    {
        $partner = DeliveryPartner::firstOrCreate(['slug' => 'delhivery'], ['name' => 'Delhivery']);
        $importer = new DelhiveryCsvImporter();

        $invoiceNum = 'CSV-' . date('YmdHis');
        $invoice = LogisticsInvoice::create([
            'delivery_partner_id' => $partner->id, 'invoice_number' => $invoiceNum,
            'invoice_date' => now()->format('Y-m-d'), 'type' => str_contains($firstLine, 'waybill_order_id') ? 'vas' : 'freight',
        ]);

        if (str_contains($firstLine, 'waybill_order_id')) {
            $result = $importer->importVasCsv($path, $invoice, $partner);
        } else {
            $result = $importer->importFreightCsv($path, $invoice, $partner);
        }

        return back()->with('success', "Logistics CSV imported: {$result['imported']} shipments.");
    }

    protected function processAdsCsv(string $path): RedirectResponse
    {
        $parser = new AdSpendCsvParser();
        $result = $parser->parse($path);

        if (empty($result['entries'])) {
            return back()->with('error', 'No ad spend data found in CSV.');
        }

        $invoice = AdInvoice::create([
            'platform' => $result['format'] === 'meta' ? 'meta' : ($result['format'] === 'google' ? 'google' : 'other'),
            'invoice_number' => 'CSV-' . date('YmdHis'),
            'invoice_date' => now()->format('Y-m-d'),
        ]);

        $totalSpend = 0;
        foreach ($result['entries'] as $entry) {
            AdSpendManual::create([
                'ad_invoice_id' => $invoice->id, 'platform' => $entry['platform'],
                'date' => $entry['date'], 'campaign_name' => $entry['campaign_name'] ?? 'Unknown',
                'spend' => $entry['spend'], 'impressions' => $entry['impressions'] ?? 0,
                'clicks' => $entry['clicks'] ?? 0, 'conversions' => $entry['conversions'] ?? 0,
                'source' => 'csv',
            ]);
            $totalSpend += $entry['spend'];
        }

        $invoice->update(['total_amount' => $totalSpend, 'entry_count' => count($result['entries'])]);

        return back()->with('success', "Ad spend CSV imported ({$result['format']}): {$result['parsed']} entries, ₹" . number_format($totalSpend, 2) . " total spend.");
    }

    /**
     * Auto-create a banking debit entry from invoice processing.
     */
    protected function createBankingEntry(string $date, float $amount, string $description, string $reference, string $category): void
    {
        if ($amount <= 0) return;

        $account = BankAccount::first();
        if (!$account) return;

        BankTransaction::create([
            'bank_account_id' => $account->id, 'date' => $date, 'type' => 'debit',
            'amount' => $amount, 'description' => $description, 'reference' => $reference,
            'category' => $category, 'source' => 'invoice',
        ]);
    }

    protected function extractPdfText(string $path): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($path);
            return $pdf->getText();
        } catch (\Throwable $e) {
            Log::warning('PDF parse failed with smalot, trying fallback', ['error' => $e->getMessage()]);
            // Fallback: try pdftotext command
            $output = '';
            exec("pdftotext '{$path}' -", $lines, $code);
            if ($code === 0) $output = implode("\n", $lines);
            return $output;
        }
    }

    protected function toFloat(string $val): float
    {
        return (float) str_replace([',', ' '], '', $val);
    }
}
