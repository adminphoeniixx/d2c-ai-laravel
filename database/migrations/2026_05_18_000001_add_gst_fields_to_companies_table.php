<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('gstin', 15)->nullable()->after('email');
            $table->string('registered_state_code', 2)->nullable()->after('gstin');
            $table->string('business_category', 30)->default('other')->after('registered_state_code');
            $table->decimal('default_gst_rate', 5, 2)->default(18.00)->after('business_category');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['gstin', 'registered_state_code', 'business_category', 'default_gst_rate']);
        });
    }
};
