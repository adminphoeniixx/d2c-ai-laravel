<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add face fields to employees
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'has_face')) {
                $table->boolean('has_face')->default(false)->after('notes');
            }
            if (!Schema::hasColumn('employees', 'face_registered_at')) {
                $table->timestamp('face_registered_at')->nullable()->after('has_face');
            }
            if (!Schema::hasColumn('employees', 'face_updated_at')) {
                $table->timestamp('face_updated_at')->nullable()->after('face_registered_at');
            }
        });

        // Add client_log_id to punches for offline dedup
        Schema::table('punches', function (Blueprint $table) {
            if (!Schema::hasColumn('punches', 'client_log_id')) {
                $table->string('client_log_id', 60)->nullable()->unique()->after('note');
            }
            if (!Schema::hasColumn('punches', 'kiosk_id')) {
                $table->string('kiosk_id', 30)->nullable()->after('source');
            }
        });

        // Add kiosk_id to attendances
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'kiosk_id')) {
                $table->string('kiosk_id', 30)->nullable()->after('source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['has_face', 'face_registered_at', 'face_updated_at']);
        });
        Schema::table('punches', function (Blueprint $table) {
            $table->dropColumn(['client_log_id', 'kiosk_id']);
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('kiosk_id');
        });
    }
};
