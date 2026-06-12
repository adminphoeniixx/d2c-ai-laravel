<?php

declare(strict_types=1);

namespace App\Jobs\Integrations;

use App\Models\Tenant\MarketplaceCredential;
use App\Models\Tenant\Order;
use App\Services\Marketplaces\AmazonClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAmazonOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(protected string $schema, protected ?string $sinceDate = null) {}

    public function handle(): void
    {
        \DB::connection('tenant')->statement("SET search_path TO \"{$this->schema}\", public");

        $cred = MarketplaceCredential::where('marketplace', 'amazon')->where('status', 'connected')->first();
        if (!$cred) return;

        try {
            $client = new AmazonClient($cred->getDecryptedCredentials());
            $sinceDate = $this->sinceDate ?? now()->subDays(7)->toIso8601String();

            $nextToken = null;
            $imported = 0;

            do {
                $result = $client->getOrders($sinceDate, 'A21TJRUUN4KGV', $nextToken);
                $orders = $result['payload']['Orders'] ?? [];
                $nextToken = $result['payload']['NextToken'] ?? null;

                foreach ($orders as $amzOrder) {
                    $items = $client->getOrderItems($amzOrder['AmazonOrderId']);
                    $normalized = AmazonClient::normalizeOrder($amzOrder, $items);

                    Order::updateOrCreate(
                        ['provider' => 'amazon', 'external_id' => $normalized['external_id']],
                        $normalized
                    );
                    $imported++;
                }

                // Respect rate limits
                usleep(500000); // 0.5s
            } while ($nextToken);

            $cred->update(['last_synced_at' => now(), 'last_error' => null]);
            Log::info("Amazon sync complete: {$imported} orders", ['schema' => $this->schema]);

        } catch (\Throwable $e) {
            $cred->update(['last_error' => $e->getMessage()]);
            Log::error('Amazon sync failed', ['error' => $e->getMessage(), 'schema' => $this->schema]);
            throw $e;
        }

        \DB::connection('tenant')->statement("SET search_path TO public");
    }
}
