<?php

declare(strict_types=1);

namespace App\Services\Integrations\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaAdsClient
{
    protected string $accessToken;
    protected string $adAccountId;
    protected string $apiVersion = 'v21.0';

    public function __construct(string $accessToken, string $adAccountId)
    {
        $this->accessToken = $accessToken;
        $this->adAccountId = $adAccountId;
    }

    /**
     * Fetch all campaigns for the ad account.
     */
    public function getCampaigns(): array
    {
        $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/act_{$this->adAccountId}/campaigns", [
            'access_token' => $this->accessToken,
            'fields'       => 'id,name,status,objective,daily_budget,lifetime_budget',
            'limit'        => 100,
        ]);

        if (!$response->successful()) {
            Log::error('Meta Ads: Failed to fetch campaigns', ['body' => $response->body()]);
            return [];
        }

        return $response->json('data', []);
    }

    /**
     * Fetch daily insights for a campaign.
     */
    public function getCampaignInsights(string $campaignId, string $since, string $until): array
    {
        $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/{$campaignId}/insights", [
            'access_token' => $this->accessToken,
            'fields'       => 'spend,impressions,clicks,actions,action_values,cpm,cpc,ctr',
            'time_range'   => json_encode(['since' => $since, 'until' => $until]),
            'time_increment' => 1, // daily breakdown
            'limit'        => 100,
        ]);

        if (!$response->successful()) {
            Log::error('Meta Ads: Failed to fetch insights', ['campaign' => $campaignId, 'body' => $response->body()]);
            return [];
        }

        return $response->json('data', []);
    }

    /**
     * Fetch all ad accounts for the user (to let them pick one).
     */
    public function getAdAccounts(): array
    {
        $response = Http::get("https://graph.facebook.com/{$this->apiVersion}/me/adaccounts", [
            'access_token' => $this->accessToken,
            'fields'       => 'id,name,account_id,currency,account_status',
            'limit'        => 50,
        ]);

        if (!$response->successful()) {
            return [];
        }

        return $response->json('data', []);
    }
}
