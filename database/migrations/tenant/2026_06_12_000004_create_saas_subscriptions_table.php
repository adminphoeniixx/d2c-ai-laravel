<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);              // e.g. "Easypanel VPS", "Interakt"
            $table->string('provider', 150)->nullable(); // e.g. "DigitalOcean", "Interakt"
            $table->string('category', 30);           // hosting, messaging, email, sms, software, other
            $table->decimal('amount', 12, 2);
            $table->string('billing_cycle', 15)->default('monthly'); // monthly, yearly, one_time
            $table->date('next_billing_date')->nullable();
            $table->string('status', 15)->default('active'); // active, paused, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['category']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_subscriptions');
    }
};
