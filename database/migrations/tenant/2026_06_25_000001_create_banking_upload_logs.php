<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('banking_upload_logs', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->nullable();
            $table->string('file_type')->nullable();           // csv, pdf, xlsx
            $table->integer('file_size')->nullable();          // bytes
            $table->string('status');                          // success|failed|partial
            $table->string('bank_detected')->nullable();       // HDFC, ICICI, etc.
            $table->string('bank_format')->nullable();         // hdfc, icici, generic
            $table->integer('rows_found')->default(0);         // raw rows in file
            $table->integer('transactions_parsed')->default(0);// parsed by rule-based
            $table->integer('transactions_imported')->default(0);
            $table->integer('transactions_skipped')->default(0);// duplicates
            $table->text('error_message')->nullable();         // what went wrong
            $table->text('file_preview')->nullable();          // first 500 chars of file
            $table->jsonb('ai_request')->nullable();           // what we sent to AI
            $table->jsonb('ai_response')->nullable();          // what AI returned
            $table->string('ai_status')->nullable();           // success|failed|skipped
            $table->integer('ai_transactions')->nullable();    // how many AI returned
            $table->jsonb('parse_steps')->nullable();          // step-by-step trace
            $table->jsonb('sample_transactions')->nullable();  // first 3 parsed transactions
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('banking_upload_logs');
    }
};
