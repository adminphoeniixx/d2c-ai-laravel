<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Attendance Settings (one row per tenant) ──────────
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            // Shift defaults
            $table->time('shift_start')->default('09:00');
            $table->time('shift_end')->default('18:00');
            $table->decimal('standard_hours', 4, 2)->default(8.00);
            $table->decimal('lunch_break_hours', 3, 2)->default(1.00);

            // Late policy
            $table->integer('late_threshold_minutes')->default(15);       // after X mins = late
            $table->integer('half_day_threshold_minutes')->default(120);  // after X mins = half day
            $table->string('late_penalty_type', 20)->default('fixed');    // fixed, per_minute, per_day_salary
            $table->decimal('late_penalty_amount', 10, 2)->default(0);    // ₹ amount for fixed
            $table->decimal('late_penalty_per_minute', 8, 2)->default(0); // ₹ per minute late
            $table->integer('late_grace_count')->default(3);              // free lates per month before penalty

            // Overtime
            $table->decimal('overtime_rate_multiplier', 4, 2)->default(1.50); // 1.5x pay
            $table->integer('overtime_min_minutes')->default(30);             // min OT minutes to count

            // Geo & face
            $table->decimal('geo_fence_latitude', 10, 7)->nullable();
            $table->decimal('geo_fence_longitude', 10, 7)->nullable();
            $table->integer('geo_fence_radius_meters')->default(200);
            $table->boolean('geo_fence_enabled')->default(false);
            $table->boolean('face_recognition_required')->default(false);

            // Auto-absent
            $table->boolean('auto_mark_absent')->default(true);
            $table->time('auto_absent_after')->default('12:00'); // if no check-in by noon, mark absent

            $table->timestamps();
        });

        // ── Work Schedules (weekly pattern) ──────────────────
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->integer('day_of_week');       // 0=Sunday, 1=Monday ... 6=Saturday
            $table->boolean('is_working_day')->default(true);
            $table->time('shift_start')->nullable();  // override per day (null = use default)
            $table->time('shift_end')->nullable();
            $table->string('label', 30)->nullable();  // "Mon", "Tue", etc.
            $table->timestamps();

            $table->unique(['day_of_week']);
        });

        // ── Holidays ─────────────────────────────────────────
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name', 120);
            $table->string('type', 20)->default('company');  // national, company, optional, restricted
            $table->boolean('is_paid')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['date']);
        });

        // ── Leave Types ──────────────────────────────────────
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);           // Casual Leave, Sick Leave, Earned Leave, Maternity
            $table->string('code', 10);            // CL, SL, EL, ML
            $table->boolean('is_paid')->default(true);
            $table->integer('annual_quota')->default(12);    // days per year
            $table->boolean('carry_forward')->default(false);
            $table->integer('max_carry_forward_days')->default(0);
            $table->integer('max_consecutive_days')->default(3);  // max consecutive days
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ── Leave Balances (per employee per year) ───────────
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->integer('year');
            $table->decimal('allocated', 5, 1)->default(0);    // total for the year
            $table->decimal('used', 5, 1)->default(0);
            $table->decimal('carried_forward', 5, 1)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        // ── Leave Requests ───────────────────────────────────
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 4, 1);            // supports half-days (0.5)
            $table->text('reason')->nullable();
            $table->string('status', 15)->default('pending'); // pending, approved, rejected, cancelled
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['from_date', 'to_date']);
        });

        // ── Enhance existing attendances table ───────────────
        Schema::table('attendances', function (Blueprint $table) {
            $table->integer('late_minutes')->default(0)->after('overtime_hours');
            $table->boolean('is_late')->default(false)->after('late_minutes');
            $table->decimal('penalty_amount', 10, 2)->default(0)->after('is_late');
            $table->jsonb('check_in_location')->nullable()->after('penalty_amount');   // {lat, lng, accuracy}
            $table->jsonb('check_out_location')->nullable()->after('check_in_location');
            $table->boolean('face_verified')->default(false)->after('check_out_location');
            $table->string('source', 15)->default('manual')->after('face_verified');   // app, manual, bulk, auto
            $table->string('ip_address', 45)->nullable()->after('source');
            $table->foreignId('leave_request_id')->nullable()->after('ip_address');
        });

        // ── Enhance employees for face + shift override ──────
        Schema::table('employees', function (Blueprint $table) {
            $table->text('face_encoding')->nullable();          // base64 face data for recognition
            $table->time('shift_override_start')->nullable();   // custom shift per employee
            $table->time('shift_override_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['face_encoding', 'shift_override_start', 'shift_override_end']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'late_minutes', 'is_late', 'penalty_amount',
                'check_in_location', 'check_out_location',
                'face_verified', 'source', 'ip_address', 'leave_request_id',
            ]);
        });

        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('attendance_settings');
    }
};
