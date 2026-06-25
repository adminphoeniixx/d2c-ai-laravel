<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banking_upload_logs', function (Blueprint $table) {
            $table->id();
            $table->string('company_id')->index();
            $table->string('company_slug')->nullable();
            $table->string('filename')->nullable();
            $table->string('file_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('status');                          // success|failed|empty|skipped
            $table->string('bank_detected')->nullable();
            $table->string('bank_format')->nullable();
            $table->integer('transactions_parsed')->default(0);
            $table->integer('transactions_imported')->default(0);
            $table->integer('transactions_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->text('file_preview')->nullable();
            $table->jsonb('parse_steps')->nullable();
            $table->jsonb('sample_transactions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banking_upload_logs');
    }
};
