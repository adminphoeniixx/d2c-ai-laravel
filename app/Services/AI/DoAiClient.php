<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DoAiClient
{
    /**
     * Call the light model (DeepSeek V4 Flash) — for extraction + categorization.
     */
    public static function light(string $systemPrompt, string $userPrompt, float $temperature = 0): ?array
    {
        return self::call(
            config('services.do_ai.light_key'),
            config('services.do_ai.light_model', 'deepseek-ai/DeepSeek-V4-Flash'),
            $systemPrompt, $userPrompt, $temperature,
        );
    }

    /**
     * Call the heavy model (Nemotron 120B) — for insights + analysis.
     */
    public static function heavy(string $systemPrompt, string $userPrompt, float $temperature = 0.3): ?array
    {
        return self::call(
            config('services.do_ai.heavy_key'),
            config('services.do_ai.heavy_model', 'nvidia/Nemotron-3-Super-120B'),
            $systemPrompt, $userPrompt, $temperature,
        );
    }

    protected static function call(?string $apiKey, string $model, string $systemPrompt, string $userPrompt, float $temperature): ?array
    {
        if (empty($apiKey)) return null;

        $baseUrl = config('services.do_ai.base_url', 'https://inference.do-ai.run/v1');

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post("{$baseUrl}/chat/completions", [
                    'model'       => $model,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => $temperature,
                    'max_tokens'  => 8000,
                ]);

            if (!$response->successful()) {
                Log::warning('DO AI failed', ['status' => $response->status(), 'model' => $model]);
                return null;
            }

            $content = $response->json('choices.0.message.content');
            if (!$content) return null;

            // Strip markdown fences, parse JSON
            $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)));
            $parsed = json_decode($content, true);
            return json_last_error() === JSON_ERROR_NONE ? $parsed : ['_text' => $content];
        } catch (\Throwable $e) {
            Log::warning('DO AI error', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
