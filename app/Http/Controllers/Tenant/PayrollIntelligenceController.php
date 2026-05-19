<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PayrollIntelligenceController extends Controller
{
    public function index(): Response
    {
        $thisMonth = (float) Expense::where('category', 'payroll')
            ->whereMonth('occurred_at', now()->month)
            ->whereYear('occurred_at', now()->year)
            ->sum('amount');

        return Inertia::render('Tenant/Payroll', [
            'thisMonth' => $thisMonth,
        ]);
    }
}
