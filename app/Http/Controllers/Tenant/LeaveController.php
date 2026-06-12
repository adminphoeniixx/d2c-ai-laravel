<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee;
use App\Models\Tenant\LeaveBalance;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    /* ── Leave Types ──────────────────────────── */

    public function types(): Response
    {
        LeaveType::seedDefaults(); // ensure defaults exist

        return Inertia::render('Tenant/HR/Leaves/Types', [
            'types' => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:60'],
            'code'                  => ['required', 'string', 'max:10', 'unique:leave_types,code'],
            'is_paid'               => ['nullable', 'boolean'],
            'annual_quota'          => ['required', 'integer', 'min:0'],
            'carry_forward'         => ['nullable', 'boolean'],
            'max_carry_forward_days'=> ['nullable', 'integer', 'min:0'],
            'max_consecutive_days'  => ['nullable', 'integer', 'min:1'],
            'requires_approval'     => ['nullable', 'boolean'],
            'description'           => ['nullable', 'string', 'max:500'],
        ]);

        LeaveType::create($validated);
        return back()->with('success', 'Leave type created.');
    }

    public function updateType(Request $request, string $tenant, LeaveType $leaveType): RedirectResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:60'],
            'is_paid'               => ['nullable', 'boolean'],
            'annual_quota'          => ['required', 'integer', 'min:0'],
            'carry_forward'         => ['nullable', 'boolean'],
            'max_carry_forward_days'=> ['nullable', 'integer', 'min:0'],
            'max_consecutive_days'  => ['nullable', 'integer', 'min:1'],
            'requires_approval'     => ['nullable', 'boolean'],
            'is_active'             => ['nullable', 'boolean'],
            'description'           => ['nullable', 'string', 'max:500'],
        ]);

        $leaveType->update($validated);
        return back()->with('success', 'Leave type updated.');
    }

    /* ── Leave Requests ───────────────────────── */

    public function requests(Request $request): Response
    {
        $status = $request->input('status', 'pending');

        $requests = LeaveRequest::with(['employee:id,employee_id,first_name,last_name,designation', 'leaveType:id,name,code'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $counts = [
            'pending'  => LeaveRequest::where('status', 'pending')->count(),
            'approved' => LeaveRequest::where('status', 'approved')->count(),
            'rejected' => LeaveRequest::where('status', 'rejected')->count(),
        ];

        return Inertia::render('Tenant/HR/Leaves/Requests', [
            'requests' => $requests,
            'counts'   => $counts,
            'status'   => $status,
        ]);
    }

    public function approve(Request $request, string $tenant, LeaveRequest $leaveRequest): RedirectResponse
    {
        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', 'This request is already ' . $leaveRequest->status);
        }

        $leaveRequest->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Deduct from balance
        $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $leaveRequest->from_date->year)
            ->first();

        if ($balance) {
            $balance->increment('used', (float) $leaveRequest->days);
        }

        return back()->with('success', 'Leave approved.');
    }

    public function reject(Request $request, string $tenant, LeaveRequest $leaveRequest): RedirectResponse
    {
        $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);

        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', 'This request is already ' . $leaveRequest->status);
        }

        $leaveRequest->update([
            'status'           => 'rejected',
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        return back()->with('success', 'Leave rejected.');
    }

    /* ── Leave Balances ───────────────────────── */

    public function balances(Request $request): Response
    {
        $year = (int) $request->input('year', now()->year);

        $employees = Employee::where('status', 'active')->orderBy('first_name')->get();

        // Initialize balances for all active employees
        foreach ($employees as $emp) {
            LeaveBalance::initializeForEmployee($emp->id, $year);
        }

        $balances = LeaveBalance::with(['employee:id,employee_id,first_name,last_name', 'leaveType:id,name,code'])
            ->where('year', $year)
            ->get()
            ->groupBy('employee_id');

        return Inertia::render('Tenant/HR/Leaves/Balances', [
            'employees' => $employees,
            'balances'  => $balances,
            'types'     => LeaveType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'year'      => $year,
        ]);
    }
}
