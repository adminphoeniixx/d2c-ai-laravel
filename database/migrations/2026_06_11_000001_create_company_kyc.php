<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_kyc', function (Blueprint $table) {
            $table->id();
            $table->string('company_id')->index();
            $table->string('status')->default('pending');
            $table->string('legal_name')->nullable();
            $table->string('business_type')->nullable();
            $table->string('gstin')->nullable();
            $table->string('pan')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->string('bank_name')->nullable();
            $table->json('documents')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        if (!DB::table('payment_settings')->where('key', 'kyc_required')->exists()) {
            DB::table('payment_settings')->insert([
                'key' => 'kyc_required', 'value' => '0',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_kyc');
    }
};
