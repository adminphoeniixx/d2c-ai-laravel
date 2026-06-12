<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DoAiService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.do_ai.base_url', 'https://inference.do-ai.run/v1');
    }

    /**
     * Light model (DeepSeek V4 Flash) — extraction + classification.
     */
    public function light(string $systemPrompt, string $userMessage, float $temperature = 0.1): ?string
    {
        return $this->chat(
            config('services.do_ai.light_key'),
            config('services.do_ai.light_model', 'deepseek-ai/DeepSeek-V4-Flash'),
            $systemPrompt, $userMessage, $temperature,
        );
    }

    /**
     * Heavy model (Nemotron 120B) — insights + analysis.
     */
    public function heavy(string $systemPrompt, string $userMessage, float $temperature = 0.3): ?string
    {
        return $this->chat(
            config('services.do_ai.heavy_key'),
            config('services.do_ai.heavy_model', 'nvidia/Nemotron-3-Super-120B'),
            $systemPrompt, $userMessage, $temperature,
        );
    }

    protected function chat(string $apiKey, string $model, string $system, string $user, float $temperature): ?string
    {
        if (empty($apiKey)) {
            Log::warning('DO AI key not configured', ['model' => $model]);
            return null;
        }

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withToken($apiKey)
                ->timeout(30)
                ->post('/chat/completions', [
                    'model'       => $model,
                    'temperature' => $temperature,
                    'max_tokens'  => 2000,
                    'messages'    => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('DO AI failed', ['status' => $response->status(), 'body' => substr($response->body(), 0, 200)]);
                return null;
            }

            return $response->json('choices.0.message.content');
        } catch (\Throwable $e) {
            Log::warning('DO AI error', ['model' => $model, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public static function parseJson(?string $text): ?array
    {
        if (empty($text)) return null;
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```\s*$/', '', $text);
        $decoded = json_decode(trim($text), true);
        if (json_last_error() === JSON_ERROR_NONE) return $decoded;
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE) return $decoded;
        }
        return null;
    }
}
