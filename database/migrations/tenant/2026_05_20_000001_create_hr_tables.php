<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 20)->unique();  // EMP-001
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();  // male, female, other

            // Employment details
            $table->string('designation', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->date('date_of_joining')->nullable();
            $table->date('date_of_leaving')->nullable();
            $table->string('employment_type', 20)->default('full_time');  // full_time, part_time, contract, intern
            $table->string('status', 15)->default('active');  // active, on_notice, terminated, resigned

            // Compensation
            $table->decimal('ctc_annual', 12, 2)->default(0);  // Cost to company
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('hra', 12, 2)->default(0);
            $table->decimal('special_allowance', 12, 2)->default(0);
            $table->decimal('other_allowance', 12, 2)->default(0);

            // Bank & statutory
            $table->string('bank_name', 80)->nullable();
            $table->string('bank_account_number', 30)->nullable();
            $table->string('bank_ifsc', 15)->nullable();
            $table->string('pan_number', 10)->nullable();
            $table->string('aadhaar_number', 12)->nullable();
            $table->string('uan_number', 20)->nullable();  // PF UAN
            $table->string('esi_number', 20)->nullable();

            // Address
            $table->text('address')->nullable();
            $table->string('city', 60)->nullable();
            $table->string('state', 60)->nullable();
            $table->string('pincode', 10)->nullable();

            // Emergency contact
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 30)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);  // "Appointment Letter", "Warning Letter"
            $table->string('type', 30);   // appointment, warning, full_and_final, custom
            $table->text('body');          // HTML content with {{placeholders}}
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('letter_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);       // appointment, warning, full_and_final, custom
            $table->string('title', 200);
            $table->text('body');              // Final rendered HTML
            $table->string('status', 15)->default('draft');  // draft, issued
            $table->date('issued_at')->nullable();
            $table->integer('issued_by')->nullable();  // user id
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_templates');
        Schema::dropIfExists('employees');
    }
};
