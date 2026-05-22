<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Ad Campaigns (synced from Meta/Google) ─────
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 15);          // meta, google
            $table->string('external_id', 60);        // platform campaign ID
            $table->string('name', 255);
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE, PAUSED, DELETED
            $table->string('objective', 60)->nullable();
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('lifetime_budget', 12, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->jsonb('meta')->default('{}');
            $table->timestamps();

            $table->unique(['platform', 'external_id']);
        });

        // ── Daily Ad Spend (granular per-campaign per-day) ──
        Schema::create('ad_spend_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 15);
            $table->date('date');
            $table->decimal('spend', 12, 2)->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('conversion_value', 12, 2)->default(0);
            $table->decimal('cpm', 10, 2)->default(0);
            $table->decimal('cpc', 10, 2)->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('roas', 8, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->jsonb('meta')->default('{}');
            $table->timestamps();

            $table->unique(['ad_campaign_id', 'date']);
            $table->index(['platform', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_spend_daily');
        Schema::dropIfExists('ad_campaigns');
    }
};
