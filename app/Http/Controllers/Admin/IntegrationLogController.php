<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = DB::table('integration_logs')
            ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->input('provider')))
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->input('level')))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->input('company_id')))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/System/IntegrationLogs', [
            'logs'    => $logs,
            'filters' => $request->only(['provider', 'level', 'company_id']),
        ]);
    }
}
