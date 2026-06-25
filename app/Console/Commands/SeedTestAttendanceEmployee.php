<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a test employee (phone: 9999999999, OTP: 123456) into the first
 * available tenant schema. Used for Google Play / App Store demo accounts
 * and QA testing of the attendance app without a real employee.
 *
 * Safe to run multiple times — uses updateOrCreate semantics.
 *
 * Usage:
 *   php artisan attendance:seed-test-employee
 *   php artisan attendance:seed-test-employee --company=ashish-company
 */
class SeedTestAttendanceEmployee extends Command
{
    protected $signature   = 'attendance:seed-test-employee {--company= : Slug of the company to add the test employee to}';
    protected $description = 'Create a test employee (phone 9999999999) for attendance app demo/QA login';

    public function handle(): int
    {
        $slug = $this->option('company');

        if ($slug) {
            $company = DB::table('companies')->where('slug', $slug)->first();
            if (!$company) {
                $this->error("Company not found: {$slug}");
                return 1;
            }
        } else {
            // Default to the first company
            $company = DB::table('companies')->orderBy('created_at')->first();
            if (!$company) {
                $this->error('No companies found. Register a company first.');
                return 1;
            }
        }

        $schema = 'tenant_' . $company->id;

        // Make sure the employees table exists in this tenant
        $exists = DB::select(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'employees' LIMIT 1",
            [$schema]
        );
        if (empty($exists)) {
            $this->error("Tenant schema {$schema} has no employees table. Run migrations first.");
            return 1;
        }

        DB::connection('tenant')->statement("SET search_path TO \"{$schema}\", public");

        $existing = DB::connection('tenant')->table('employees')
            ->where('phone', '9999999999')
            ->first();

        if ($existing) {
            // Update to ensure it's active and has the right data
            DB::connection('tenant')->table('employees')
                ->where('id', $existing->id)
                ->update([
                    'first_name'  => 'Demo',
                    'last_name'   => 'User',
                    'status'      => 'active',
                    'updated_at'  => now(),
                ]);
            $this->info("Test employee already exists (id: {$existing->id}) in {$company->name} — refreshed.");
        } else {
            $id = DB::connection('tenant')->table('employees')->insertGetId([
                'first_name'  => 'Demo',
                'last_name'   => 'User',
                'phone'       => '9999999999',
                'employee_id' => 'DEMO001',
                'designation' => 'Test Account',
                'department'  => 'QA',
                'status'      => 'active',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $this->info("Test employee created (id: {$id}) in {$company->name}.");
        }

        $this->line('');
        $this->line('  Phone : <fg=green>9999999999</>');
        $this->line('  OTP   : <fg=green>123456</>');
        $this->line('  Name  : Demo User');
        $this->line("  Company: {$company->name}");
        $this->line('');
        $this->info('Ready for attendance app login.');

        return 0;
    }
}
