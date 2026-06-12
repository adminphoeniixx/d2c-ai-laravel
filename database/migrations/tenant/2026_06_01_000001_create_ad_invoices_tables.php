<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 15);           // meta, google, other
            $table->string('invoice_number', 60)->nullable();
            $table->date('invoice_date');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('file_url')->nullable();
            $table->string('status', 15)->default('uploaded'); // uploaded, verified
            $table->integer('entry_count')->default(0);
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();
        });

        // Manual ad spend entries (not linked to campaigns)
        Schema::create('ad_spend_manual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_invoice_id')->nullable()->constrained('ad_invoices')->nullOnDelete();
            $table->string('platform', 15);
            $table->date('date');
            $table->string('campaign_name', 255)->nullable();
            $table->decimal('spend', 12, 2)->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('conversion_value', 12, 2)->default(0);
            $table->string('source', 15)->default('manual'); // manual, csv
            $table->jsonb('raw_data')->default('{}');
            $table->timestamps();

            $table->index(['platform', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_spend_manual');
        Schema::dropIfExists('ad_invoices');
    }
};
