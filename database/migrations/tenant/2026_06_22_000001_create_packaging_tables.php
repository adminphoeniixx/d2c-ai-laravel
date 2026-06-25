<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        // Packaging inventory — boxes, tape, bubble wrap, labels, etc.
        Schema::connection('tenant')->create('packaging_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('unit')->default('pcs');
            $table->integer('quantity')->default(0);
            $table->integer('min_stock_level')->default(10);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->string('location')->nullable();
            $table->string('status')->default('active'); // active|discontinued
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Packaging purchase orders — separate from product POs
        Schema::connection('tenant')->create('packaging_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->string('supplier_name')->nullable(); // free text, no vendor FK
            $table->string('status')->default('draft'); // draft|sent|received|cancelled
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->date('received_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Line items on a packaging PO
        Schema::connection('tenant')->create('packaging_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packaging_order_id')->constrained('packaging_orders')->cascadeOnDelete();
            $table->foreignId('packaging_item_id')->nullable()->constrained('packaging_items')->nullOnDelete();
            $table->string('item_name'); // denormalized so deleting item doesn't lose history
            $table->string('unit')->default('pcs');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('packaging_order_items');
        Schema::connection('tenant')->dropIfExists('packaging_orders');
        Schema::connection('tenant')->dropIfExists('packaging_items');
    }
};
