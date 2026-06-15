<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AiInsight;
use App\Models\Tenant\AiInsightRun;
use App\Services\AI\AiInsightsGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AiInsightsController extends Controller
{
    public function index(): Response
    {
        $insights = $this->orderedInsights()->get();
        $lastRun  = AiInsightRun::orderByDesc('generated_at')->first();

        return Inertia::render('Tenant/AiInsights', [
            'insights'    => $insights,
            'generatedAt' => $lastRun?->generated_at,
            'hasError'    => $lastRun?->status === 'failed',
        ]);
    }

    public function refresh(AiInsightsGenerator $generator): JsonResponse
    {
        $companyName = optional(Auth::user()->company)->name ?? 'this company';

        try {
            $insights = $generator->generateAndStore($companyName);
            $lastRun  = AiInsightRun::orderByDesc('generated_at')->first();

            return response()->json([
                'success'     => true,
                'insights'    => $insights,
                'generatedAt' => $lastRun?->generated_at,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Could not generate insights right now. Please try again shortly.',
            ], 500);
        }
    }

    protected function orderedInsights()
    {
        return AiInsight::query()
            ->orderByRaw("CASE severity WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE type WHEN 'alert' THEN 0 ELSE 1 END")
            ->orderByDesc('id');
    }
}
