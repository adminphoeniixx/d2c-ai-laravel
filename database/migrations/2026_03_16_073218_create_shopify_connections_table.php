<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_connections', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('tenant_id');

            $table->string('shop')->unique(); // store.myshopify.com

            $table->text('access_token')->nullable();

            $table->string('scope')->nullable();

            $table->timestamp('installed_at')->nullable();

            $table->timestamp('last_synced_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('tenant_id');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_connections');
    }
};