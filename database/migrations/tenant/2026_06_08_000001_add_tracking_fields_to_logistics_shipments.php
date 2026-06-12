<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('logistics_shipments', 'origin_pincode')) {
                $table->string('origin_pincode', 10)->nullable()->after('zone');
            }
            if (!Schema::hasColumn('logistics_shipments', 'dest_pincode')) {
                $table->string('dest_pincode', 10)->nullable()->after('origin_pincode');
            }
            if (!Schema::hasColumn('logistics_shipments', 'delivery_days')) {
                $table->integer('delivery_days')->nullable()->after('dest_pincode');
            }
            if (!Schema::hasColumn('logistics_shipments', 'is_rto')) {
                $table->boolean('is_rto')->default(false)->after('delivery_days');
            }
            if (!Schema::hasColumn('logistics_shipments', 'last_scan')) {
                $table->text('last_scan')->nullable()->after('is_rto');
            }
            if (!Schema::hasColumn('logistics_shipments', 'last_scan_at')) {
                $table->timestamp('last_scan_at')->nullable()->after('last_scan');
            }
            if (!Schema::hasColumn('logistics_shipments', 'tracking_data')) {
                $table->jsonb('tracking_data')->nullable()->after('last_scan_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('logistics_shipments', function (Blueprint $table) {
            $table->dropColumn(['origin_pincode', 'dest_pincode', 'delivery_days', 'is_rto', 'last_scan', 'last_scan_at', 'tracking_data']);
        });
    }
};
