<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AdCampaign;
use App\Models\Tenant\AdInvoice;
use App\Models\Tenant\AdSpendDaily;
use App\Models\Tenant\AdSpendManual;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Order;
use App\Services\Ads\AdSpendCsvParser;
use App\Services\BunnyCDN;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdAnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        $since = Carbon::now()->subDays($days);

        try {
            // Synced spend (from API)
            $metaSyncSpend = (float) AdSpendDaily::where('platform', 'meta')->where('date', '>=', $since)->sum('spend');
            $googleSyncSpend = (float) AdSpendDaily::where('platform', 'google')->where('date', '>=', $since)->sum('spend');

            // Manual/uploaded spend
            $metaManualSpend = (float) AdSpendManual::where('platform', 'meta')->where('date', '>=', $since)->sum('spend');
            $googleManualSpend = (float) AdSpendManual::where('platform', 'google')->where('date', '>=', $since)->sum('spend');
            $otherManualSpend = (float) AdSpendManual::whereNotIn('platform', ['meta', 'google'])->where('date', '>=', $since)->sum('spend');

            $metaSpend = $metaSyncSpend + $metaManualSpend;
            $googleSpend = $googleSyncSpend + $googleManualSpend;
            $totalAdSpend = $metaSpend + $googleSpend + $otherManualSpend;

            // Fallback to expense entries
            if ($totalAdSpend <= 0) {
                $totalAdSpend = (float) Expense::where('category', 'ads')->where('occurred_at', '>=', $since)->sum('amount');
            }

            $revenue = (float) Order::where('placed_at', '>=', $since)->sum('total_amount');

            // Platform metrics (synced)
            $metaMetrics = AdSpendDaily::where('platform', 'meta')->where('date', '>=', $since)
                ->selectRaw('SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions, SUM(conversion_value) as conversion_value')
                ->first();
            $googleMetrics = AdSpendDaily::where('platform', 'google')->where('date', '>=', $since)
                ->selectRaw('SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions, SUM(conversion_value) as conversion_value')
                ->first();

            // Add manual metrics
            $metaManualMetrics = AdSpendManual::where('platform', 'meta')->where('date', '>=', $since)
                ->selectRaw('SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions, SUM(conversion_value) as conversion_value')
                ->first();
            $googleManualMetrics = AdSpendManual::where('platform', 'google')->where('date', '>=', $since)
                ->selectRaw('SUM(spend) as spend, SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(conversions) as conversions, SUM(conversion_value) as conversion_value')
                ->first();

            // Daily spend chart (combined)
            $dailySpend = $this->getDailyChart($since);

            // Campaign breakdown (synced)
            $campaigns = AdCampaign::withSum(['dailySpend as total_spend' => fn ($q) => $q->where('date', '>=', $since)], 'spend')
                ->withSum(['dailySpend as total_clicks' => fn ($q) => $q->where('date', '>=', $since)], 'clicks')
                ->withSum(['dailySpend as total_impressions' => fn ($q) => $q->where('date', '>=', $since)], 'impressions')
                ->withSum(['dailySpend as total_conversions' => fn ($q) => $q->where('date', '>=', $since)], 'conversions')
                ->orderByDesc('total_spend')
                ->limit(20)
                ->get();

            // Manual campaign breakdown
            $manualCampaigns = AdSpendManual::where('date', '>=', $since)
                ->selectRaw("platform, campaign_name, SUM(spend) as total_spend, SUM(clicks) as total_clicks, SUM(impressions) as total_impressions, SUM(conversions) as total_conversions")
                ->groupBy('platform', 'campaign_name')
                ->orderByDesc('total_spend')
                ->limit(20)
                ->get();

            // Invoices
            $invoices = AdInvoice::orderByDesc('invoice_date')->limit(20)->get();

        } catch (\Throwable $e) {
            $totalAdSpend = (float) Expense::where('category', 'ads')->where('occurred_at', '>=', $since)->sum('amount');
            $revenue = (float) Order::where('placed_at', '>=', $since)->sum('total_amount');
            $metaSpend = 0; $googleSpend = 0; $otherManualSpend = 0;
            $metaMetrics = null; $googleMetrics = null;
            $metaManualMetrics = null; $googleManualMetrics = null;
            $dailySpend = collect(); $campaigns = collect(); $manualCampaigns = collect();
            $invoices = collect();
        }

        return Inertia::render('Tenant/AdAnalytics', [
            'days' => $days,
            'kpis' => [
                'ad_spend'    => $totalAdSpend,
                'revenue'     => $revenue,
                'roas'        => $totalAdSpend > 0 ? round($revenue / $totalAdSpend, 2) : 0,
                'meta_spend'  => $metaSpend,
                'google_spend'=> $googleSpend,
                'other_spend' => $otherManualSpend,
            ],
            'platforms' => [
                'meta' => [
                    'spend'       => $metaSpend,
                    'impressions' => (int) ($metaMetrics->impressions ?? 0) + (int) ($metaManualMetrics->impressions ?? 0),
                    'clicks'      => (int) ($metaMetrics->clicks ?? 0) + (int) ($metaManualMetrics->clicks ?? 0),
                    'conversions' => (int) ($metaMetrics->conversions ?? 0) + (int) ($metaManualMetrics->conversions ?? 0),
                    'ctr'         => $this->calcCtr($metaMetrics, $metaManualMetrics),
                    'roas'        => $metaSpend > 0 ? round($revenue / $metaSpend, 2) : 0,
                ],
                'google' => [
                    'spend'       => $googleSpend,
                    'impressions' => (int) ($googleMetrics->impressions ?? 0) + (int) ($googleManualMetrics->impressions ?? 0),
                    'clicks'      => (int) ($googleMetrics->clicks ?? 0) + (int) ($googleManualMetrics->clicks ?? 0),
                    'conversions' => (int) ($googleMetrics->conversions ?? 0) + (int) ($googleManualMetrics->conversions ?? 0),
                    'ctr'         => $this->calcCtr($googleMetrics, $googleManualMetrics),
                    'roas'        => $googleSpend > 0 ? round($revenue / $googleSpend, 2) : 0,
                ],
            ],
            'campaigns'       => $campaigns,
            'manualCampaigns' => $manualCampaigns,
            'dailySpend'      => $dailySpend,
            'invoices'        => $invoices,
        ]);
    }

    /**
     * Extract invoice data from uploaded PDF (AJAX).
     */
    public function extractPdf(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['pdf' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $extractor = new \App\Services\InvoicePdfExtractor();
        $data = $extractor->extract($request->file('pdf')->getRealPath());

        return response()->json($data);
    }

    /**
     * Upload ad invoice with CSV spend data.
     */
    public function uploadInvoice(Request $request): RedirectResponse
    {
        $request->validate([
            'platform'       => ['nullable', 'in:meta,google,other'],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'invoice_date'   => ['nullable', 'date'],
            'period_from'    => ['nullable', 'date'],
            'period_to'      => ['nullable', 'date'],
            'tax_amount'     => ['nullable', 'numeric', 'min:0'],
            'invoice_pdf'    => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'spend_csv'      => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        // Smart extract from PDF if fields not provided
        $extracted = [];
        if ($request->hasFile('invoice_pdf')) {
            try {
                $extractor = new \App\Services\InvoicePdfExtractor();
                $extracted = $extractor->extract($request->file('invoice_pdf')->getRealPath());
            } catch (\Throwable $e) {
                \Log::warning('Invoice extraction failed', ['error' => $e->getMessage()]);
            }
        }

        // Use extracted values as fallback for empty fields
        $platform = $request->input('platform') ?: ($extracted['platform'] ?? 'other');
        $invoiceNumber = $request->input('invoice_number') ?: ($extracted['invoice_number'] ?? null);
        $transactionId = $extracted['transaction_id'] ?? null;
        $invoiceDate = $request->input('invoice_date') ?: ($extracted['invoice_date'] ?? now()->format('Y-m-d'));
        $periodFrom = $request->input('period_from') ?: ($extracted['period_from'] ?? null);
        $periodTo = $request->input('period_to') ?: ($extracted['period_to'] ?? null);

        // Tax: a manually entered amount always wins over what the AI
        // extracted. Some invoice formats (e.g. Meta's per-transaction tax
        // invoice, as opposed to a periodic billing report) don't show a
        // separate GST line at all, so the person reviewing the upload
        // needs a way to add it by hand rather than being stuck with
        // whatever (or nothing) the extractor found.
        $manualTax = $request->input('tax_amount');
        $tax = ($manualTax !== null && $manualTax !== '') ? (float) $manualTax : (float) ($extracted['tax'] ?? 0);

        // Filename: prefer transaction_id (Meta's real unique id), then
        // invoice_number, then a random suffix — never reuse a shared
        // Account ID here either, or two Meta PDFs would overwrite each
        // other's stored file on the CDN.
        $fileKey = $transactionId ?: ($invoiceNumber ?: Str::random(8));

        $pdfUrl = null;
        if ($request->hasFile('invoice_pdf')) {
            try {
                $path = "ads/{$platform}/" . $fileKey . '.pdf';
                $cdn = new BunnyCDN();
                $cdn->upload($path, file_get_contents($request->file('invoice_pdf')->getRealPath()));
                $pdfUrl = config('services.bunny.cdn_url') . '/' . $path;
            } catch (\Throwable $e) {
                $pdfUrl = $request->file('invoice_pdf')->store("ads/{$platform}", 'public');
            }
        }

        // Dedup: re-uploading the SAME invoice should replace it, not
        // create a duplicate. The uniqueness key must be the most specific
        // identifier we actually have:
        //   1. transaction_id (Meta's true per-invoice identifier, stored
        //      in metadata) — preferred whenever present.
        //   2. invoice_number — only used when no transaction_id exists,
        //      since some platforms (Delhivery, Google) do issue a real
        //      single invoice number.
        // If NEITHER is available, we deliberately do NOT dedup — treating
        // every such upload as new is far safer than guessing wrong and
        // silently destroying a different invoice's data, which is what
        // happened when this used to fall back to the shared Meta Account
        // ID: two unrelated invoices from the same ad account would
        // collide and the second upload would overwrite the first.
        $existingInvoice = null;
        if ($transactionId) {
            $existingInvoice = AdInvoice::whereRaw("metadata->>'transaction_id' = ?", [$transactionId])->first();
        } elseif ($invoiceNumber) {
            $existingInvoice = AdInvoice::where('invoice_number', $invoiceNumber)->first();
        }

        $metadata = array_filter([
            'source'         => $extracted['source'] ?? null,
            'gstin'          => $extracted['gstin'] ?? $extracted['customer_gstin'] ?? null,
            'tds'            => $extracted['tds'] ?? null,
            'transaction_id' => $transactionId,
        ]);

        // total_amount: prefer subtotal + tax (most reliable, especially
        // once a manual tax override is involved) and only fall back to
        // whatever the AI extracted as total_amount when we have no
        // subtotal to compute from at all.
        $subtotal = (float) ($extracted['subtotal'] ?? 0);
        $totalAmount = $subtotal > 0
            ? $subtotal + $tax
            : (float) ($extracted['total_amount'] ?? 0);

        if ($existingInvoice) {
            $existingInvoice->entries()->delete();
            $existingInvoice->update([
                'platform'       => $platform,
                'invoice_number' => $invoiceNumber,
                'invoice_date'   => $invoiceDate,
                'period_from'    => $periodFrom,
                'period_to'      => $periodTo,
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'total_amount'   => $totalAmount,
                'file_url'       => $pdfUrl ?? $existingInvoice->file_url,
                'metadata'       => $metadata,
            ]);
        }

        $invoice = $existingInvoice ?? AdInvoice::create([
            'platform'       => $platform,
            'invoice_number' => $invoiceNumber,
            'invoice_date'   => $invoiceDate,
            'period_from'    => $periodFrom,
            'period_to'      => $periodTo,
            'subtotal'       => $subtotal,
            'tax'            => $tax,
            'total_amount'   => $totalAmount,
            'file_url'       => $pdfUrl,
            'metadata'       => $metadata,
        ]);

        // Import transactions from extracted PDF data (Meta daily payments)
        $imported = 0;
        $transactions = $extracted['transactions'] ?? [];

        if (!empty($transactions) && is_array($transactions)) {
            foreach ($transactions as $txn) {
                // Skip fund additions
                $status = $txn['status'] ?? $txn['payment_status'] ?? '';
                if (stripos($status, 'Fund') !== false) continue;

                $txnDate = $txn['date'] ?? $txn['Date'] ?? null;
                $txnAmount = $txn['amount'] ?? $txn['Amount'] ?? $txn['spend'] ?? 0;

                if (empty($txnDate) || $txnAmount <= 0) continue;

                AdSpendManual::create([
                    'ad_invoice_id'    => $invoice->id,
                    'platform'         => $platform,
                    'date'             => $txnDate,
                    'campaign_name'    => $txn['description'] ?? $txn['campaign_name'] ?? ucfirst($platform) . ' Ads Daily',
                    'spend'            => (float) str_replace([',', '₹', 'INR', ' '], '', (string) $txnAmount),
                    'source'           => 'pdf',
                    'raw_data'         => $txn,
                ]);
                $imported++;
            }
        }

        // Fallback: if PDF had a total but no individual transactions, create
        // one summary entry. Some Meta invoice formats (notably the
        // per-transaction "Tax invoice" layout, as opposed to a periodic
        // "Billing Report") show only a total_amount with no separately
        // labeled subtotal — so we must fall back to total_amount too, or
        // invoices in that format silently never reach ad_spend_manual and
        // therefore never show up in P&L despite having uploaded fine.
        // Uses $totalAmount (already resolved above, including any manual
        // tax override) rather than re-deriving from $extracted, so the
        // P&L entry always matches what's shown on the invoice card.
        if ($imported === 0 && $totalAmount > 0) {
            AdSpendManual::create([
                'ad_invoice_id'    => $invoice->id,
                'platform'         => $platform,
                'date'             => $invoiceDate,
                'campaign_name'    => ucfirst($platform) . ' Ads (Invoice Total)',
                'spend'            => $totalAmount,
                'source'           => 'pdf',
            ]);
            $imported = 1;
        }

        // Also import from CSV if provided
        if ($request->hasFile('spend_csv')) {
            $parser = new AdSpendCsvParser();
            $result = $parser->parse($request->file('spend_csv')->getRealPath(), $platform);
            foreach ($result['entries'] as $entry) {
                AdSpendManual::create([
                    'ad_invoice_id'    => $invoice->id,
                    'platform'         => $entry['platform'],
                    'date'             => $entry['date'],
                    'campaign_name'    => $entry['campaign_name'] ?? 'Unknown',
                    'spend'            => $entry['spend'],
                    'impressions'      => $entry['impressions'] ?? 0,
                    'clicks'           => $entry['clicks'] ?? 0,
                    'conversions'      => $entry['conversions'] ?? 0,
                    'conversion_value' => $entry['conversion_value'] ?? 0,
                    'source'           => 'csv',
                    'raw_data'         => $entry['raw_data'] ?? [],
                ]);
                $imported++;
            }
        }

        // Update invoice totals
        if ($imported > 0) {
            $totalSpend = AdSpendManual::where('ad_invoice_id', $invoice->id)->sum('spend');
            $invoice->update([
                'entry_count'  => $imported,
                'total_amount' => $invoice->total_amount > 0 ? $invoice->total_amount : $totalSpend,
            ]);
        }

        $source = $extracted['source'] ?? 'unknown';
        $msg = $source !== 'unknown'
            ? "✓ {$source} invoice uploaded. "
            : "Invoice uploaded. ";
        if ($invoiceNumber) $msg .= "#{$invoiceNumber} ";
        if ($imported > 0) $msg .= "· {$imported} spend entries imported.";
        if (!empty($extracted['total_amount'])) $msg .= " · Total: ₹" . number_format($extracted['total_amount'], 0);

        return back()->with('success', $msg);
    }

    /**
     * Add manual ad spend entry.
     */
    public function addManualSpend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform'      => ['required', 'in:meta,google,other'],
            'date'          => ['required', 'date'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'spend'         => ['required', 'numeric', 'min:0'],
            'impressions'   => ['nullable', 'integer', 'min:0'],
            'clicks'        => ['nullable', 'integer', 'min:0'],
            'conversions'   => ['nullable', 'integer', 'min:0'],
        ]);

        AdSpendManual::create([
            'platform'      => $validated['platform'],
            'date'          => $validated['date'],
            'campaign_name' => $validated['campaign_name'] ?? 'Manual Entry',
            'spend'         => $validated['spend'],
            'impressions'   => $validated['impressions'] ?? 0,
            'clicks'        => $validated['clicks'] ?? 0,
            'conversions'   => $validated['conversions'] ?? 0,
            'source'        => 'manual',
        ]);

        return back()->with('success', 'Ad spend entry added.');
    }

    public function deleteInvoice(Request $request, string $tenant, string $invoiceId): RedirectResponse
    {
        $invoice = AdInvoice::findOrFail($invoiceId);

        // Remove the stored PDF from Bunny CDN (or local public storage)
        // before deleting the DB row — otherwise the file is orphaned
        // forever with nothing left pointing at it to clean up later.
        if ($invoice->file_url) {
            try {
                $cdn = new BunnyCDN();
                $deleted = $cdn->delete($invoice->file_url);

                if (!$deleted && str_starts_with($invoice->file_url, '/storage/')) {
                    // Fell back to local public storage at upload time —
                    // BunnyCDN::delete() only handles CDN URLs, so remove
                    // the local file directly via the public disk.
                    $localPath = ltrim(str_replace('/storage/', '', $invoice->file_url), '/');
                    Storage::disk('public')->delete($localPath);
                }
            } catch (\Throwable $e) {
                \Log::warning('Failed to delete ad invoice file from storage', [
                    'invoice_id' => $invoice->id,
                    'file_url'   => $invoice->file_url,
                    'error'      => $e->getMessage(),
                ]);
                // Don't block the DB delete on a storage cleanup failure —
                // an orphaned file is recoverable; a stuck "can't delete
                // invoice" experience is worse for the user.
            }
        }

        $invoice->entries()->delete();
        $invoice->delete();
        return back()->with('success', 'Ad invoice deleted.');
    }

    /**
     * Show invoice detail with all spend entries.
     */
    public function invoiceDetail(Request $request, string $tenant, string $invoiceId): Response
    {
        $invoice = AdInvoice::findOrFail($invoiceId);

        $entries = AdSpendManual::where('ad_invoice_id', $invoice->id)
            ->orderByDesc('date')
            ->get();

        $summary = AdSpendManual::where('ad_invoice_id', $invoice->id)
            ->selectRaw("SUM(spend) as total_spend, SUM(impressions) as total_impressions, SUM(clicks) as total_clicks, SUM(conversions) as total_conversions, COUNT(*) as count")
            ->first();

        return Inertia::render('Tenant/AdInvoiceDetail', [
            'invoice' => $invoice,
            'entries' => $entries,
            'summary' => $summary,
        ]);
    }

    protected function getDailyChart(Carbon $since): array
    {
        // Synced daily
        $synced = AdSpendDaily::where('date', '>=', $since)
            ->selectRaw("date, platform, SUM(spend) as spend")
            ->groupBy('date', 'platform')
            ->orderBy('date')
            ->get();

        // Manual daily
        $manual = AdSpendManual::where('date', '>=', $since)
            ->selectRaw("date, platform, SUM(spend) as spend")
            ->groupBy('date', 'platform')
            ->orderBy('date')
            ->get();

        // Merge
        $chart = [];
        foreach ($synced->merge($manual) as $row) {
            $d = $row->date->format('Y-m-d');
            $chart[$d][$row->platform] = ($chart[$d][$row->platform] ?? 0) + (float) $row->spend;
        }

        ksort($chart);
        return $chart;
    }

    protected function calcCtr($syncMetrics, $manualMetrics): float
    {
        $totalImpr = (int) ($syncMetrics->impressions ?? 0) + (int) ($manualMetrics->impressions ?? 0);
        $totalClicks = (int) ($syncMetrics->clicks ?? 0) + (int) ($manualMetrics->clicks ?? 0);
        return $totalImpr > 0 ? round($totalClicks / $totalImpr * 100, 2) : 0;
    }
}
