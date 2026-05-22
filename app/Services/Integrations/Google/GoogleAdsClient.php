<?php

declare(strict_types=1);

namespace App\Services\Integrations\Google;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAdsClient
{
    protected string $accessToken;
    protected string $customerId;
    protected string $developerToken;

    public function __construct(string $accessToken, string $customerId, string $developerToken)
    {
        $this->accessToken = $accessToken;
        $this->customerId = str_replace('-', '', $customerId);
        $this->developerToken = $developerToken;
    }

    /**
     * Fetch campaigns using Google Ads REST API (GAQL query).
     */
    public function getCampaigns(): array
    {
        $query = "SELECT campaign.id, campaign.name, campaign.status, campaign.campaign_budget, campaign.advertising_channel_type FROM campaign WHERE campaign.status != 'REMOVED' ORDER BY campaign.name";

        return $this->gaqlQuery($query);
    }

    /**
     * Fetch daily ad spend metrics for a date range.
     */
    public function getDailyMetrics(string $since, string $until): array
    {
        $query = "SELECT campaign.id, campaign.name, segments.date, metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions, metrics.conversions_value, metrics.ctr, metrics.average_cpm, metrics.average_cpc FROM campaign WHERE segments.date BETWEEN '{$since}' AND '{$until}' ORDER BY segments.date";

        return $this->gaqlQuery($query);
    }

    /**
     * Fetch accessible customer IDs (to let them pick one).
     */
    public function getAccessibleCustomers(): array
    {
        $response = Http::withHeaders([
            'Authorization'   => "Bearer {$this->accessToken}",
            'developer-token' => $this->developerToken,
        ])->get('https://googleads.googleapis.com/v18/customers:listAccessibleCustomers');

        if (!$response->successful()) {
            Log::error('Google Ads: Failed to list customers', ['body' => $response->body()]);
            return [];
        }

        return $response->json('resourceNames', []);
    }

    protected function gaqlQuery(string $query): array
    {
        $response = Http::withHeaders([
            'Authorization'   => "Bearer {$this->accessToken}",
            'developer-token' => $this->developerToken,
        ])->post("https://googleads.googleapis.com/v18/customers/{$this->customerId}/googleAds:searchStream", [
            'query' => $query,
        ]);

        if (!$response->successful()) {
            Log::error('Google Ads: GAQL query failed', ['query' => $query, 'body' => $response->body()]);
            return [];
        }

        $results = [];
        foreach ($response->json() as $batch) {
            foreach ($batch['results'] ?? [] as $row) {
                $results[] = $row;
            }
        }
        return $results;
    }
}
