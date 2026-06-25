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
use Illuminate\Support\Facades\Log;
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
                'Export this month\'s orders as Excel',
                'What\'s a healthy RTO rate for D2C apparel?',
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

    /**
     * Explicit user-triggered export execution. The frontend calls this
     * when the user clicks the "Export to Excel" button on an assistant
     * message that proposed an export. We look up the message, verify
     * ownership, read the pending_export descriptor from its meta, run
     * the export, and append a new assistant message carrying the
     * download card. No AI inference involved — the user clicked, intent
     * is unambiguous.
     */
    public function runExport(Request $request, string $tenant, int $messageId, AiCopilotService $copilot): JsonResponse
    {
        Log::info('AI export endpoint hit', ['message_id' => $messageId, 'user_id' => Auth::id()]);

        // Look up the proposal message and verify the requesting user
        // owns the conversation it belongs to. This is the only security
        // check needed — without it a logged-in user could trigger
        // exports against other people's pending proposals.
        $message = AiMessage::with('conversation')->find($messageId);
        if (!$message || !$message->conversation) {
            Log::warning('AI export: message not found', ['message_id' => $messageId]);
            abort(404);
        }
        if ($message->conversation->user_id !== Auth::id()) {
            Log::warning('AI export: ownership mismatch', [
                'message_id' => $messageId,
                'conv_user'  => $message->conversation->user_id,
                'auth_user'  => Auth::id(),
            ]);
            abort(403);
        }

        $pending = $message->meta['pending_export'] ?? null;
        if (!is_array($pending) || empty($pending['sql'])) {
            Log::warning('AI export: no pending_export on message', [
                'message_id' => $messageId,
                'meta_keys'  => array_keys($message->meta ?? []),
            ]);
            return response()->json([
                'error' => 'This message does not have a pending export.',
            ], 422);
        }

        Log::info('AI export: invoking service', [
            'message_id' => $messageId,
            'sql_len'    => strlen($pending['sql']),
            'description'=> $pending['description'] ?? null,
        ]);

        $companyName = optional(Auth::user()->company)->name ?? 'this company';

        try {
            $result = $copilot->runExport(
                $companyName,
                (string) $pending['sql'],
                (string) ($pending['description'] ?? 'Export'),
            );
        } catch (\Throwable $e) {
            Log::error('AI export: controller-level exception', [
                'message'   => $e->getMessage(),
                'exception' => get_class($e),
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'trace'     => collect($e->getTrace())->take(5)->map(fn($t) => ($t['file'] ?? '?') . ':' . ($t['line'] ?? '?'))->all(),
            ]);
            return response()->json([
                'error' => 'Could not generate the export. Please try again.',
            ], 500);
        }

        Log::info('AI export: service returned', [
            'message_id'  => $messageId,
            'has_download'=> isset($result['meta']['download']),
            'row_count'   => $result['row_count'] ?? null,
        ]);

        $meta = array_merge([
            'row_count' => $result['row_count'] ?? null,
            'declined'  => false,
            'escalated' => false,
        ], $result['meta'] ?? []);

        $assistantMessage = AiMessage::create([
            'conversation_id' => $message->conversation_id,
            'role'            => 'assistant',
            'content'         => $result['answer'],
            'sql'             => $result['sql'] ?? null,
            'meta'            => $meta,
        ]);

        // Mark the original proposal as fulfilled so the button can be
        // hidden / disabled on a re-render. The pending_export descriptor
        // itself stays on the message (useful for debugging / audit), but
        // gets a sibling flag the frontend uses to gate the button.
        $proposalMeta = $message->meta ?? [];
        $proposalMeta['pending_export_fulfilled'] = true;
        $message->meta = $proposalMeta;
        $message->save();

        $message->conversation->touch();

        return response()->json([
            'message' => [
                'id'         => $assistantMessage->id,
                'role'       => $assistantMessage->role,
                'content'    => $assistantMessage->content,
                'sql'        => $assistantMessage->sql,
                'meta'       => $assistantMessage->meta,
                'created_at' => $assistantMessage->created_at,
            ],
            'proposal_message_id' => $message->id,
        ]);
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

        // Build short history from prior messages for follow-up context.
        // We include 'meta' so the service can find pending_export proposals
        // stored on prior assistant messages when the user confirms.
        $history = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['role', 'content', 'meta'])
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content, 'meta' => $m->meta])
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
                'meta'      => [],
            ];
        }

        // Merge service-level meta (pending_export, download) with
        // controller-level meta (row_count, declined, escalated flags) into
        // the single meta column. The service-level fields are what the
        // frontend reads to render the download button or confirmation UI.
        $meta = array_merge([
            'row_count' => $result['row_count'] ?? null,
            'declined'  => $result['declined'] ?? false,
            'escalated' => $result['escalated'] ?? false,
        ], $result['meta'] ?? []);

        $assistantMessage = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => $result['answer'],
            'sql'             => $result['sql'] ?? null,
            'meta'            => $meta,
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
