<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $grouped = Permission::all()->groupBy(fn ($p) => explode('.', $p->name)[0]);
        return Inertia::render('Admin/Permissions/Index', ['grouped' => $grouped]);
    }

    public function create(): Response { return Inertia::render('Admin/Permissions/Create'); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('permissions', 'name'), 'regex:/^[a-z0-9]+(\.[a-z0-9-]+)+$/'],
        ]);
        Permission::create(['name' => $data['name'], 'guard_name' => 'web']);
        return redirect()->route('admin.permissions.index')->with('success', 'Permission created.');
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render('Admin/Permissions/Edit', ['permission' => $permission]);
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('permissions', 'name')->ignore($permission->id)],
        ]);
        $permission->update($data);
        return back()->with('success', 'Permission updated.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();
        return back()->with('success', 'Permission deleted.');
    }

    public function show(Permission $permission): Response
    {
        return Inertia::render('Admin/Permissions/Show', ['permission' => $permission]);
    }
}
