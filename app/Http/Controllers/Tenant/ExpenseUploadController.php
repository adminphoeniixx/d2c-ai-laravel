<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use App\Services\ExpenseExtractorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExpenseUploadController extends Controller
{
    /**
     * Upload file → AI extract → return extracted data for preview.
     */
    public function extract(Request $request)
    {
        $request->validate([
            'file'         => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,csv,txt',
            'image_method' => 'nullable|in:ai,tesseract',
        ]);

        $file = $request->file('file');

        // Get image extraction method from request or tenant setting
        $imageMethod = $request->input('image_method')
            ?? $this->getTenantSetting('expense_image_method', 'ai');

        $extractor = new ExpenseExtractorService($imageMethod);
        $result    = $extractor->extract($file);

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        // Upload to Bunny CDN for permanent storage
        $cdnPath = $this->uploadToBunny($file);

        $result['attachment_path'] = $cdnPath;
        $result['attachment_type'] = $result['type'];
        $result['original_name']   = $file->getClientOriginalName();

        return response()->json($result);
    }

    /**
     * Save extracted expense(s) after user confirms/edits.
     */
    public function store(Request $request)
    {
        $request->validate([
            'expenses'   => 'required|array|min:1',
            'expenses.*.label'       => 'required|string|max:255',
            'expenses.*.amount'      => 'required|numeric|min:0.01',
            'expenses.*.category'    => 'required|string',
            'expenses.*.occurred_at' => 'required|date',
        ]);

        $created = [];
        foreach ($request->expenses as $exp) {
            $created[] = Expense::create([
                'label'              => $exp['label'],
                'amount'             => $exp['amount'],
                'category'           => $exp['category'],
                'occurred_at'        => $exp['occurred_at'],
                'currency'           => $exp['currency'] ?? 'INR',
                'source'             => 'manual',
                'vendor'             => $exp['vendor'] ?? null,
                'notes'              => $exp['notes'] ?? null,
                'attachment_path'    => $exp['attachment_path'] ?? null,
                'attachment_type'    => $exp['attachment_type'] ?? null,
                'extracted_data'     => isset($exp['extracted_data']) ? $exp['extracted_data'] : null,
                'line_items'         => isset($exp['line_items']) ? $exp['line_items'] : null,
                'recorded_by_user_id' => $request->user()->id,
            ]);
        }

        return back()->with('success', count($created) . ' expense(s) saved.');
    }

    /**
     * Get expense settings for this tenant.
     */
    public function getSettings()
    {
        return response()->json([
            'image_method'  => $this->getTenantSetting('expense_image_method', 'ai'),
            'visible_tiles' => $this->getTenantSetting('expense_visible_tiles', ['total', 'gst_paid', 'net_amount', 'entries']),
        ]);
    }

    /**
     * Update expense settings for this tenant.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'image_method'  => 'required|in:ai,tesseract',
            'visible_tiles' => 'nullable|array',
        ]);

        $this->setTenantSetting('expense_image_method', $request->image_method);

        if ($request->has('visible_tiles')) {
            $this->setTenantSetting('expense_visible_tiles', $request->visible_tiles);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Tenant settings helpers — uses tenant data().
     */
    protected function getTenantSetting(string $key, $default = null)
    {
        if (function_exists('tenant') && tenant()) {
            $val = tenant()->getAttribute('data')[$key] ?? null;
            if ($val !== null) return $val;
        }
        return $default;
    }

    protected function setTenantSetting(string $key, $value): void
    {
        if (function_exists('tenant') && tenant()) {
            $data = tenant()->getAttribute('data') ?? [];
            $data[$key] = $value;
            tenant()->update(['data' => $data]);
        }
    }

    /**
     * Upload file to Bunny CDN.
     */
    protected function uploadToBunny($file): string
    {
        $zone    = config('services.bunny.zone', 'dtwoc');
        $apiKey  = config('services.bunny.api_key');
        $baseUrl = config('services.bunny.storage_url', "https://sg.storage.bunnycdn.com/{$zone}");
        $cdnBase = config('services.bunny.cdn_url', "https://{$zone}.b-cdn.net");

        $tenant  = tenant('id') ?? 'default';
        $ext     = $file->getClientOriginalExtension();
        $name    = 'expenses/' . $tenant . '/' . date('Y/m') . '/' . Str::uuid() . '.' . $ext;

        try {
            $response = Http::withHeaders([
                'AccessKey'    => $apiKey,
                'Content-Type' => 'application/octet-stream',
            ])->withBody(
                file_get_contents($file->getRealPath()),
                'application/octet-stream'
            )->put("{$baseUrl}/{$name}");

            if ($response->successful()) {
                return "{$cdnBase}/{$name}";
            }

            Log::error('Bunny upload failed', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('Bunny upload exception', ['error' => $e->getMessage()]);
        }

        $localPath = $file->store('expenses', 'public');
        return '/storage/' . $localPath;
    }
}
