<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permission catalogue
        $permissions = [
            // Admin permissions
            'admin.companies.view', 'admin.companies.create', 'admin.companies.update', 'admin.companies.delete',
            'admin.companies.suspend', 'admin.companies.impersonate',
            'admin.users.view', 'admin.users.create', 'admin.users.update', 'admin.users.delete',
            'admin.roles.manage', 'admin.permissions.manage',
            'admin.system.view', 'admin.integrations.view', 'admin.audit.view',

            // Tenant (company) permissions
            'orders.view', 'orders.export',
            'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
            'integrations.connect', 'integrations.disconnect',
            'reports.view',
            'team.manage',
            'ai.use',
        ];

        foreach ($permissions as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        // Roles
        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        $supportAdmin = Role::findOrCreate('support-admin', 'web');
        $supportAdmin->syncPermissions([
            'admin.companies.view', 'admin.companies.update', 'admin.companies.impersonate',
            'admin.users.view', 'admin.system.view',
            'admin.integrations.view', 'admin.audit.view',
        ]);

        $owner = Role::findOrCreate('owner', 'web');
        $owner->syncPermissions([
            'orders.view', 'orders.export',
            'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
            'integrations.connect', 'integrations.disconnect',
            'reports.view', 'team.manage', 'ai.use',
        ]);

        $staff = Role::findOrCreate('staff', 'web');
        $staff->syncPermissions([
            'orders.view', 'expenses.view', 'expenses.create',
            'reports.view', 'ai.use',
        ]);

        $this->command->info('✓ Roles & permissions synced.');
    }
}
