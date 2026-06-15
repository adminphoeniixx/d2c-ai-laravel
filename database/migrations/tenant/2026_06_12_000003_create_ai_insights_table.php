<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);       // alert, opportunity
            $table->string('severity', 10);   // high, medium, low
            $table->string('title', 150);
            $table->text('description');
            $table->string('action_label', 60)->nullable();
            $table->string('action_page', 40)->nullable(); // maps to a tenant route name
            $table->json('metric')->nullable();            // supporting numbers, for reference
            $table->timestamps();

            $table->index(['severity', 'type']);
        });

        Schema::create('ai_insight_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('generated_at');
            $table->string('status', 15)->default('ok'); // ok, failed
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
        Schema::dropIfExists('ai_insight_runs');
    }
};
