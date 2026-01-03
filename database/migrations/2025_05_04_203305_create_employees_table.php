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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('photo')->nullable(); // foto
            $table->string('first_name', 60); // nombre
            $table->string('last_name', 60); // apellido
            $table->string('ci', 20)->unique(); // cédula
            $table->date('birth_date')->nullable(); // fecha de nacimiento
            $table->string('phone', 50)->nullable(); // teléfono
            $table->string('email', 100)->unique(); // correo
            $table->date('hire_date'); // fecha de ingreso
            $table->enum('payroll_type', ['monthly', 'biweekly', 'weekly'])->default('monthly'); // tipo de nómina
            $table->enum('employment_type', ['full_time', 'day_laborer'])->default('full_time'); // tipo de empleo
            $table->decimal('base_salary', 12, 2)->nullable(); // salario base en Guaranies (PYG)
            $table->decimal('daily_rate', 12, 2)->nullable(); // tarifa diaria en Guaranies (PYG)
            $table->enum('payment_method', ['debit', 'cash', 'check'])->default('debit'); // método de pago
            $table->foreignId('position_id')->nullable()->constrained()->onDelete('set null'); // posición
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null'); // sucursal
            $table->foreignId('schedule_id')->nullable()->constrained()->onDelete('set null'); // horario
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active'); // estado
            $table->json('face_descriptor')->nullable(); // descriptor facial
            $table->timestamps();
            $table->index(['first_name', 'last_name']); // índice para búsqueda por nombre completo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
