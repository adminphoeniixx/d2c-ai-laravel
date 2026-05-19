<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        // Platform admin
        $admin = User::query()->firstOrCreate(
            ['email' => env('ADMIN_SEED_EMAIL', 'admin@pulsara.test')],
            [
                'name'              => 'Platform Admin',
                'password'          => Hash::make(env('ADMIN_SEED_PASSWORD', 'password')),
                'is_admin'          => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['super-admin']);

        // Two seeded companies with owner + staff
        foreach (['Acme Apparel' => 'acme', 'Nova Brews' => 'nova'] as $name => $slug) {
            /** @var Company $company */
            $company = Company::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name'     => $name,
                    'email'    => "hello@{$slug}.test",
                    'status'   => Company::STATUS_ACTIVE,
                    'plan'     => Company::PLAN_PRO,
                    'country'  => 'IN',
                    'currency' => 'INR',
                    'timezone' => 'Asia/Kolkata',
                ]
            );
            // Note: stancl/tenancy TenancyServiceProvider listens for TenantCreated
            // and automatically runs CreateDatabase + MigrateDatabase jobs.
            // So the tenant schema + tables are created on Company::create().

            $owner = User::query()->firstOrCreate(
                ['email' => "owner@{$slug}.test"],
                [
                    'company_id'        => $company->id,
                    'name'              => $name . ' Owner',
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $owner->syncRoles(['owner']);

            $staff = User::query()->firstOrCreate(
                ['email' => "staff@{$slug}.test"],
                [
                    'company_id'        => $company->id,
                    'name'              => $name . ' Staff',
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $staff->syncRoles(['staff']);
        }

        $this->command->info('✓ Seeded admin + 2 companies.');
    }
}
