<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add marketplace source tracking to orders
        Schema::table('orders', function (Blueprint $table) {
            $table->string('marketplace', 30)->nullable()->after('provider'); // amazon, flipkart, myntra, nykaa
            $table->string('marketplace_order_id')->nullable()->after('marketplace'); // marketplace-specific order ID
            $table->string('channel_sku')->nullable()->after('marketplace_order_id'); // marketplace SKU
            $table->decimal('marketplace_commission', 12, 2)->default(0)->after('total_amount');
            $table->decimal('marketplace_fees', 12, 2)->default(0)->after('marketplace_commission');
            $table->decimal('net_amount', 12, 2)->default(0)->after('marketplace_fees'); // total - commission - fees
        });

        // Marketplace credentials stored per-tenant
        Schema::create('marketplace_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace', 30); // amazon, flipkart, myntra, nykaa
            $table->string('status', 15)->default('disconnected'); // connected, disconnected, error
            $table->jsonb('credentials')->default('{}'); // encrypted API keys, tokens, etc.
            $table->jsonb('settings')->default('{}');     // marketplace-specific settings
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['marketplace']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_credentials');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'marketplace', 'marketplace_order_id', 'channel_sku',
                'marketplace_commission', 'marketplace_fees', 'net_amount',
            ]);
        });
    }
};
