<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AiConversation;
use App\Models\Tenant\AiMessage;
use App\Services\AI\AiCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AiCopilotController extends Controller
{
    public function index(): Response
    {
        $conversations = AiConversation::where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get(['id', 'title', 'updated_at']);

        return Inertia::render('Tenant/AiCopilot', [
            'conversations' => $conversations,
            'suggestions' => [
                'Why did my margin drop last week?',
                'Show me top 5 SKUs by profit this month',
                'Compare ad spend by channel this month',
                'What products are at risk of stockout?',
                'Summarize this month vs last month revenue',
                'What were my total expenses last month by category?',
            ],
        ]);
    }

    public function showConversation(string $tenant, int $id): JsonResponse
    {
        $conversation = AiConversation::where('user_id', Auth::id())->findOrFail($id);

        return response()->json([
            'conversation' => [
                'id'    => $conversation->id,
                'title' => $conversation->title,
            ],
            'messages' => $conversation->messages()->get([
                'id', 'role', 'content', 'sql', 'meta', 'created_at',
            ]),
        ]);
    }

    public function destroyConversation(string $tenant, int $id): JsonResponse
    {
        $conversation = AiConversation::where('user_id', Auth::id())->findOrFail($id);
        $conversation->delete();

        return response()->json(['success' => true]);
    }

    public function prompt(Request $request, string $tenant, AiCopilotService $copilot): JsonResponse
    {
        $validated = $request->validate([
            'prompt'          => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $conversation = null;
        if (!empty($validated['conversation_id'])) {
            $conversation = AiConversation::where('user_id', Auth::id())
                ->find($validated['conversation_id']);
        }

        if (!$conversation) {
            $conversation = AiConversation::create([
                'user_id' => Auth::id(),
                'title'   => Str::limit($validated['prompt'], 60),
            ]);
        }

        // Build short history from prior messages for follow-up context
        $history = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        // Persist the user's message
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $validated['prompt'],
        ]);

        $companyName = optional(Auth::user()->company)->name ?? 'this company';

        try {
            $result = $copilot->ask($companyName, $validated['prompt'], $history);
        } catch (\Throwable $e) {
            $result = [
                'answer'    => "Sorry, I ran into an unexpected error answering that. Please try again in a moment.",
                'sql'       => null,
                'row_count' => null,
                'declined'  => false,
                'escalated' => false,
            ];
        }

        $assistantMessage = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => $result['answer'],
            'sql'             => $result['sql'] ?? null,
            'meta'            => [
                'row_count' => $result['row_count'] ?? null,
                'declined'  => $result['declined'] ?? false,
                'escalated' => $result['escalated'] ?? false,
            ],
        ]);

        $conversation->touch();

        return response()->json([
            'conversation_id' => $conversation->id,
            'title'           => $conversation->title,
            'message'         => [
                'id'         => $assistantMessage->id,
                'role'       => $assistantMessage->role,
                'content'    => $assistantMessage->content,
                'sql'        => $assistantMessage->sql,
                'meta'       => $assistantMessage->meta,
                'created_at' => $assistantMessage->created_at,
            ],
        ]);
    }
}
