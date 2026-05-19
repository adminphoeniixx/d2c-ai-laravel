<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('external_id');
            $table->string('provider');                  // shopify|woocommerce
            $table->string('order_number');
            $table->string('status')->default('pending');
            $table->string('financial_status')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->string('currency', 3)->default('INR');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('total_shipping', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();

            $table->jsonb('shipping_address')->nullable();
            $table->jsonb('billing_address')->nullable();

            $table->unsignedInteger('line_item_count')->default(0);
            $table->jsonb('tags')->default('[]');
            $table->jsonb('raw_payload')->default('{}');

            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index(['placed_at']);
            $table->index(['status']);
            $table->index(['customer_email']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
