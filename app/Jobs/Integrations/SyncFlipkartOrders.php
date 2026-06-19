<?php

declare(strict_types=1);

namespace App\Jobs\Integrations;

use App\Models\Company;
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
        $companyId = str_starts_with($this->schema, 'tenant_')
            ? substr($this->schema, strlen('tenant_'))
            : $this->schema;

        $company = Company::find($companyId);
        if (!$company) {
            Log::error('Flipkart sync: company not found', ['schema' => $this->schema]);
            return;
        }

        tenancy()->initialize($company);

        try {
            $cred = MarketplaceCredential::where('marketplace', 'flipkart')->where('status', 'connected')->first();
            if (!$cred) return;

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
            MarketplaceCredential::where('marketplace', 'flipkart')->update(['last_error' => $e->getMessage()]);
            Log::error('Flipkart sync failed', ['error' => $e->getMessage(), 'schema' => $this->schema]);
            throw $e;
        } finally {
            tenancy()->end();
        }
    }
}
