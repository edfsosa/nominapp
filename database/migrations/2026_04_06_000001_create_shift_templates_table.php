<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 7)->default('#6B7280'); // hex color para el planner
            $table->enum('shift_type', ['diurno', 'nocturno', 'mixto'])->default('diurno');
            $table->boolean('is_day_off')->default(false);
            $table->time('start_time')->nullable(); // null si is_day_off = true
            $table->time('end_time')->nullable();   // end_time < start_time = cruza medianoche
            $table->smallInteger('break_minutes')->default(0); // minutos descontados del tiempo neto
            $table->string('notes', 100)->nullable();
            $table->boolean('is_active')->default(true); // nunca borrar: los IDs viven en sequence JSON
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_templates');
    }
};
