<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\IntegrationSyncCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Log sync completions into integration_logs for the admin viewer
        Event::listen(IntegrationSyncCompleted::class, function (IntegrationSyncCompleted $e) {
            try {
                \DB::table('integration_logs')->insert([
                    'company_id' => $e->companyId,
                    'provider'   => $e->provider,
                    'event'      => 'sync.completed',
                    'level'      => $e->failed > 0 ? 'warn' : 'info',
                    'message'    => "Synced {$e->orderCount} orders (failed: {$e->failed})",
                    'context'    => json_encode(['order_count' => $e->orderCount, 'failed' => $e->failed]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $ex) {
                // Table may not exist yet — don't break the sync
            }
        });
    }
}
