<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AI\AiInsightsGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class GenerateAiInsights extends Command
{
    protected $signature = 'ai:generate-insights {--company= : Only generate for a single company ID}';
    protected $description = 'Generate AI-powered business insights for each tenant';

    public function handle(): int
    {
        $companies = Company::query();

        if ($companyId = $this->option('company')) {
            $companies->where('id', $companyId);
        }

        $generator = new AiInsightsGenerator();
        $ok = 0;
        $failed = 0;

        foreach ($companies->get() as $company) {
            try {
                tenancy()->initialize($company);

                if (!Schema::hasTable('ai_insights')) {
                    $this->warn("Skipping {$company->id} ({$company->name}): ai_insights table not migrated yet");
                    tenancy()->end();
                    continue;
                }

                $generator->generateAndStore($company->name);
                $ok++;
                $this->info("Generated insights for {$company->name} ({$company->id})");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Failed for {$company->id} ({$company->name}): " . $e->getMessage());
            } finally {
                tenancy()->end();
            }
        }

        $this->info("Done. {$ok} succeeded, {$failed} failed.");

        return self::SUCCESS;
    }
}
