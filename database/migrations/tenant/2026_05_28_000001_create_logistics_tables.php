<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Delivery Partners (Delhivery, Shiprocket, Ecom Express, etc.)
        Schema::create('delivery_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);               // Delhivery, Shiprocket, BlueDart, etc.
            $table->string('slug', 40)->unique();
            $table->string('api_base_url')->nullable();
            $table->text('api_credentials')->nullable(); // encrypted JSON: token, key, secret
            $table->string('gstin', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('api_connected')->default(false);
            $table->jsonb('settings')->default('{}');    // rate cards, zone mapping, etc.
            $table->timestamps();
        });

        // ── Logistics Invoices (PDF uploads from delivery partners)
        Schema::create('logistics_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_partner_id')->constrained('delivery_partners')->cascadeOnDelete();
            $table->string('invoice_number', 60)->unique();
            $table->date('invoice_date');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('type', 20)->default('freight'); // freight, vas, cod, other
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('cgst', 10, 2)->default(0);
            $table->decimal('sgst', 10, 2)->default(0);
            $table->decimal('igst', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status', 15)->default('uploaded'); // uploaded, verified, disputed
            $table->string('file_url')->nullable();           // PDF on Bunny CDN
            $table->string('csv_url')->nullable();             // CSV on Bunny CDN
            $table->integer('shipment_count')->default(0);
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();
        });

        // ── Shipments / Transactions (line items from CSV)
        Schema::create('logistics_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_partner_id')->constrained('delivery_partners')->cascadeOnDelete();
            $table->foreignId('logistics_invoice_id')->nullable()->constrained('logistics_invoices')->nullOnDelete();
            $table->string('waybill', 30)->index();           // AWB number
            $table->string('order_id', 60)->nullable()->index(); // linked order
            $table->string('status', 20);                     // Delivered, RTO, DTO, In Transit, etc.
            $table->string('payment_mode', 15)->nullable();    // Pre-paid, COD
            $table->string('zone', 10)->nullable();            // A, B, C, C2, etc.
            $table->decimal('product_value', 10, 2)->default(0);
            $table->decimal('cod_amount', 10, 2)->default(0);
            $table->decimal('charged_weight', 8, 2)->default(0);
            $table->string('destination_pin', 10)->nullable();
            $table->string('origin_center', 100)->nullable();

            // Charges breakdown
            $table->decimal('charge_freight', 10, 2)->default(0);   // charge_DL
            $table->decimal('charge_cod', 10, 2)->default(0);
            $table->decimal('charge_rto', 10, 2)->default(0);
            $table->decimal('charge_fuel', 10, 2)->default(0);      // charge_FSC
            $table->decimal('charge_pickup', 10, 2)->default(0);
            $table->decimal('charge_vas', 10, 2)->default(0);       // WhatsApp, label, etc.
            $table->decimal('charge_other', 10, 2)->default(0);     // DPH, QC, CWH, etc.
            $table->decimal('gross_amount', 10, 2)->default(0);
            $table->decimal('cgst', 10, 2)->default(0);
            $table->decimal('sgst', 10, 2)->default(0);
            $table->decimal('igst', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);

            // Delivery tracking
            $table->timestamp('pickup_date')->nullable();
            $table->timestamp('first_delivery_attempt')->nullable(); // fpd
            $table->timestamp('delivered_date')->nullable();         // frd
            $table->timestamp('pdd')->nullable();                    // promised delivery date
            $table->integer('attempt_count')->default(0);            // atc
            $table->string('item_shipped')->nullable();
            $table->integer('qty')->default(1);

            $table->jsonb('raw_data')->default('{}');
            $table->timestamps();

            $table->index(['delivery_partner_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_shipments');
        Schema::dropIfExists('logistics_invoices');
        Schema::dropIfExists('delivery_partners');
    }
};
