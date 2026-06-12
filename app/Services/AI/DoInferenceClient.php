<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DoInferenceClient
{
    /**
     * Call the light model (cheap — extraction, categorization).
     */
    public static function light(string $systemPrompt, string $userPrompt, int $maxTokens = null): ?string
    {
        return self::call('light', $systemPrompt, $userPrompt, $maxTokens);
    }

    /**
     * Call the heavy model (smart — insights, analysis).
     */
    public static function heavy(string $systemPrompt, string $userPrompt, int $maxTokens = null): ?string
    {
        return self::call('heavy', $systemPrompt, $userPrompt, $maxTokens);
    }

    protected static function call(string $tier, string $systemPrompt, string $userPrompt, ?int $maxTokens): ?string
    {
        $config = config("ai.{$tier}");
        $baseUrl = config('ai.base_url');

        if (empty($config['key'])) {
            Log::warning("AI {$tier} key not configured");
            return null;
        }

        try {
            $response = Http::withToken($config['key'])
                ->timeout(30)
                ->post("{$baseUrl}/chat/completions", [
                    'model'       => $config['model'],
                    'max_tokens'  => $maxTokens ?? 1000,
                    'temperature' => 0.1, // Low temperature for structured extraction
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userPrompt],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning("AI {$tier} API error", ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;

            // Log token usage for cost tracking
            $usage = $data['usage'] ?? [];
            Log::info("AI {$tier} usage", [
                'model'   => $config['model'],
                'input'   => $usage['prompt_tokens'] ?? 0,
                'output'  => $usage['completion_tokens'] ?? 0,
            ]);

            return $content;
        } catch (\Throwable $e) {
            Log::error("AI {$tier} call failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse JSON from AI response (strips markdown fences if present).
     */
    public static function parseJson(?string $response): ?array
    {
        if (empty($response)) return null;

        // Strip markdown code fences
        $clean = trim($response);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);
        $clean = trim($clean);

        try {
            return json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::warning('AI JSON parse failed', ['response' => substr($response, 0, 200)]);
            return null;
        }
    }
}
