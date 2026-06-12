<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 5); // in, out
            $table->time('time');
            $table->string('source', 15)->default('kiosk'); // kiosk, app, manual
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
            $table->index(['date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punches');
    }
};
