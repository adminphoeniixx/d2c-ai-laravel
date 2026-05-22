<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Company-level PF & ESI settings (central schema) ──
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('pf_enabled')->default(true);
            $table->decimal('pf_employee_rate', 5, 2)->default(12.00);   // %
            $table->decimal('pf_employer_rate', 5, 2)->default(12.00);   // %
            $table->decimal('pf_basic_cap', 10, 2)->default(15000.00);   // max basic for PF calc
            $table->boolean('esi_enabled')->default(true);
            $table->decimal('esi_employee_rate', 5, 2)->default(0.75);   // %
            $table->decimal('esi_employer_rate', 5, 2)->default(3.25);   // %
            $table->decimal('esi_gross_threshold', 10, 2)->default(21000.00);
            $table->decimal('pt_amount', 8, 2)->default(200.00);         // Professional Tax per month
            $table->string('pf_establishment_code', 30)->nullable();     // PF est. code
            $table->string('esi_establishment_code', 30)->nullable();    // ESI est. code
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'pf_enabled', 'pf_employee_rate', 'pf_employer_rate', 'pf_basic_cap',
                'esi_enabled', 'esi_employee_rate', 'esi_employer_rate', 'esi_gross_threshold',
                'pt_amount', 'pf_establishment_code', 'esi_establishment_code',
            ]);
        });
    }
};
