<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('external_id');
            $table->string('provider');
            $table->string('sku')->nullable();
            $table->string('name');
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->string('status')->default('active');
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->integer('inventory_quantity')->default(0);
            $table->jsonb('tags')->default('[]');
            $table->jsonb('meta')->default('{}');
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index(['sku']);
            $table->index(['status']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category');        // ads|payroll|inventory|shipping|tools|rent|other
            $table->string('source')->default('manual'); // manual|voice|auto
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->timestamp('occurred_at');
            $table->unsignedBigInteger('recorded_by_user_id')->nullable();
            $table->text('voice_transcript')->nullable();
            $table->jsonb('meta')->default('{}');
            $table->timestamps();

            $table->index(['category']);
            $table->index(['occurred_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('products');
    }
};
