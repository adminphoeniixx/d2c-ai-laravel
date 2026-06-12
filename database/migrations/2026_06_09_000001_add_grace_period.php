<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add grace period settings to payment_settings
        foreach ([
            'grace_period_days'      => '7',   // days after limit before hard block
            'grace_email_day_1'      => '1',   // send email on day 1 of grace
            'grace_email_day_3'      => '1',   // send email on day 3
            'grace_email_day_7'      => '1',   // send email on day 7 (last day)
            'limit_warning_pct'      => '90',  // show warning at 90% usage
        ] as $key => $value) {
            if (!DB::table('payment_settings')->where('key', $key)->exists()) {
                DB::table('payment_settings')->insert([
                    'key' => $key, 'value' => $value,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // Add grace_period_started_at to companies table
        if (!Schema::hasColumn('companies', 'grace_period_started_at')) {
            Schema::table('companies', function ($table) {
                $table->timestamp('grace_period_started_at')->nullable()->after('subscription_status');
            });
        }

        // Add grace period email template
        if (!DB::table('email_templates')->where('slug', 'grace_period_warning')->exists()) {
            $base = 'body{font-family:-apple-system,sans-serif;background:#0f0f14;color:#e2e8f0;margin:0;padding:0}.c{max-width:560px;margin:40px auto;background:#1a1a2e;border-radius:16px;overflow:hidden}.h{padding:40px 32px;text-align:center;background:linear-gradient(135deg,#dc2626,#991b1b)}.h h1{color:white;margin:0;font-size:24px;font-weight:800}.b{padding:32px}.b p{color:#94a3b8;line-height:1.7;font-size:14px;margin:0 0 16px}.box{background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.25);border-radius:10px;padding:16px;margin:16px 0}.days{font-size:48px;font-weight:800;color:#f87171;text-align:center}.btn{display:inline-block;background:#7c3aed;color:white;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;font-size:14px;margin:16px 0}.f{padding:20px 32px;border-top:1px solid rgba(255,255,255,0.05);text-align:center}.f p{color:#475569;font-size:12px;margin:0}';

            DB::table('email_templates')->insert([
                'slug'      => 'grace_period_warning',
                'name'      => 'Grace Period Warning',
                'subject'   => '🚨 {{days_left}} days left — upgrade to keep heyd2c running',
                'variables' => '["company_name","owner_name","days_left","order_count","order_limit","grace_ends_date","upgrade_url"]',
                'is_active' => true,
                'html_body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'.$base.'</style></head><body><div class="c"><div class="h"><h1>Action Required</h1></div><div class="b"><p>Hi <strong style="color:#e2e8f0">{{owner_name}}</strong>,</p><p>Your <strong style="color:#e2e8f0">{{company_name}}</strong> account has exceeded its free order limit.</p><div class="box"><div class="days">{{days_left}}</div><p style="color:#fca5a5;font-size:13px;text-align:center;margin:4px 0 0">days remaining in grace period<br>Grace ends {{grace_ends_date}}</p></div><p>You have used <strong style="color:#f87171">{{order_count}} / {{order_limit}}</strong> free orders. After the grace period ends, new orders will stop syncing and some features will be locked until you upgrade.</p><p style="text-align:center"><a href="{{upgrade_url}}" class="btn">Upgrade Now →</a></p></div><div class="f"><p>© 2026 heyd2c · SaltyPay Software Pvt Ltd</p></div></div></body></html>',
                'created_at'=> now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'grace_period_started_at')) {
            Schema::table('companies', fn($t) => $t->dropColumn('grace_period_started_at'));
        }
    }
};
