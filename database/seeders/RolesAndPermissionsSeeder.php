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

        $permissions = [
            // Admin
            'admin.companies.view', 'admin.companies.create', 'admin.companies.update', 'admin.companies.delete',
            'admin.companies.suspend', 'admin.companies.impersonate',
            'admin.users.view', 'admin.users.create', 'admin.users.update', 'admin.users.delete',
            'admin.roles.manage', 'admin.permissions.manage',
            'admin.system.view', 'admin.integrations.view', 'admin.audit.view',

            // Tenant
            'dashboard.view',
            'orders.view', 'orders.export',
            'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'attendance.view', 'attendance.create', 'attendance.update', 'attendance.settings',
            'leaves.view', 'leaves.approve', 'leaves.settings',
            'payroll.view', 'payroll.create', 'payroll.approve',
            'letters.view', 'letters.create',
            'holidays.view', 'holidays.create', 'holidays.update', 'holidays.delete',
            'support.view', 'support.reply', 'support.manage',
            'inventory.view', 'inventory.update',
            'integrations.connect', 'integrations.disconnect',
            'marketplaces.view', 'marketplaces.connect', 'marketplaces.sync',
            'ads.view', 'ads.connect',
            'reports.view',
            'settings.view', 'settings.update',
            'team.view', 'team.manage', 'team.invite',
            'ai.use',
        ];

        foreach ($permissions as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        // Super Admin — all permissions
        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        $supportAdmin = Role::findOrCreate('support-admin', 'web');
        $supportAdmin->syncPermissions([
            'admin.companies.view', 'admin.companies.update', 'admin.companies.impersonate',
            'admin.users.view', 'admin.system.view', 'admin.integrations.view', 'admin.audit.view',
        ]);

        // Owner — full tenant access
        $owner = Role::findOrCreate('owner', 'web');
        $owner->syncPermissions(Permission::where('name', 'not like', 'admin.%')->pluck('name'));

        // Manager — HR, ops, reports, no team/integrations
        $manager = Role::findOrCreate('manager', 'web');
        $manager->syncPermissions([
            'dashboard.view', 'orders.view', 'orders.export',
            'expenses.view', 'expenses.create', 'expenses.update',
            'employees.view', 'employees.create', 'employees.update',
            'attendance.view', 'attendance.create', 'attendance.update', 'attendance.settings',
            'leaves.view', 'leaves.approve', 'leaves.settings',
            'payroll.view', 'payroll.create',
            'letters.view', 'letters.create',
            'holidays.view', 'holidays.create', 'holidays.update',
            'support.view', 'support.reply', 'support.manage',
            'inventory.view', 'inventory.update',
            'marketplaces.view', 'ads.view',
            'reports.view', 'ai.use',
        ]);

        // Accountant — finance focused
        $accountant = Role::findOrCreate('accountant', 'web');
        $accountant->syncPermissions([
            'dashboard.view', 'orders.view', 'orders.export',
            'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
            'payroll.view', 'payroll.create', 'payroll.approve',
            'reports.view', 'ai.use',
        ]);

        // HR — people focused
        $hr = Role::findOrCreate('hr', 'web');
        $hr->syncPermissions([
            'dashboard.view',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'attendance.view', 'attendance.create', 'attendance.update', 'attendance.settings',
            'leaves.view', 'leaves.approve', 'leaves.settings',
            'payroll.view', 'payroll.create',
            'letters.view', 'letters.create',
            'holidays.view', 'holidays.create', 'holidays.update', 'holidays.delete',
            'support.view', 'support.reply',
            'reports.view', 'ai.use',
        ]);

        // Sales — customer & orders focused
        $sales = Role::findOrCreate('sales', 'web');
        $sales->syncPermissions([
            'dashboard.view', 'orders.view', 'orders.export',
            'support.view', 'support.reply',
            'marketplaces.view', 'ads.view',
            'inventory.view',
            'reports.view', 'ai.use',
        ]);

        // Staff — basic access
        $staff = Role::findOrCreate('staff', 'web');
        $staff->syncPermissions([
            'dashboard.view', 'orders.view',
            'expenses.view', 'expenses.create',
            'reports.view', 'ai.use',
        ]);

        // Viewer — read only
        $viewer = Role::findOrCreate('viewer', 'web');
        $viewer->syncPermissions([
            'dashboard.view', 'orders.view', 'reports.view',
        ]);

        $this->command->info('✓ Roles & permissions synced (8 roles, ' . count($permissions) . ' permissions).');
    }
}
