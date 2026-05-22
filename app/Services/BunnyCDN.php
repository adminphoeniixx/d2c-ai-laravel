<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BunnyCDN
{
    protected ?string $storageZone;
    protected ?string $apiKey;
    protected ?string $cdnUrl;
    protected ?string $region;

    public function __construct()
    {
        $this->storageZone = config('services.bunny.storage_zone');
        $this->apiKey = config('services.bunny.api_key');
        $this->cdnUrl = config('services.bunny.cdn_url');
        $this->region = config('services.bunny.region');
    }

    public function isConfigured(): bool
    {
        return !empty($this->storageZone) && !empty($this->apiKey) && !empty($this->cdnUrl);
    }

    /**
     * Get the storage API host based on region.
     * Falkenstein (default): storage.bunnycdn.com
     * Other regions: {region}.storage.bunnycdn.com
     */
    protected function storageHost(): string
    {
        if (!empty($this->region)) {
            return "{$this->region}.storage.bunnycdn.com";
        }
        return 'storage.bunnycdn.com';
    }

    /**
     * Upload a file to Bunny CDN. Falls back to local storage if not configured or fails.
     */
    public function upload(UploadedFile $file, string $folder, ?string $customName = null): string
    {
        $ext = $file->getClientOriginalExtension();
        $name = $customName ?? (Str::random(16) . '.' . $ext);
        $path = trim($folder, '/') . '/' . $name;

        if ($this->isConfigured()) {
            try {
                $host = $this->storageHost();
                $url = "https://{$host}/{$this->storageZone}/{$path}";

                Log::info('BunnyCDN uploading', ['url' => $url, 'size' => $file->getSize()]);

                $response = Http::timeout(30)->withHeaders([
                    'AccessKey'    => $this->apiKey,
                    'Content-Type' => 'application/octet-stream',
                ])->withBody(
                    file_get_contents($file->getRealPath()),
                    'application/octet-stream'
                )->put($url);

                if ($response->successful()) {
                    $cdnPath = rtrim($this->cdnUrl, '/') . '/' . $path;
                    Log::info('BunnyCDN upload success', ['cdn_url' => $cdnPath]);
                    return $cdnPath;
                }

                Log::warning('BunnyCDN upload failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'url'    => $url,
                ]);
            } catch (\Throwable $e) {
                Log::error('BunnyCDN upload error', ['error' => $e->getMessage(), 'path' => $path]);
            }
        } else {
            Log::warning('BunnyCDN not configured', [
                'zone' => $this->storageZone,
                'key'  => !empty($this->apiKey) ? 'SET' : 'EMPTY',
                'cdn'  => $this->cdnUrl,
            ]);
        }

        // Fallback: local storage
        $localPath = $file->store($folder, 'public');
        return '/storage/' . $localPath;
    }

    /**
     * Delete a file from Bunny CDN (if it's a CDN URL).
     */
    public function delete(string $url): bool
    {
        if (!$this->isConfigured()) return false;
        if (!$this->cdnUrl || !str_starts_with($url, $this->cdnUrl)) return false;

        $path = str_replace(rtrim($this->cdnUrl, '/') . '/', '', $url);
        $host = $this->storageHost();

        try {
            $response = Http::withHeaders(['AccessKey' => $this->apiKey])
                ->delete("https://{$host}/{$this->storageZone}/{$path}");
            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('BunnyCDN delete error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
