<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Datos de la desvinculación
            $table->date('termination_date');
            $table->enum('termination_type', [
                'unjustified_dismissal',
                'justified_dismissal',
                'resignation',
                'mutual_agreement',
                'contract_end',
            ]);
            $table->text('termination_reason')->nullable();
            $table->boolean('preaviso_otorgado')->default(false);

            // Snapshot del empleo al momento de la liquidación
            $table->date('hire_date');
            $table->decimal('base_salary', 12, 2);
            $table->decimal('daily_salary', 12, 2);
            $table->unsignedInteger('years_of_service')->default(0);
            $table->unsignedInteger('months_of_service')->default(0);
            $table->unsignedInteger('days_of_service')->default(0);
            $table->decimal('average_salary_6m', 12, 2)->default(0);

            // Montos por componente (haberes)
            $table->unsignedInteger('preaviso_days')->default(0);
            $table->decimal('preaviso_amount', 12, 2)->default(0);
            $table->decimal('indemnizacion_amount', 12, 2)->default(0);
            $table->unsignedInteger('vacaciones_days')->default(0);
            $table->decimal('vacaciones_amount', 12, 2)->default(0);
            $table->decimal('aguinaldo_proporcional_amount', 12, 2)->default(0);
            $table->unsignedInteger('salario_pendiente_days')->default(0);
            $table->decimal('salario_pendiente_amount', 12, 2)->default(0);

            // Deducciones
            $table->decimal('ips_deduction', 12, 2)->default(0);
            $table->decimal('loan_deduction', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);

            // Totales
            $table->decimal('total_haberes', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);

            // Status workflow
            $table->enum('status', ['draft', 'calculated', 'closed'])->default('draft');
            $table->string('pdf_path')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'termination_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones');
    }
};
