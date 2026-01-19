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
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_number'); // Número de cuota (1, 2, 3...)
            $table->decimal('amount', 12, 2); // Monto de la cuota
            $table->date('due_date'); // Fecha de vencimiento
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable(); // Fecha en que se pagó
            $table->foreignId('employee_deduction_id')->nullable()->constrained('employee_deductions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices para mejorar rendimiento
            $table->index(['loan_id', 'status']);
            $table->index(['loan_id', 'installment_number']);
            $table->index('status');
            $table->index('due_date');

            // Asegurar que no haya cuotas duplicadas por préstamo
            $table->unique(['loan_id', 'installment_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
