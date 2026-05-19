<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with(['company:id,slug,name', 'roles:id,name'])
            ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where(function ($qq) use ($request) {
                $n = '%'.$request->string('q').'%';
                $qq->where('name', 'ilike', $n)->orWhere('email', 'ilike', $n);
            }))
            ->when($request->boolean('admins_only'), fn ($q) => $q->where('is_admin', true))
            ->latest()->paginate(25)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users'   => $users,
            'filters' => $request->only(['q', 'admins_only']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => \Spatie\Permission\Models\Role::pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'is_admin' => ['boolean'],
            'roles'    => ['array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => $data['is_admin'] ?? false,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles($data['roles'] ?? []);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user'  => $user->load('roles:id,name'),
            'roles' => \Spatie\Permission\Models\Role::pluck('name'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'is_admin' => ['boolean'],
            'roles'    => ['array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ]);

        $user->update([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'is_admin' => $data['is_admin'] ?? false,
        ]);
        $user->syncRoles($data['roles'] ?? []);

        return back()->with('success', 'User updated.');
    }

    public function show(User $user): Response
    {
        return Inertia::render('Admin/Users/Show', [
            'user' => $user->load(['roles:id,name', 'permissions:id,name', 'company:id,slug,name']),
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();
        return back()->with('success', 'User deleted.');
    }
}
