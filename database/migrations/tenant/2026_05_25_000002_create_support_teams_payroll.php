<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ══════════════════════════════════════════════
        // 1. SUPPORT TICKET SYSTEM
        // ══════════════════════════════════════════════

        Schema::create('support_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->text('auto_reply')->nullable(); // bot auto-reply for this category
            $table->integer('sla_hours')->default(24); // SLA response time
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 20)->unique(); // TKT-000001
            $table->string('subject', 200);
            $table->text('description');
            $table->string('status', 20)->default('open'); // open, in_progress, awaiting_reply, resolved, closed
            $table->string('priority', 15)->default('medium'); // low, medium, high, urgent
            $table->string('source', 20)->default('portal'); // portal, email, phone, whatsapp
            $table->foreignId('category_id')->nullable()->constrained('support_categories')->nullOnDelete();

            // Customer info (not necessarily a user)
            $table->string('customer_name', 120);
            $table->string('customer_email', 120);
            $table->string('customer_phone', 20)->nullable();
            $table->string('order_number', 60)->nullable(); // link to order

            // Agent assignment
            $table->unsignedBigInteger('assigned_to')->nullable(); // user_id
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // SLA tracking
            $table->integer('sla_hours')->default(24);
            $table->boolean('sla_breached')->default(false);

            $table->jsonb('tags')->default('[]');
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['customer_email']);
            $table->index(['assigned_to']);
        });

        Schema::create('support_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->text('body');
            $table->string('sender_type', 15); // customer, agent, bot
            $table->string('sender_name', 120);
            $table->string('sender_email', 120)->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // agent user_id
            $table->boolean('is_internal_note')->default(false);
            $table->jsonb('attachments')->default('[]');
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('support_faq', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('support_categories')->nullOnDelete();
            $table->string('question', 300);
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('helpful_count')->default(0);
            $table->timestamps();
        });

        // ══════════════════════════════════════════════
        // 2. TEAM INVITATIONS
        // ══════════════════════════════════════════════

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 120);
            $table->string('role', 30)->default('staff');
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('invited_by');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'token']);
        });

        // ══════════════════════════════════════════════
        // 3. PAYROLL ENHANCEMENTS
        // ══════════════════════════════════════════════

        // Add late penalty and leave deductions to payslips
        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('late_deductions', 10, 2)->default(0)->after('other_deductions');
            $table->decimal('absent_deductions', 10, 2)->default(0)->after('late_deductions');
            $table->decimal('lwp_deductions', 10, 2)->default(0)->after('absent_deductions');
            $table->integer('late_count')->default(0)->after('days_absent');
            $table->decimal('half_days', 5, 1)->default(0)->after('late_count');
            $table->integer('leave_days')->default(0)->after('half_days');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['late_deductions', 'absent_deductions', 'lwp_deductions', 'late_count', 'half_days', 'leave_days']);
        });

        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('support_faq');
        Schema::dropIfExists('support_replies');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_categories');
    }
};
