<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla advances (adelantos de salario).
 *
 * Los adelantos son retiros anticipados del sueldo del mes en curso.
 * Se descuentan en su totalidad en la siguiente liquidación de nómina.
 * A diferencia de los préstamos, no tienen cuotas: el vínculo con nómina
 * se almacena directamente en esta tabla (employee_deduction_id, payroll_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();

            // Vínculo con nómina — se establece al procesar el adelanto en PayrollService
            $table->foreignId('employee_deduction_id')
                ->nullable()
                ->constrained('employee_deductions')
                ->nullOnDelete();
            $table->foreignId('payroll_id')
                ->nullable()
                ->constrained('payrolls')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advances');
    }
};
