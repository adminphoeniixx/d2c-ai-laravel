<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves AI-generated Excel exports created by ExcelExportService.
 *
 * The route this hits (`ai.export.download`) is protected by Laravel's
 * `signed` middleware, so the URL itself proves it was issued by us and
 * hasn't been tampered with. On top of that we verify the requesting user
 * actually owns the file via the {user_id}/{uuid} path layout — preventing
 * one logged-in user from downloading another user's export even if they
 * somehow obtained the signed URL.
 */
class AiExportDownloadController extends Controller
{
    public function download(Request $request, string $tenant, string $path, string $filename): BinaryFileResponse
    {
        $userId = Auth::id();
        abort_unless($userId, 403);

        // Reject anything that tries to escape the per-user dir or includes
        // unexpected characters. The uuid component should be a clean UUID
        // string — no slashes, no traversal, no .. etc.
        if (!preg_match('/^[a-f0-9-]{36}$/i', $path)) {
            abort(404);
        }

        $storagePath = "exports/{$userId}/{$path}.xlsx";

        if (!Storage::disk('local')->exists($storagePath)) {
            abort(404);
        }

        // Sanitize filename for the Content-Disposition header — preserves
        // a friendly download name without letting weird characters through.
        $safeFilename = preg_replace('/[^A-Za-z0-9 ._-]/', '', $filename) ?: 'export.xlsx';
        if (!str_ends_with(strtolower($safeFilename), '.xlsx')) {
            $safeFilename .= '.xlsx';
        }

        return response()->download(
            Storage::disk('local')->path($storagePath),
            $safeFilename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }
}
