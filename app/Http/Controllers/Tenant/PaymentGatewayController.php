<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PgInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class PaymentGatewayController extends Controller
{
    public function index(string $tenant)
    {
        $invoices = PgInvoice::orderByDesc('created_at')->get();

        $summary = [
            'total_gross'   => (float) $invoices->sum('net_settled'),   // total invoiced amount
            'total_charges' => (float) $invoices->sum('total_charges'),  // charges excl GST
            'total_gst'     => (float) $invoices->sum('gst_amount'),
            'total_settled' => (float) $invoices->sum('net_settled'),
        ];

        return Inertia::render('Tenant/PaymentGateway/Index', [
            'invoices' => $invoices,
            'summary'  => $summary,
        ]);
    }

    public function upload(Request $request, string $tenant)
    {
        $request->validate([
            'invoices'   => 'required|array|min:1',
            'invoices.*' => 'file|mimes:pdf,csv,jpg,jpeg,png|max:10240',
        ]);

        $results = [];

        foreach ($request->file('invoices') as $file) {
            try {
                $ext       = strtolower($file->getClientOriginalExtension());
                $extracted = match(true) {
                    $ext === 'csv'                      => $this->extractFromCsv($file),
                    in_array($ext, ['jpg','jpeg','png'])=> $this->extractFromImage($file),
                    default                             => $this->extractFromPdf($file),
                };

                // Check for duplicate invoice number
                if (!empty($extracted['invoice_number'])) {
                    $exists = PgInvoice::where('invoice_number', $extracted['invoice_number'])->exists();
                    if ($exists) {
                        $results[] = ['success' => false, 'file' => $file->getClientOriginalName(), 'error' => 'Duplicate invoice number'];
                        continue;
                    }
                }

                PgInvoice::create(array_merge($extracted, [
                    'original_name' => $file->getClientOriginalName(),
                ]));

                $results[] = ['success' => true, 'file' => $file->getClientOriginalName()];
            } catch (\Exception $e) {
                $results[] = ['success' => false, 'file' => $file->getClientOriginalName(), 'error' => $e->getMessage()];
            }
        }

        $success = collect($results)->where('success', true)->count();
        $failed  = collect($results)->where('success', false)->count();

        if ($success > 0) {
            return response()->json([
                'success' => true,
                'message' => "{$success} invoice(s) processed" . ($failed > 0 ? ", {$failed} failed" : ""),
                'results' => $results,
            ]);
        }

        return response()->json([
            'success' => false,
            'error'   => 'All uploads failed: ' . collect($results)->pluck('error')->join(', '),
            'results' => $results,
        ], 422);
    }

    private function extractFromPdf($file): array
    {
        $tmpPath = $file->getRealPath();
        $text    = '';

        if (function_exists('shell_exec')) {
            $escaped = escapeshellarg($tmpPath);
            $text    = shell_exec("pdftotext {$escaped} - 2>/dev/null") ?? '';
            \Illuminate\Support\Facades\Log::info("PG PDF pdftotext output length: " . strlen($text));
        }

        if (empty(trim($text))) {
            $raw  = file_get_contents($tmpPath);
            $text = preg_replace('/[^\x20-\x7E\n]/', ' ', $raw);
            \Illuminate\Support\Facades\Log::info("PG PDF fallback raw text length: " . strlen($text));
        }

        return $this->extractWithAI($text, $file->getClientOriginalName());
    }

    private function extractFromImage($file): array
    {
        $base64 = base64_encode(file_get_contents($file->getRealPath()));
        $mime   = $file->getMimeType();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.do_ai.vision_key'),
            'Content-Type'  => 'application/json',
        ])->timeout(60)->post(config('services.do_ai.base_url') . '/chat/completions', [
            'model'    => config('services.do_ai.vision_model', 'nemotron-nano-12b-v2-vl'),
            'messages' => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$base64}"]],
                    ['type' => 'text', 'text' => $this->aiPrompt()],
                ],
            ]],
            'max_tokens' => 600,
        ]);

        return $this->parseAiResponse($response->json('choices.0.message.content', '{}'));
    }

    private function extractFromCsv($file): array
    {
        $content = file_get_contents($file->getRealPath());
        return $this->extractWithAI(substr($content, 0, 4000), $file->getClientOriginalName());
    }

    private function extractWithAI(string $text, string $filename): array
    {
        if (empty(trim($text))) {
            \Illuminate\Support\Facades\Log::info("PG: empty text for {$filename}, using fallback");
            return $this->fallbackFromFilename($filename);
        }

        \Illuminate\Support\Facades\Log::info("PG: extracted text length=" . strlen($text) . " for {$filename}");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.do_ai.light_key'),
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post(config('services.do_ai.base_url') . '/chat/completions', [
            'model'    => config('services.do_ai.light_model', 'deepseek-4-flash'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a payment gateway invoice parser. Return ONLY valid JSON, no markdown, no explanation.'],
                ['role' => 'user',   'content' => $this->aiPrompt() . "\n\nDocument content:\n" . $text],
            ],
            'max_tokens' => 600,
        ]);

        $raw = $response->json('choices.0.message.content', '{}');
        \Illuminate\Support\Facades\Log::info("PG: AI response: {$raw}");

        return $this->parseAiResponse($raw);
    }

    private function aiPrompt(): string
    {
        return 'You are parsing a payment gateway invoice. Extract these fields carefully:

- gateway: the PG name in lowercase (razorpay, payu, cashfree, stripe, phonepe, ccavenue, paytm, other)
- invoice_number: the invoice or document number/ID
- period: billing period as readable string e.g. "Apr 2026" or "01/04/26 - 30/04/26"
- period_start: the FIRST day of the billing period as an ISO date "YYYY-MM-DD" (e.g. if period is "Apr 2026" use "2026-04-01"; if period is "01/04/26 - 30/04/26" use "2026-04-01"). If you cannot determine this, use null.
- period_end: the LAST day of the billing period as an ISO date "YYYY-MM-DD" (e.g. if period is "Apr 2026" use "2026-04-30"; if period is "01/04/26 - 30/04/26" use "2026-04-30"). If you cannot determine this, use null.
- gross_volume: total payment volume processed by merchants (NOT the invoice amount). For Razorpay this is the settlement amount or total transactions processed. If not available, use 0.
- total_charges: PG commission/fee BEFORE tax. For Razorpay look for "Commission" or "Amount" column BEFORE GST.
- gst_amount: total GST charged (CGST + SGST + IGST combined)
- net_settled: grand total of the invoice (total_charges + gst_amount). For Razorpay this is the "Grand Total" or "Total" amount.

IMPORTANT for Razorpay invoices:
- "Amount" column = total_charges (e.g. ₹235.69)
- "Tax Total" or IGST amount = gst_amount (e.g. ₹42.42)
- "Grand Total" = net_settled (e.g. ₹278.11)
- gross_volume = 0 unless a separate "Payment Volume" or "Total Transactions" figure is shown

Return ONLY valid JSON, no markdown, no explanation:
{"gateway":"","invoice_number":"","period":"","period_start":null,"period_end":null,"gross_volume":0,"total_charges":0,"gst_amount":0,"net_settled":0}';
    }

    private function parseAiResponse(string $text): array
    {
        $text = preg_replace('/```json|```/i', '', $text);
        $text = trim($text);

        // Extract JSON object if surrounded by other text
        if (preg_match('/\{[^{}]*\}/s', $text, $m)) {
            $text = $m[0];
        }

        $data = json_decode($text, true);
        if (!is_array($data)) $data = [];

        return $this->normalizeExtracted($data);
    }

    private function normalizeExtracted(array $data): array
    {
        return [
            'gateway'        => strtolower(trim($data['gateway']         ?? '')),
            'invoice_number' => trim($data['invoice_number']             ?? '') ?: null,
            'period'         => trim($data['period']                     ?? '') ?: null,
            'period_start'   => $this->normalizeDate($data['period_start'] ?? null),
            'period_end'     => $this->normalizeDate($data['period_end']   ?? null),
            'gross_volume'   => (float) ($data['gross_volume']           ?? 0),
            'total_charges'  => (float) ($data['total_charges']          ?? 0),
            'gst_amount'     => (float) ($data['gst_amount']             ?? 0),
            'net_settled'    => (float) ($data['net_settled']            ?? 0),
        ];
    }

    /**
     * Validate an AI-extracted date string, returning a Y-m-d string or null.
     */
    private function normalizeDate($value): ?string
    {
        if (empty($value) || !is_string($value)) return null;

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function fallbackFromFilename(string $filename): array
    {
        $gateway = '';
        foreach (['razorpay', 'payu', 'cashfree', 'stripe', 'phonepe'] as $gw) {
            if (stripos($filename, $gw) !== false) { $gateway = $gw; break; }
        }
        return $this->normalizeExtracted(['gateway' => $gateway]);
    }

    public function destroy(string $tenant, int $id)
    {
        PgInvoice::findOrFail($id)->delete();
        return back()->with('success', 'Invoice deleted.');
    }

    public function show(string $tenant, int $id)
    {
        return response()->json(PgInvoice::findOrFail($id));
    }
}
