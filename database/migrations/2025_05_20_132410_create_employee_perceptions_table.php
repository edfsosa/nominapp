<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_perceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete(); // Relación con la tabla employees
            $table->foreignId('perception_id')->constrained()->cascadeOnDelete(); // Relación con la tabla perception_types
            $table->date('start_date'); // Fecha de inicio de la percepción
            $table->date('end_date')->nullable(); // Fecha de fin de la percepción (opcional)
            $table->decimal('custom_amount', 10, 2)->nullable(); // Monto personalizado de la percepción (opcional)
            $table->unique(['employee_id', 'perception_id', 'start_date'], 'emp_per_sta_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_perceptions');
    }
};
