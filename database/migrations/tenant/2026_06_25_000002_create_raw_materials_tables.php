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
        // Raw material inventory items
        Schema::connection('tenant')->create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('category')->nullable();
            $table->string('unit')->default('kg'); // kg, litre, pcs, metre, gram, etc.
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->decimal('cost_per_unit', 12, 2)->default(0);
            $table->string('supplier')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('active'); // active|discontinued
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Transactions — manual stock in/out entries
        Schema::connection('tenant')->create('raw_material_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->cascadeOnDelete();
            $table->string('type'); // in|out
            $table->decimal('quantity', 12, 3);
            $table->decimal('cost_per_unit', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->date('transaction_date');
            $table->string('reference')->nullable(); // PO number, batch, invoice etc.
            $table->string('reason')->nullable();    // purchase, usage, wastage, adjustment
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('raw_material_transactions');
        Schema::connection('tenant')->dropIfExists('raw_materials');
    }
};
