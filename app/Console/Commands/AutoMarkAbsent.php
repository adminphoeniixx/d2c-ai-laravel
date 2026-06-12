<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AutoMarkAbsent extends Command
{
    protected $signature = 'attendance:auto-absent {--date= : Specific date (Y-m-d), defaults to today}';
    protected $description = 'Auto-mark absent for employees who did not check in (excludes weekends & holidays)';

    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        foreach (Company::all() as $company) {
            $schema = 'tenant_' . $company->id;

            try {
                DB::connection('tenant')->statement("SET search_path TO \"{$schema}\", public");

                $service = new AttendanceService();
                $count = $service->autoMarkAbsent($date);

                if ($count > 0) {
                    $this->info("  {$company->name}: {$count} marked absent");
                }
            } catch (\Throwable $e) {
                $this->warn("  {$company->name}: " . $e->getMessage());
            }
        }

        DB::connection('tenant')->statement("SET search_path TO public");
        $this->info('Done.');
        return 0;
    }
}
