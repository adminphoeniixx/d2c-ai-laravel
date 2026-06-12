<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pg_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('gateway')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('period')->nullable();
            $table->decimal('gross_volume',  15, 2)->default(0);
            $table->decimal('total_charges', 15, 2)->default(0);
            $table->decimal('gst_amount',    15, 2)->default(0);
            $table->decimal('net_settled',   15, 2)->default(0);
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pg_invoices');
    }
};
