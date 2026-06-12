<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExpenseExtractorService
{
    protected string $imageMethod;

    public function __construct(string $imageMethod = 'ai')
    {
        $this->imageMethod = $imageMethod;
    }

    public function extract(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        $ext  = strtolower($file->getClientOriginalExtension());

        if ($ext === 'csv' || $mime === 'text/csv') {
            return $this->extractFromCsv($file);
        }
        if ($ext === 'pdf' || $mime === 'application/pdf') {
            return $this->extractFromPdf($file);
        }
        if (Str::startsWith($mime, 'image/')) {
            return $this->imageMethod === 'tesseract'
                ? $this->extractFromImageTesseract($file)
                : $this->extractFromImageAI($file);
        }

        return ['success' => false, 'error' => 'Unsupported file type: ' . $ext, 'type' => $ext];
    }

    /* ── CSV ───────────────────────────────────────────────── */

    protected function extractFromCsv(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        $headers = null;

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) === 1 && empty(trim($line[0] ?? ''))) continue;
            if ($headers === null) {
                $headers = array_map(fn($h) => strtolower(trim($h)), $line);
                continue;
            }
            if (count($line) >= count($headers)) {
                $rows[] = array_combine($headers, array_slice($line, 0, count($headers)));
            }
        }
        fclose($handle);

        if (empty($rows)) {
            return ['success' => false, 'error' => 'CSV file is empty or invalid', 'type' => 'csv'];
        }

        $mapped = $this->aiMapCsvColumns($headers, array_slice($rows, 0, 3));
        $expenses = [];
        foreach ($rows as $row) {
            $amount = $this->parseAmount($row[$mapped['amount_col']] ?? '0');
            if ($amount <= 0) continue;
            $expenses[] = [
                'label'       => $row[$mapped['label_col']] ?? '',
                'amount'      => $amount,
                'category'    => $mapped['default_category'] ?? 'other',
                'occurred_at' => $this->parseDate($row[$mapped['date_col']] ?? ''),
                'vendor'      => $row[$mapped['vendor_col']] ?? '',
            ];
        }

        return ['success' => true, 'type' => 'csv', 'multi' => true, 'needs_title' => false, 'data' => $expenses, 'row_count' => count($expenses)];
    }

    /* ── PDF ───────────────────────────────────────────────── */

    protected function extractFromPdf(UploadedFile $file): array
    {
        $tmpPath = $file->getRealPath();
        $text = '';
        exec("pdftotext -layout " . escapeshellarg($tmpPath) . " -", $output, $code);
        if ($code === 0) $text = implode("\n", $output);

        if (empty(trim($text))) {
            exec("pdftoppm -r 200 -png " . escapeshellarg($tmpPath) . " /tmp/expense_page", $out2, $code2);
            if ($code2 === 0) {
                foreach (glob('/tmp/expense_page-*.png') as $page) {
                    exec("tesseract " . escapeshellarg($page) . " stdout 2>/dev/null", $ocrOut);
                    $text .= implode("\n", $ocrOut) . "\n";
                    @unlink($page);
                }
            }
        }

        if (empty(trim($text))) {
            return ['success' => false, 'error' => 'Could not extract text from PDF', 'type' => 'pdf'];
        }
        return $this->aiExtractFromText($text, 'pdf');
    }

    /* ── Image: AI Vision (DO Nemotron VL) ─────────────────── */

    protected function extractFromImageAI(UploadedFile $file): array
    {
        $tmpPath = $file->getRealPath();
        $mime = $file->getMimeType();

        // Resize large images to max 1200px to avoid timeouts
        $imageData = $this->resizeImage($tmpPath, $mime, 1200);
        $base64 = base64_encode($imageData);

        try {
            $result = $this->callVisionAI($base64, 'image/jpeg');
            return $this->parseAIResponse($result, 'image');
        } catch (\Exception $e) {
            Log::error('Vision AI failed, falling back to tesseract', ['error' => $e->getMessage()]);
            return $this->extractFromImageTesseract($file);
        }
    }

    /* ── Image: Tesseract OCR ──────────────────────────────── */

    protected function extractFromImageTesseract(UploadedFile $file): array
    {
        $text = '';
        exec("tesseract " . escapeshellarg($file->getRealPath()) . " stdout 2>/dev/null", $output, $code);
        if ($code === 0) $text = implode("\n", $output);

        if (empty(trim($text))) {
            return ['success' => false, 'error' => 'Could not read text from image. Try a clearer photo or switch to AI Vision in settings.', 'type' => 'image'];
        }
        return $this->aiExtractFromText($text, 'image');
    }

    /* ── AI: Text Extraction ───────────────────────────────── */

    protected function aiExtractFromText(string $text, string $sourceType): array
    {
        if (strlen($text) > 15000) $text = substr($text, 0, 15000) . "\n\n[... truncated]";

        try {
            $result = $this->callTextAI([
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user',   'content' => "Extract ALL expense/invoice data from this document. If multiple invoices, return a JSON array.\n\nDocument text:\n\n{$text}"],
            ]);
            return $this->parseAIResponse($result, $sourceType);
        } catch (\Exception $e) {
            Log::error('AI extraction failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'AI extraction failed: ' . $e->getMessage(), 'type' => $sourceType];
        }
    }

    /* ── Vision AI Call (DO Serverless - Nemotron VL) ───────── */

    protected function callVisionAI(string $base64, string $mime): string
    {
        $apiKey  = config('services.do_ai.vision_key');
        $baseUrl = config('services.do_ai.base_url', 'https://inference.do-ai.run/v1');
        $model   = config('services.do_ai.vision_model', 'nemotron-nano-12b-v2-vl');

        $response = Http::timeout(90)
            ->withHeaders(['Authorization' => "Bearer {$apiKey}", 'Content-Type' => 'application/json'])
            ->post("{$baseUrl}/chat/completions", [
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$base64}"]],
                        ['type' => 'text', 'text' => "Extract all expense/invoice details from this image. Read ALL numbers, quantities, rates, and amounts carefully. Follow the JSON format from the system prompt."],
                    ]],
                ],
                'max_tokens' => 4000, 'temperature' => 0.1,
            ]);

        if ($response->failed()) throw new \Exception('Vision AI error: ' . $response->status() . ' - ' . $response->body());
        return $response->json('choices.0.message.content', '');
    }

    /* ── Text AI Call (DO Serverless - DeepSeek) ────────────── */

    protected function callTextAI(array $messages): string
    {
        $apiKey  = config('services.do_ai.light_key');
        $baseUrl = config('services.do_ai.base_url', 'https://inference.do-ai.run/v1');
        $model   = config('services.do_ai.light_model', 'deepseek-4-flash');

        $response = Http::timeout(60)
            ->withHeaders(['Authorization' => "Bearer {$apiKey}", 'Content-Type' => 'application/json'])
            ->post("{$baseUrl}/chat/completions", [
                'model' => $model, 'messages' => $messages, 'max_tokens' => 8000, 'temperature' => 0.1,
            ]);

        if ($response->failed()) throw new \Exception('Text AI error: ' . $response->status() . ' - ' . $response->body());
        return $response->json('choices.0.message.content', '');
    }

    /* ── CSV Column Mapping ────────────────────────────────── */

    protected function aiMapCsvColumns(array $headers, array $sampleRows): array
    {
        $headerStr = implode(', ', $headers);
        $sampleStr = implode("\n", array_map(fn($r) => implode(', ', array_values($r)), $sampleRows));

        try {
            $result = $this->callTextAI([
                ['role' => 'system', 'content' => 'You are a CSV column mapper. Respond ONLY with valid JSON. No other text.'],
                ['role' => 'user', 'content' => "CSV headers: {$headerStr}\nSample rows:\n{$sampleStr}\n\nMap to: {\"label_col\":\"\",\"amount_col\":\"\",\"date_col\":\"\",\"vendor_col\":\"\",\"default_category\":\"other\"}"],
            ]);
            $json = json_decode($result, true);
            if ($json && isset($json['amount_col'])) return $json;
        } catch (\Exception $e) {}

        return $this->guessCsvColumns($headers);
    }

    protected function guessCsvColumns(array $headers): array
    {
        $map = ['label_col' => '', 'amount_col' => '', 'date_col' => '', 'vendor_col' => '', 'default_category' => 'other'];
        foreach ($headers as $h) {
            $l = strtolower($h);
            if (str_contains($l, 'amount') || str_contains($l, 'total') || str_contains($l, 'price')) $map['amount_col'] = $h;
            elseif (str_contains($l, 'date') || str_contains($l, 'time')) $map['date_col'] = $h;
            elseif (str_contains($l, 'desc') || str_contains($l, 'label') || str_contains($l, 'narration')) $map['label_col'] = $h;
            elseif (str_contains($l, 'vendor') || str_contains($l, 'party') || str_contains($l, 'name')) $map['vendor_col'] = $h;
        }
        if (empty($map['label_col'])) {
            $used = array_filter(array_values($map), fn($v) => !empty($v) && $v !== 'other');
            foreach ($headers as $h) { if (!in_array($h, $used)) { $map['label_col'] = $h; break; } }
        }
        return $map;
    }

    /* ── System Prompt ─────────────────────────────────────── */

    protected function systemPrompt(): string
    {
        return <<<'SYS'
You are an expense/invoice data extractor for Indian D2C businesses. Extract structured data from receipts, invoices, and bills.

ALWAYS respond with ONLY valid JSON (no markdown, no backticks, no explanation).

If ONE invoice/expense: return a single object. If MULTIPLE: return a JSON ARRAY of objects.

Format per object:
{
    "vendor": "Company/person name or empty string if unclear",
    "label": "Short description of purchase",
    "amount": 1234.56,
    "date": "2026-05-30",
    "category": "ads|payroll|inventory|shipping|tools|rent|packaging|logistics|platform_fee|payment_gateway|software|marketing|office|travel|utilities|other",
    "gst_amount": 0, "gst_rate": 0, "has_gst": false,
    "line_items": [{"description": "Item", "qty": 1, "rate": 100, "amount": 100}],
    "needs_title": false,
    "confidence": "high|medium|low",
    "notes": "Invoice number, order number etc."
}

Rules: vendor="" and needs_title=true for handwritten notes without vendor. INR default. Indian date formats (DD/MM/YY). Extract GST (IGST/CGST/SGST). textiles→inventory, marketplace fees→platform_fee, delivery→logistics. Separate objects for separate invoices. Read handwritten numbers carefully.
SYS;
    }

    /* ── Response Parsing ──────────────────────────────────── */

    protected function parseAIResponse(string $raw, string $sourceType): array
    {
        $clean = trim(preg_replace('/```(?:json)?\s*|```\s*$/i', '', $raw));
        $json = json_decode($clean, true);
        if ($json === null) {
            Log::warning('AI response not valid JSON', ['raw' => $raw]);
            return ['success' => false, 'error' => 'Could not parse AI response', 'type' => $sourceType];
        }

        if (!isset($json[0])) $json = [$json];

        $expenses = [];
        $anyNeedsTitle = false;
        foreach ($json as $item) {
            $needsTitle = $item['needs_title'] ?? empty($item['vendor']);
            if ($needsTitle) $anyNeedsTitle = true;
            $expenses[] = [
                'vendor' => $item['vendor'] ?? '', 'label' => $item['label'] ?? '',
                'amount' => floatval($item['amount'] ?? 0), 'date' => $item['date'] ?? now()->toDateString(),
                'category' => $item['category'] ?? 'other',
                'gst_amount' => floatval($item['gst_amount'] ?? 0), 'gst_rate' => floatval($item['gst_rate'] ?? 0),
                'has_gst' => $item['has_gst'] ?? false, 'line_items' => $item['line_items'] ?? [],
                'confidence' => $item['confidence'] ?? 'medium', 'notes' => $item['notes'] ?? '',
                'needs_title' => $needsTitle,
            ];
        }

        $multi = count($expenses) > 1;
        return ['success' => true, 'type' => $sourceType, 'multi' => $multi, 'needs_title' => $anyNeedsTitle, 'data' => $multi ? $expenses : $expenses[0]];
    }

    /* ── Image Resize ──────────────────────────────────────── */

    protected function resizeImage(string $path, string $mime, int $maxDim = 1200): string
    {
        if (!extension_loaded('gd')) return file_get_contents($path);

        $info = @getimagesize($path);
        if (!$info) return file_get_contents($path);

        [$w, $h] = $info;
        if ($w <= $maxDim && $h <= $maxDim) return file_get_contents($path);

        $ratio = min($maxDim / $w, $maxDim / $h);
        $newW = (int)($w * $ratio);
        $newH = (int)($h * $ratio);

        $src = match ($mime) {
            'image/png'  => @imagecreatefrompng($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => @imagecreatefromjpeg($path),
        };

        if (!$src) return file_get_contents($path);

        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        ob_start();
        imagejpeg($dst, null, 85);
        $data = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $data;
    }

    /* ── Helpers ────────────────────────────────────────────── */

    protected function parseAmount(string $val): float
    {
        return abs(floatval(preg_replace('/[^\d.\-]/', '', $val)));
    }

    protected function parseDate(string $val): string
    {
        if (empty($val)) return now()->toDateString();
        foreach (['d/m/Y','d-m-Y','d.m.Y','Y-m-d','d/m/y','d-m-y','m/d/Y'] as $fmt) {
            try { $dt = \Carbon\Carbon::createFromFormat($fmt, trim($val)); if ($dt) return $dt->toDateString(); } catch (\Exception $e) {}
        }
        try { return \Carbon\Carbon::parse($val)->toDateString(); } catch (\Exception $e) { return now()->toDateString(); }
    }
}
