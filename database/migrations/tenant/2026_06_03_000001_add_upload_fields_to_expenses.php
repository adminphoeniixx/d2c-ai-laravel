<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('attachment_path', 500)->nullable()->after('source');
            $table->string('attachment_type', 20)->nullable()->after('attachment_path'); // image, pdf, csv
            $table->jsonb('extracted_data')->nullable()->after('attachment_type');
            $table->jsonb('line_items')->nullable()->after('extracted_data');
            $table->string('vendor', 200)->nullable()->after('line_items');
            $table->text('notes')->nullable()->after('vendor');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_type', 'extracted_data', 'line_items', 'vendor', 'notes']);
        });
    }
};
