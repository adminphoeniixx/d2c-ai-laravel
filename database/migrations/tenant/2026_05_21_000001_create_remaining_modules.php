<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Attendance / Working Hours ──────────────────
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->decimal('worked_hours', 5, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->string('status', 15)->default('present'); // present, absent, half_day, leave, holiday
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
        });

        // ── Payroll ────────────────────────────────────
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7);    // 2026-05
            $table->string('status', 15)->default('draft'); // draft, processed, paid
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->integer('employee_count')->default(0);
            $table->date('processed_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->integer('processed_by')->nullable();
            $table->timestamps();

            $table->unique(['month']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);

            // Earnings
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('hra', 12, 2)->default(0);
            $table->decimal('special_allowance', 12, 2)->default(0);
            $table->decimal('other_allowance', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('gross_salary', 12, 2)->default(0);

            // Deductions
            $table->decimal('pf_employee', 12, 2)->default(0);   // 12% of basic
            $table->decimal('pf_employer', 12, 2)->default(0);   // 12% of basic
            $table->decimal('esi_employee', 12, 2)->default(0);  // 0.75% of gross
            $table->decimal('esi_employer', 12, 2)->default(0);  // 3.25% of gross
            $table->decimal('professional_tax', 12, 2)->default(0);
            $table->decimal('tds', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);

            $table->decimal('net_salary', 12, 2)->default(0);

            // Attendance summary
            $table->integer('working_days')->default(0);
            $table->integer('days_present')->default(0);
            $table->integer('days_absent')->default(0);
            $table->decimal('total_overtime_hours', 6, 2)->default(0);
            $table->decimal('overtime_rate', 10, 2)->default(0);

            $table->string('status', 15)->default('draft'); // draft, approved, paid
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        // ── Purchase Orders ────────────────────────────
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 180)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 60)->nullable();
            $table->string('state', 60)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('contact_person', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 30)->unique();   // PO-001
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('status', 15)->default('draft'); // draft, sent, partial, received, cancelled
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->date('received_date')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('product_name', 200);
            $table->string('sku', 60)->nullable();
            $table->string('hsn_code', 10)->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('received_quantity')->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->timestamps();
        });

        // ── Inventory ──────────────────────────────────
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('sku', 60)->unique();
            $table->string('category', 60)->nullable();
            $table->string('unit', 20)->default('pcs');  // pcs, kg, litre, box
            $table->integer('quantity')->default(0);
            $table->integer('min_stock_level')->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->string('location', 100)->nullable();  // warehouse location
            $table->string('status', 15)->default('active'); // active, discontinued
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 15);  // in, out, adjustment
            $table->integer('quantity');  // positive or negative
            $table->integer('balance_after')->default(0);
            $table->string('reference_type', 30)->nullable(); // purchase_order, order, manual
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });

        // ── Employee Documents (Bunny CDN) ─────────────
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('type', 30);  // aadhaar, pan, resume, offer_letter, id_proof, other
            $table->string('file_name', 255);
            $table->string('file_url', 500);  // Bunny CDN URL
            $table->string('file_size', 20)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('attendances');
    }
};
