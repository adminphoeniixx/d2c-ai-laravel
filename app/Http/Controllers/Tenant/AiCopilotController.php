<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiCopilotController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tenant/AiCopilot', [
            'suggestions' => [
                'Why did my margin drop last week?',
                'Show me top 5 SKUs by profit this month',
                'Compare CAC by channel last 30 days',
                'What products are at risk of stockout?',
            ],
        ]);
    }

    /**
     * Prompt endpoint — in production this would call Laravel 13's AI SDK
     * with an agent that has tools for querying the tenant DB.
     * Stubbed here to keep the build bootable without provider keys.
     */
    public function prompt(Request $request): JsonResponse
    {
        $validated = $request->validate(['prompt' => ['required', 'string', 'max:2000']]);

        // Placeholder: wire Laravel\Ai\... here with a tool-using agent.
        return response()->json([
            'answer' => "AI Copilot is wired up at the server, but no provider key is configured yet. "
                      . "Add an AI provider in services.php and the agent class in App\\Ai\\Agents\\ to enable.",
            'prompt' => $validated['prompt'],
        ]);
    }
}
