<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->string('id')->primary();                 // UUID (used as schema suffix)
            $table->string('slug')->unique();                // URL identifier
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('status')->default('active');     // active|suspended|pending
            $table->string('plan')->default('free');         // free|pro|enterprise
            $table->string('country', 2)->default('IN');
            $table->string('currency', 3)->default('INR');
            $table->string('timezone', 64)->default('UTC');
            $table->jsonb('settings')->default('{}');

            $table->timestamp('shopify_connected_at')->nullable();
            $table->timestamp('woo_connected_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            // Required by stancl/tenancy to store extra arbitrary data
            $table->jsonb('data')->nullable();

            $table->timestamps();

            $table->index(['status']);
            $table->index(['plan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
