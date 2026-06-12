<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    // Roles are in public schema — hardcode to avoid tenant connection issues
    private const TENANT_ROLES = ['owner', 'manager', 'accountant', 'hr', 'sales', 'staff', 'viewer'];

    public function index(): Response
    {
        $company = app('current_company');

        $members = User::where('company_id', $company->id)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->roles->first()->name ?? 'staff',
                'created_at' => $u->created_at,
                'is_current' => $u->id === auth()->id(),
            ]);

        $pendingInvites = TeamInvitation::whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        // Query roles from public schema explicitly
        $roles = DB::connection('pgsql')
            ->table('roles')
            ->whereIn('name', self::TENANT_ROLES)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Tenant/Team/Index', [
            'members'        => $members,
            'pendingInvites' => $pendingInvites,
            'roles'          => $roles,
            'roleDescriptions' => [
                'owner'      => 'Full access to all features, settings, and team management',
                'manager'    => 'Access to HR, payroll, reports, and operations. Cannot manage team or integrations.',
                'accountant' => 'Access to payroll, expenses, orders, and financial reports.',
                'hr'         => 'Access to employees, attendance, leaves, payroll, and letters.',
                'sales'      => 'Access to orders, customers, ad analytics, and marketplace data.',
                'staff'      => 'View orders, expenses, and use AI. Limited write access.',
                'viewer'     => 'Read-only access to dashboard and reports.',
            ],
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:120'],
            'role'  => ['required', 'string', 'in:' . implode(',', self::TENANT_ROLES)],
        ]);

        $company = app('current_company');

        if (User::where('company_id', $company->id)->where('email', $validated['email'])->exists()) {
            return back()->with('error', 'This user is already a team member.');
        }

        $existing = TeamInvitation::where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return back()->with('error', 'An invitation is already pending for this email.');
        }

        TeamInvitation::create([
            'email'      => $validated['email'],
            'role'       => $validated['role'],
            'token'      => Str::random(64),
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(7),
        ]);

        return back()->with('success', "Invitation sent to {$validated['email']}.");
    }

    public function cancelInvite(Request $request, string $tenant, TeamInvitation $invitation): RedirectResponse
    {
        $invitation->delete();
        return back()->with('success', 'Invitation cancelled.');
    }

    public function updateRole(Request $request, string $tenant, string $userId): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:' . implode(',', self::TENANT_ROLES)],
        ]);

        $user = User::where('company_id', app('current_company')->id)->findOrFail($userId);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $user->syncRoles([$validated['role']]);

        return back()->with('success', "Role updated for {$user->name}.");
    }

    public function removeMember(Request $request, string $tenant, string $userId): RedirectResponse
    {
        $user = User::where('company_id', app('current_company')->id)->findOrFail($userId);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot remove yourself.');
        }

        $user->update(['company_id' => null]);
        $user->syncRoles([]);

        return back()->with('success', "{$user->name} removed from team.");
    }
}
