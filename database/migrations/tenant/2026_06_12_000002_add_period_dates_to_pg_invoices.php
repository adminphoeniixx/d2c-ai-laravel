<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pg_invoices', 'period_start')) {
            Schema::table('pg_invoices', function (Blueprint $table) {
                $table->date('period_start')->nullable()->after('period');
                $table->date('period_end')->nullable()->after('period_start');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pg_invoices', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'period_end']);
        });
    }
};
