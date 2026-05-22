<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Employee-level PF/ESI opt-out ──
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('pf_applicable')->default(true);
            $table->boolean('esi_applicable')->default(true);
            $table->string('pf_number', 30)->nullable();  // individual PF member ID
        });

        // ── Workers (कर्मचारी / श्रमिक) — Hindi bio-data based ──
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('worker_id', 20)->unique();   // WRK-001

            // Bio-data fields (as per uploaded form)
            $table->string('name', 120);                  // नाम (Name in Capital Letters)
            $table->string('father_husband_name', 120)->nullable(); // पिता/पति का नाम
            $table->date('date_of_birth')->nullable();     // जन्म तिथि
            $table->integer('age')->nullable();            // आयु
            $table->text('permanent_address')->nullable(); // स्थाई पता
            $table->text('local_address')->nullable();     // स्थानीय पता
            $table->string('education', 200)->nullable();  // शैक्षणिक अहर्ताएं
            $table->string('technical_qualification', 200)->nullable(); // तकनीकी अहर्ताएं
            $table->string('languages', 200)->nullable();  // भाषाएं (लिखना एवं पढ़ना)

            // Identity documents
            $table->string('mobile', 20)->nullable();
            $table->string('pan_number', 10)->nullable();
            $table->string('aadhaar_number', 12)->nullable();
            $table->string('pf_uan', 20)->nullable();     // PF No. / UAN

            // Appointment details
            $table->string('post_applied', 100)->nullable();  // अभ्यर्थिक पद
            $table->string('post_held', 100)->nullable();     // भारित पद (current/assigned post)
            $table->date('appointment_from')->nullable();
            $table->date('appointment_to')->nullable();       // for temporary appointments
            $table->string('appointment_type', 20)->default('temporary'); // temporary, permanent, contract

            // Compensation
            $table->decimal('daily_wage', 10, 2)->default(0);    // दैनिक दर
            $table->decimal('monthly_wage', 10, 2)->default(0);  // मासिक दर
            $table->string('payment_mode', 20)->default('daily'); // daily, monthly, piece_rate
            $table->string('currency', 3)->default('INR');

            // Statutory
            $table->boolean('pf_applicable')->default(false);
            $table->boolean('esi_applicable')->default(false);
            $table->string('pf_number', 30)->nullable();
            $table->string('esi_number', 30)->nullable();

            // Experience (JSON array)
            $table->jsonb('experience')->default('[]');  // [{employer, post, from, to, salary, reason_leaving}]

            // References
            $table->jsonb('references')->default('[]');  // [{name, address, designation}]

            // Status
            $table->string('status', 15)->default('active'); // active, terminated, absconded, completed
            $table->date('date_of_leaving')->nullable();
            $table->string('reason_leaving', 200)->nullable();

            // Bank
            $table->string('bank_name', 80)->nullable();
            $table->string('bank_account_number', 30)->nullable();
            $table->string('bank_ifsc', 15)->nullable();

            $table->text('notes')->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['appointment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['pf_applicable', 'esi_applicable', 'pf_number']);
        });
    }
};
