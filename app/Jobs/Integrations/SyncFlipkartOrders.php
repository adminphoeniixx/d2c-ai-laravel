<?php

declare(strict_types=1);

namespace App\Jobs\Integrations;

use App\Models\Tenant\MarketplaceCredential;
use App\Models\Tenant\Order;
use App\Services\Marketplaces\FlipkartClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncFlipkartOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(protected string $schema, protected ?string $sinceDate = null) {}

    public function handle(): void
    {
        \DB::connection('tenant')->statement("SET search_path TO \"{$this->schema}\", public");

        $cred = MarketplaceCredential::where('marketplace', 'flipkart')->where('status', 'connected')->first();
        if (!$cred) return;

        try {
            $client = new FlipkartClient($cred->getDecryptedCredentials());
            $sinceDate = $this->sinceDate ?? now()->subDays(7)->toIso8601String();

            $result = $client->getOrders($sinceDate);
            $shipments = $result['shipments'] ?? [];
            $imported = 0;

            foreach ($shipments as $shipment) {
                $normalized = FlipkartClient::normalizeOrder($shipment);

                Order::updateOrCreate(
                    ['provider' => 'flipkart', 'external_id' => $normalized['external_id']],
                    $normalized
                );
                $imported++;
            }

            $cred->update(['last_synced_at' => now(), 'last_error' => null]);
            Log::info("Flipkart sync complete: {$imported} orders", ['schema' => $this->schema]);

        } catch (\Throwable $e) {
            $cred->update(['last_error' => $e->getMessage()]);
            Log::error('Flipkart sync failed', ['error' => $e->getMessage(), 'schema' => $this->schema]);
            throw $e;
        }

        \DB::connection('tenant')->statement("SET search_path TO public");
    }
}
