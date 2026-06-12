<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);           // HDFC Current, ICICI Savings, etc.
            $table->string('account_number', 30)->nullable();
            $table->string('ifsc', 15)->nullable();
            $table->string('bank_name', 60)->nullable();
            $table->string('type', 15)->default('current'); // current, savings, cc
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 10); // credit, debit
            $table->decimal('amount', 14, 2);
            $table->decimal('balance', 14, 2)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('reference', 100)->nullable(); // cheque no, UTR, UPI ref
            $table->string('category', 40)->nullable();   // auto-categorized: salary, vendor, gst, ads, logistics, refund, sales, other
            $table->string('source', 20)->default('import'); // import, manual
            $table->string('upload_batch', 40)->nullable();  // to group by upload
            $table->jsonb('raw_data')->default('{}');
            $table->timestamps();

            $table->index(['bank_account_id', 'date']);
            $table->index(['type', 'date']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
    }
};
