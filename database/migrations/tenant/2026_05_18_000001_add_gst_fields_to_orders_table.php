<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('taxable_amount', 12, 2)->default(0)->after('total_amount');
            $table->decimal('cgst_amount', 12, 2)->default(0)->after('taxable_amount');
            $table->decimal('sgst_amount', 12, 2)->default(0)->after('cgst_amount');
            $table->decimal('igst_amount', 12, 2)->default(0)->after('sgst_amount');
            $table->decimal('gst_rate', 5, 2)->nullable()->after('igst_amount');
            $table->string('place_of_supply', 60)->nullable()->after('gst_rate');
            $table->boolean('is_intra_state')->nullable()->after('place_of_supply');
            $table->string('buyer_state_code', 2)->nullable()->after('is_intra_state');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('hsn_code', 10)->nullable()->after('discount_amount');
            $table->decimal('gst_rate', 5, 2)->nullable()->after('hsn_code');
            $table->decimal('taxable_amount', 12, 2)->default(0)->after('gst_rate');
            $table->decimal('cgst_amount', 12, 2)->default(0)->after('taxable_amount');
            $table->decimal('sgst_amount', 12, 2)->default(0)->after('cgst_amount');
            $table->decimal('igst_amount', 12, 2)->default(0)->after('sgst_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
                'gst_rate', 'place_of_supply', 'is_intra_state', 'buyer_state_code',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'hsn_code', 'gst_rate', 'taxable_amount',
                'cgst_amount', 'sgst_amount', 'igst_amount',
            ]);
        });
    }
};
