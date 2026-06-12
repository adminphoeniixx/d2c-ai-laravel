<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Subscription Plans (admin-managed)
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // Free, Basic, Growth, Scale
            $table->string('slug')->unique();                // free, basic, growth, scale
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);    // Monthly price INR
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->integer('order_limit')->default(3000);   // -1 = unlimited
            $table->integer('store_limit')->default(1);      // -1 = unlimited
            $table->integer('team_member_limit')->default(1);// -1 = unlimited
            $table->integer('data_history_days')->default(30);// -1 = unlimited
            $table->decimal('per_order_charge', 8, 4)->default(0); // after limit
            $table->boolean('is_free')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->jsonb('features')->default('[]');        // feature flags
            $table->string('razorpay_plan_id')->nullable();  // live
            $table->string('razorpay_plan_id_test')->nullable(); // test
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── Subscriptions (per company)
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->string('status');                        // active|cancelled|expired|trial|past_due
            $table->string('billing_cycle')->default('monthly'); // monthly|yearly
            $table->decimal('amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2);
            $table->string('coupon_code')->nullable();
            $table->string('razorpay_subscription_id')->nullable();
            $table->string('razorpay_payment_id')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('status');
        });

        // ── Subscription Invoices
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->string('company_id');
            $table->string('invoice_number')->unique();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('cgst', 10, 2)->default(0);
            $table->decimal('sgst', 10, 2)->default(0);
            $table->decimal('igst', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('status')->default('paid');       // paid|unpaid|refunded
            $table->string('razorpay_payment_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // ── Coupons
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');                          // percent|flat
            $table->decimal('value', 10, 2);                 // % or ₹ amount
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();      // null = unlimited
            $table->integer('usage_count')->default(0);
            $table->integer('per_user_limit')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('first_time_only')->default(false);
            $table->json('applicable_plans')->nullable();    // null = all plans
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();
        });

        // ── Coupon usage log
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->onDelete('cascade');
            $table->string('company_id');
            $table->decimal('discount_applied', 10, 2);
            $table->timestamp('used_at');
            $table->timestamps();
        });

        // ── Notifications
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->string('company_id')->nullable();        // null = broadcast
            $table->string('type');                          // sms|email|in_app
            $table->string('category');                      // subscription|billing|system|feature
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status')->default('pending');    // pending|sent|failed
            $table->string('recipient')->nullable();         // phone or email
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type']);
        });

        // ── Razorpay settings (global)
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default payment settings
        DB::table('payment_settings')->insert([
            ['key' => 'razorpay_mode',          'value' => 'test',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'razorpay_key_id_test',    'value' => '',      'created_at' => now(), 'updated_at' => now()],
            ['key' => 'razorpay_key_secret_test','value' => '',      'created_at' => now(), 'updated_at' => now()],
            ['key' => 'razorpay_key_id_live',    'value' => '',      'created_at' => now(), 'updated_at' => now()],
            ['key' => 'razorpay_key_secret_live','value' => '',      'created_at' => now(), 'updated_at' => now()],
            ['key' => 'gst_rate',                'value' => '18',    'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Add subscription fields to companies (safe - skip existing)
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'active_plan_id'))
                $table->unsignedBigInteger('active_plan_id')->nullable()->after('plan');
            if (!Schema::hasColumn('companies', 'subscription_status'))
                $table->string('subscription_status')->default('free')->after('plan');
            if (!Schema::hasColumn('companies', 'order_count'))
                $table->integer('order_count')->default(0)->after('subscription_status');
            if (!Schema::hasColumn('companies', 'phone'))
                $table->string('phone')->nullable()->after('email');
            if (!Schema::hasColumn('companies', 'business_category'))
                $table->string('business_category')->nullable()->after('phone');
        });

        // ── Add OTP fields to users (safe - skip existing)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone_verified_at'))
                $table->string('phone_verified_at')->nullable()->after('phone');
            if (!Schema::hasColumn('users', 'otp_login_enabled'))
                $table->boolean('otp_login_enabled')->default(true)->after('phone_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'otp_login_enabled')) $t->dropColumn('otp_login_enabled');
            if (Schema::hasColumn('users', 'phone_verified_at')) $t->dropColumn('phone_verified_at');
        });
        Schema::table('companies', function (Blueprint $t) {
            if (Schema::hasColumn('companies', 'active_plan_id'))      $t->dropColumn('active_plan_id');
            if (Schema::hasColumn('companies', 'subscription_status')) $t->dropColumn('subscription_status');
            if (Schema::hasColumn('companies', 'order_count'))         $t->dropColumn('order_count');
        });
        Schema::dropIfExists('payment_settings');
        Schema::dropIfExists('notifications_log');
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
