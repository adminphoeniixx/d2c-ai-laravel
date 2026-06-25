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
    public function light(string $systemPrompt, string $userMessage, float $temperature = 0.1, int $maxTokens = 2000): ?string
    {
        return $this->chat(
            config('services.do_ai.light_key'),
            config('services.do_ai.light_model', 'deepseek-ai/DeepSeek-V4-Flash'),
            $systemPrompt, $userMessage, $temperature, $maxTokens,
        );
    }

    /**
     * Heavy model (Nemotron 120B) — insights + analysis.
     *
     * Nemotron is a reasoning model: it spends tokens on an internal
     * "reasoning_content" scratchpad before writing the final answer into
     * "content". A small max_tokens budget can be entirely consumed by
     * reasoning, leaving no tokens for the actual answer — the API still
     * returns 200 OK with finish_reason "length" and an empty/missing
     * content field, which looks identical to "the model produced nothing
     * useful" unless you specifically check for it. Default raised well
     * above what a single JSON-insights response plus its reasoning
     * realistically needs.
     */
    public function heavy(string $systemPrompt, string $userMessage, float $temperature = 0.3, int $maxTokens = 6000): ?string
    {
        return $this->chat(
            config('services.do_ai.heavy_key'),
            config('services.do_ai.heavy_model', 'nvidia/Nemotron-3-Super-120B'),
            $systemPrompt, $userMessage, $temperature, $maxTokens,
        );
    }

    protected function chat(string $apiKey, string $model, string $system, string $user, float $temperature, int $maxTokens = 2000): ?string
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
                    'max_tokens'  => $maxTokens,
                    'messages'    => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('DO AI failed', ['status' => $response->status(), 'body' => substr($response->body(), 0, 200)]);
                return null;
            }

            $message = $response->json('choices.0.message');
            $content = $message['content'] ?? null;

            if (empty($content)) {
                $finishReason = $response->json('choices.0.finish_reason');
                $reasoningLen = isset($message['reasoning_content']) ? strlen($message['reasoning_content']) : 0;

                // Reasoning model hit max_tokens before producing an answer
                // — the whole budget went into reasoning_content. Log this
                // distinctly from a genuine empty response, since the fix
                // (raise max_tokens) is different from "model said nothing".
                if ($finishReason === 'length' && $reasoningLen > 0) {
                    Log::warning('DO AI truncated before answer (reasoning exhausted max_tokens)', [
                        'model'          => $model,
                        'max_tokens'     => $maxTokens,
                        'reasoning_len'  => $reasoningLen,
                    ]);
                }

                return null;
            }

            return $content;
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
