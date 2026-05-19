<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\IntegrationAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $stats = [
            ['label' => 'Total Companies',    'value' => Company::count(),                                          'delta' => null],
            ['label' => 'Active',             'value' => Company::where('status', Company::STATUS_ACTIVE)->count(), 'delta' => null],
            ['label' => 'Users',              'value' => User::where('is_admin', false)->count(),                   'delta' => null],
            ['label' => 'Integrations Live',  'value' => IntegrationAccount::where('status', 'connected')->count(), 'delta' => null],
        ];

        $signups = Company::query()
            ->selectRaw("date_trunc('day', created_at)::date as day, count(*) as n")
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('day')->orderBy('day')->get();

        $planDistribution = Company::query()
            ->selectRaw('plan, count(*) as n')
            ->groupBy('plan')->pluck('n', 'plan');

        $latestCompanies = Company::latest()->limit(5)->get(['id', 'slug', 'name', 'plan', 'status', 'created_at']);

        return Inertia::render('Admin/Dashboard', [
            'stats'            => $stats,
            'signups'          => $signups,
            'planDistribution' => $planDistribution,
            'latestCompanies'  => $latestCompanies,
        ]);
    }
}
