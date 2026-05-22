<?php

declare(strict_types=1);

namespace App\Jobs\Integrations;

use App\Models\Company;
use App\Models\IntegrationAccount;
use App\Models\Tenant\AdCampaign;
use App\Models\Tenant\AdSpendDaily;
use App\Models\Tenant\Expense;
use App\Services\Integrations\Google\GoogleAdsClient;
use App\Services\Integrations\Google\GoogleOAuth;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncGoogleAds implements ShouldQueue
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
            ->where('provider', IntegrationAccount::PROVIDER_GOOGLE)
            ->where('status', IntegrationAccount::STATUS_CONNECTED)
            ->first();

        if (!$account) return;

        $accessToken = $account->getCredential('access_token');
        $refreshToken = $account->getCredential('refresh_token');
        $customerId = $account->getCredential('customer_id');
        $developerToken = config('services.google_ads.developer_token', '');

        if (!$accessToken || !$customerId) return;

        // Refresh token if needed
        if ($refreshToken) {
            try {
                $oauth = new GoogleOAuth();
                $tokens = $oauth->refreshToken($refreshToken);
                $accessToken = $tokens['access_token'];
                $creds = $account->credentials;
                $creds['access_token'] = $accessToken;
                $account->update(['credentials' => $creds]);
            } catch (\Throwable $e) {
                Log::warning('Google Ads: Token refresh failed, using existing token', ['error' => $e->getMessage()]);
            }
        }

        $client = new GoogleAdsClient($accessToken, $customerId, $developerToken);
        $schema = 'tenant_' . $company->id;
        DB::statement("SET search_path TO \"{$schema}\", public");

        try {
            $this->syncCampaigns($client);
            $this->syncDailySpend($client);
            $account->update(['last_synced_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Google Ads sync failed', ['company' => $this->companyId, 'error' => $e->getMessage()]);
            $account->update(['status' => IntegrationAccount::STATUS_ERROR, 'error_message' => $e->getMessage()]);
        } finally {
            DB::statement("SET search_path TO public");
        }
    }

    protected function syncCampaigns(GoogleAdsClient $client): void
    {
        $rows = $client->getCampaigns();

        foreach ($rows as $row) {
            $c = $row['campaign'] ?? [];
            AdCampaign::updateOrCreate(
                ['platform' => 'google', 'external_id' => (string) ($c['id'] ?? '')],
                [
                    'name'      => $c['name'] ?? 'Untitled',
                    'status'    => $c['status'] ?? 'ENABLED',
                    'objective' => $c['advertisingChannelType'] ?? null,
                ]
            );
        }
    }

    protected function syncDailySpend(GoogleAdsClient $client): void
    {
        $since = $this->since ?? Carbon::now()->subDays(30)->format('Y-m-d');
        $until = $this->until ?? Carbon::now()->format('Y-m-d');

        $rows = $client->getDailyMetrics($since, $until);

        foreach ($rows as $row) {
            $campaign = $row['campaign'] ?? [];
            $metrics = $row['metrics'] ?? [];
            $date = $row['segments']['date'] ?? $since;

            $dbCampaign = AdCampaign::where('platform', 'google')
                ->where('external_id', (string) ($campaign['id'] ?? ''))
                ->first();

            if (!$dbCampaign) continue;

            // Google returns cost in micros (1/1,000,000 of currency)
            $spend = ((float) ($metrics['costMicros'] ?? 0)) / 1_000_000;
            $impressions = (int) ($metrics['impressions'] ?? 0);
            $clicks = (int) ($metrics['clicks'] ?? 0);
            $conversions = (float) ($metrics['conversions'] ?? 0);
            $conversionValue = (float) ($metrics['conversionsValue'] ?? 0);

            AdSpendDaily::updateOrCreate(
                ['ad_campaign_id' => $dbCampaign->id, 'date' => $date],
                [
                    'platform'         => 'google',
                    'spend'            => $spend,
                    'impressions'      => $impressions,
                    'clicks'           => $clicks,
                    'conversions'      => (int) $conversions,
                    'conversion_value' => $conversionValue,
                    'cpm'              => ((float) ($metrics['averageCpm'] ?? 0)) / 1_000_000,
                    'cpc'              => ((float) ($metrics['averageCpc'] ?? 0)) / 1_000_000,
                    'ctr'              => (float) ($metrics['ctr'] ?? 0),
                    'roas'             => $spend > 0 ? round($conversionValue / $spend, 2) : 0,
                ]
            );
        }

        $this->syncExpenses($since, $until);
    }

    protected function syncExpenses(string $since, string $until): void
    {
        $dailyTotals = AdSpendDaily::where('platform', 'google')
            ->whereBetween('date', [$since, $until])
            ->selectRaw('date, SUM(spend) as total_spend')
            ->groupBy('date')
            ->get();

        foreach ($dailyTotals as $day) {
            if ($day->total_spend <= 0) continue;

            $label = 'Google Ads · ' . Carbon::parse($day->date)->format('d M Y');

            Expense::updateOrCreate(
                ['category' => 'ads', 'source' => 'auto', 'occurred_at' => $day->date, 'label' => $label],
                ['amount' => $day->total_spend, 'currency' => 'INR', 'meta' => ['platform' => 'google', 'synced_at' => now()->toIso8601String()]]
            );
        }
    }
}
