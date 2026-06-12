<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('subject');
            $table->longText('html_body');
            $table->text('text_body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('variables')->nullable();
            $table->timestamps();
        });

        // Add Brevo settings (skip if exists)
        foreach ([
            'brevo_api_key'      => '',
            'brevo_sender_email' => 'noreply@heyd2c.ai',
            'brevo_sender_name'  => 'heyd2c',
            'emails_enabled'     => '1',
        ] as $key => $value) {
            if (!DB::table('payment_settings')->where('key', $key)->exists()) {
                DB::table('payment_settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $now = now();
        $base = 'body{font-family:-apple-system,sans-serif;background:#0f0f14;color:#e2e8f0;margin:0;padding:0}.c{max-width:560px;margin:40px auto;background:#1a1a2e;border-radius:16px;overflow:hidden}.h{padding:40px 32px;text-align:center}.h h1{color:white;margin:0;font-size:24px;font-weight:800}.h p{color:rgba(255,255,255,0.8);margin:8px 0 0;font-size:14px}.b{padding:32px}.b p{color:#94a3b8;line-height:1.7;font-size:14px;margin:0 0 16px}.btn{display:inline-block;background:#7c3aed;color:white;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;font-size:14px;margin:16px 0}.row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:13px}.box{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:16px;margin:16px 0}.f{padding:20px 32px;border-top:1px solid rgba(255,255,255,0.05);text-align:center}.f p{color:#475569;font-size:12px;margin:0}';

        DB::table('email_templates')->insert([
            [
                'slug'      => 'register',
                'name'      => 'Welcome / Registration',
                'subject'   => 'Welcome to heyd2c, {{company_name}}! 🎉',
                'variables' => '["company_name","owner_name","login_url","dashboard_url"]',
                'is_active' => true,
                'html_body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'.$base.' .h{background:linear-gradient(135deg,#7c3aed,#4f46e5)}</style></head><body><div class="c"><div class="h"><h1>heyd2c</h1><p>D2C Operations Platform</p></div><div class="b"><p>Hi <strong style="color:#e2e8f0">{{owner_name}}</strong>,</p><p>Welcome! Your account for <strong style="color:#e2e8f0">{{company_name}}</strong> is ready. heyd2c brings all your D2C operations into one place.</p><p style="text-align:center"><a href="{{dashboard_url}}" class="btn">Go to Dashboard →</a></p><p>You are on the <strong style="color:#e2e8f0">Free plan</strong> with 3,000 lifetime orders included.</p></div><div class="f"><p>© 2026 heyd2c · <a href="{{login_url}}" style="color:#7c3aed">Login</a></p></div></div></body></html>',
                'created_at'=> $now, 'updated_at' => $now,
            ],
            [
                'slug'      => 'subscription_activated',
                'name'      => 'Subscription Activated',
                'subject'   => '🎉 Your {{plan_name}} plan is now active!',
                'variables' => '["company_name","owner_name","plan_name","billing_cycle","amount","next_renewal","dashboard_url","invoice_number"]',
                'is_active' => true,
                'html_body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'.$base.' .h{background:linear-gradient(135deg,#059669,#047857)}</style></head><body><div class="c"><div class="h"><h1>Subscription Active ✓</h1><p>{{plan_name}} · {{company_name}}</p></div><div class="b"><p>Hi <strong style="color:#e2e8f0">{{owner_name}}</strong>, your <strong style="color:#e2e8f0">{{plan_name}}</strong> plan is now active!</p><div class="box"><div class="row"><span style="color:#64748b">Plan</span><span style="color:#e2e8f0;font-weight:500">{{plan_name}}</span></div><div class="row"><span style="color:#64748b">Billing</span><span style="color:#e2e8f0;font-weight:500">{{billing_cycle}}</span></div><div class="row"><span style="color:#64748b">Amount</span><span style="color:#e2e8f0;font-weight:500">{{amount}}</span></div><div class="row"><span style="color:#64748b">Renews</span><span style="color:#e2e8f0;font-weight:500">{{next_renewal}}</span></div><div class="row" style="border:none"><span style="color:#64748b">Invoice</span><span style="color:#e2e8f0;font-weight:500">{{invoice_number}}</span></div></div><p style="text-align:center"><a href="{{dashboard_url}}" class="btn">Go to Dashboard →</a></p></div><div class="f"><p>© 2026 heyd2c</p></div></div></body></html>',
                'created_at'=> $now, 'updated_at' => $now,
            ],
            [
                'slug'      => 'subscription_expiring',
                'name'      => 'Subscription Expiring Soon',
                'subject'   => '⚠️ Your heyd2c subscription expires in {{days_left}} days',
                'variables' => '["company_name","owner_name","plan_name","expiry_date","days_left","renew_url"]',
                'is_active' => true,
                'html_body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'.$base.' .h{background:linear-gradient(135deg,#d97706,#b45309)} .days{font-size:40px;font-weight:800;color:#f59e0b} .wbox{background:rgba(217,119,6,0.1);border:1px solid rgba(217,119,6,0.3);border-radius:10px;padding:16px;margin:16px 0;text-align:center}</style></head><body><div class="c"><div class="h"><h1>Subscription Expiring</h1><p>{{plan_name}} · {{company_name}}</p></div><div class="b"><p>Hi <strong style="color:#e2e8f0">{{owner_name}}</strong>, your subscription is expiring soon.</p><div class="wbox"><div class="days">{{days_left}}</div><p style="color:#94a3b8;font-size:13px;margin:4px 0 0">days remaining · Expires {{expiry_date}}</p></div><p>Renew now to avoid interruption to your operations.</p><p style="text-align:center"><a href="{{renew_url}}" style="background:#d97706;color:white;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;font-size:14px;display:inline-block;margin:16px 0">Renew Subscription →</a></p></div><div class="f"><p>© 2026 heyd2c</p></div></div></body></html>',
                'created_at'=> $now, 'updated_at' => $now,
            ],
            [
                'slug'      => 'subscription_expired',
                'name'      => 'Subscription Expired',
                'subject'   => '🔴 Your heyd2c subscription has expired',
                'variables' => '["company_name","owner_name","plan_name","expired_date","upgrade_url"]',
                'is_active' => true,
                'html_body' => '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'.$base.' .h{background:linear-gradient(135deg,#dc2626,#991b1b)} .ebox{background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.25);border-radius:10px;padding:16px;margin:16px 0}</style></head><body><div class="c"><div class="h"><h1>Subscription Expired</h1><p>{{plan_name}} · Expired {{expired_date}}</p></div><div class="b"><p>Hi <strong style="color:#e2e8f0">{{owner_name}}</strong>, your subscription for <strong style="color:#e2e8f0">{{company_name}}</strong> has expired.</p><div class="ebox"><p style="color:#fca5a5;font-size:13px;margin:0">Your account has been downgraded to Free. All data is safe. Upgrade to restore full functionality.</p></div><p style="text-align:center"><a href="{{upgrade_url}}" class="btn">Reactivate Subscription →</a></p></div><div class="f"><p>© 2026 heyd2c</p></div></div></body></html>',
                'created_at'=> $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
