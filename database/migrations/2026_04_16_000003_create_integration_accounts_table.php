<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('company_id');
            $table->string('provider');         // shopify|woocommerce
            $table->string('mode');             // oauth|manual
            $table->string('status')->default('disconnected');
            $table->string('shop_domain')->nullable();
            $table->text('credentials');        // encrypted JSON
            $table->jsonb('scopes')->default('[]');
            $table->jsonb('meta')->default('{}');
            $table->text('error_message')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')->on('companies')
                ->cascadeOnDelete();

            $table->unique(['company_id', 'provider']);
            $table->index(['status']);
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('company_id')->nullable();
            $table->foreignId('integration_account_id')->nullable();
            $table->string('provider');
            $table->string('event');              // sync.start|sync.page|webhook.order.created|...
            $table->string('level')->default('info'); // info|warn|error
            $table->text('message')->nullable();
            $table->jsonb('context')->default('{}');
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['provider', 'event']);
            $table->index(['level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integration_accounts');
    }
};
