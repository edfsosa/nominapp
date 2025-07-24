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
        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete(); // Relación con la tabla employees
            $table->foreignId('deduction_id')->constrained()->cascadeOnDelete(); // Relación con la tabla deductions
            $table->date('start_date')->nullable(); // Fecha de inicio de la deducción
            $table->date('end_date')->nullable(); // Fecha de fin de la deducción (opcional)
            $table->integer('custom_amount')->nullable(); // Monto personalizado de la deducción (opcional)
            $table->unique(['employee_id', 'deduction_id', 'start_date'], 'emp_ded_sta_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_deductions');
    }
};
