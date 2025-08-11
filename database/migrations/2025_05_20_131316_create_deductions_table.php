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
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60); // nombre de la deducción (e.g. IPS, IRP)
            $table->text('description')->nullable();  // descripción opcional
            $table->enum('calculation', ['fixed', 'percentage'])->default('fixed'); // fijo o porcentaje
            $table->decimal('amount', 10, 2)->nullable(); // monto fijo si es tipo 'fixed'
            $table->decimal('percent', 5, 2)->nullable(); // porcentaje si es tipo 'percentage'
            $table->boolean('is_mandatory')->default(false); // si la deducción es obligatoria
            $table->boolean('is_active')->default(true); // si la deducción está activa
            $table->timestamps();
            $table->index(['is_active', 'calculation'], 'idx_deductions_active_calculation'); // índice para optimizar consultas por estado y tipo de cálculo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deductions');
    }
};
