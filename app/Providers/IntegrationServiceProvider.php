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
        // Log sync completions into integration_logs for the admin viewer.
        // integration_logs lives in the CENTRAL database, but this listener
        // can fire while the tenant connection is active (sync jobs call
        // tenancy()->initialize() first) — explicitly target the central
        // connection so this never tries to write into a tenant schema.
        Event::listen(IntegrationSyncCompleted::class, function (IntegrationSyncCompleted $e) {
            try {
                \DB::connection('pgsql')->table('integration_logs')->insert([
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
