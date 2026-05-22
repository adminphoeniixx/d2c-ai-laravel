<?php

declare(strict_types=1);

namespace App\Jobs\Integrations;

use App\Models\Company;
use App\Models\IntegrationAccount;
use App\Models\Tenant\AdCampaign;
use App\Models\Tenant\AdSpendDaily;
use App\Models\Tenant\Expense;
use App\Services\Integrations\Meta\MetaAdsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMetaAds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        protected string $companyId,
        protected ?string $since = null,
        protected ?string $until = null,
    ) {}

    public function handle(): void
    {
        $company = Company::findOrFail($this->companyId);
        $account = IntegrationAccount::where('company_id', $company->id)
            ->where('provider', IntegrationAccount::PROVIDER_META)
            ->where('status', IntegrationAccount::STATUS_CONNECTED)
            ->first();

        if (!$account) return;

        $accessToken = $account->getCredential('access_token');
        $adAccountId = $account->getCredential('ad_account_id');
        if (!$accessToken || !$adAccountId) return;

        $client = new MetaAdsClient($accessToken, $adAccountId);

        // Switch to tenant schema
        $schema = 'tenant_' . $company->id;
        DB::statement("SET search_path TO \"{$schema}\", public");

        try {
            $this->syncCampaigns($client);
            $this->syncDailySpend($client);
            $account->update(['last_synced_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Meta Ads sync failed', ['company' => $this->companyId, 'error' => $e->getMessage()]);
            $account->update(['status' => IntegrationAccount::STATUS_ERROR, 'error_message' => $e->getMessage()]);
        } finally {
            DB::statement("SET search_path TO public");
        }
    }

    protected function syncCampaigns(MetaAdsClient $client): void
    {
        $campaigns = $client->getCampaigns();

        foreach ($campaigns as $c) {
            AdCampaign::updateOrCreate(
                ['platform' => 'meta', 'external_id' => $c['id']],
                [
                    'name'            => $c['name'] ?? 'Untitled',
                    'status'          => $c['status'] ?? 'ACTIVE',
                    'objective'       => $c['objective'] ?? null,
                    'daily_budget'    => isset($c['daily_budget']) ? $c['daily_budget'] / 100 : null,
                    'lifetime_budget' => isset($c['lifetime_budget']) ? $c['lifetime_budget'] / 100 : null,
                ]
            );
        }
    }

    protected function syncDailySpend(MetaAdsClient $client): void
    {
        $since = $this->since ?? Carbon::now()->subDays(30)->format('Y-m-d');
        $until = $this->until ?? Carbon::now()->format('Y-m-d');

        $campaigns = AdCampaign::where('platform', 'meta')->get();

        foreach ($campaigns as $campaign) {
            $insights = $client->getCampaignInsights($campaign->external_id, $since, $until);

            foreach ($insights as $day) {
                $spend = (float) ($day['spend'] ?? 0);
                $impressions = (int) ($day['impressions'] ?? 0);
                $clicks = (int) ($day['clicks'] ?? 0);

                // Extract conversions from actions
                $conversions = 0;
                $conversionValue = 0;
                foreach ($day['actions'] ?? [] as $action) {
                    if (in_array($action['action_type'], ['purchase', 'offsite_conversion.fb_pixel_purchase'])) {
                        $conversions += (int) ($action['value'] ?? 0);
                    }
                }
                foreach ($day['action_values'] ?? [] as $av) {
                    if (in_array($av['action_type'], ['purchase', 'offsite_conversion.fb_pixel_purchase'])) {
                        $conversionValue += (float) ($av['value'] ?? 0);
                    }
                }

                $dateStr = $day['date_start'] ?? $since;

                AdSpendDaily::updateOrCreate(
                    ['ad_campaign_id' => $campaign->id, 'date' => $dateStr],
                    [
                        'platform'         => 'meta',
                        'spend'            => $spend,
                        'impressions'      => $impressions,
                        'clicks'           => $clicks,
                        'conversions'      => $conversions,
                        'conversion_value' => $conversionValue,
                        'cpm'              => (float) ($day['cpm'] ?? 0),
                        'cpc'              => (float) ($day['cpc'] ?? 0),
                        'ctr'              => (float) ($day['ctr'] ?? 0),
                        'roas'             => $spend > 0 ? round($conversionValue / $spend, 2) : 0,
                    ]
                );
            }
        }

        // Auto-create expenses grouped by date
        $this->syncExpenses($since, $until);
    }

    /**
     * Auto-create/update expense records from ad spend data.
     * Groups by date, creates one expense per day.
     */
    protected function syncExpenses(string $since, string $until): void
    {
        $dailyTotals = AdSpendDaily::where('platform', 'meta')
            ->whereBetween('date', [$since, $until])
            ->selectRaw('date, SUM(spend) as total_spend')
            ->groupBy('date')
            ->get();

        foreach ($dailyTotals as $day) {
            if ($day->total_spend <= 0) continue;

            $label = 'Meta Ads · ' . Carbon::parse($day->date)->format('d M Y');

            Expense::updateOrCreate(
                [
                    'category'    => 'ads',
                    'source'      => 'auto',
                    'occurred_at' => $day->date,
                    'label'       => $label,
                ],
                [
                    'amount'   => $day->total_spend,
                    'currency' => 'INR',
                    'meta'     => ['platform' => 'meta', 'synced_at' => now()->toIso8601String()],
                ]
            );
        }
    }
}
