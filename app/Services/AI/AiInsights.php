<?php

declare(strict_types=1);

namespace App\Services\AI;

class AiInsights
{
    protected DoAiService $ai;

    public function __construct()
    {
        $this->ai = new DoAiService();
    }

    /**
     * Generate ad spend insights from data.
     */
    public function adSpendInsights(array $data): ?string
    {
        $system = 'You are a D2C brand marketing analyst. Analyze the ad spend data and give 3-5 actionable insights in bullet points. Be specific with numbers. Keep under 200 words. Focus on ROAS, cost efficiency, platform comparison, and recommendations.';

        $json = json_encode($data);
        // Trim to save tokens
        $input = mb_substr($json, 0, 2000);

        return $this->ai->heavy($system, $input, 0.4);
    }

    /**
     * Generate P&L insights.
     */
    public function pnlInsights(array $data): ?string
    {
        $system = 'You are a D2C brand financial analyst. Analyze the P&L data and give 3-5 actionable insights. Focus on margins, cost optimization, revenue trends, and red flags. Keep under 200 words. Be specific.';

        return $this->ai->heavy($system, mb_substr(json_encode($data), 0, 2000), 0.4);
    }
}
