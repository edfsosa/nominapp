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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['loan', 'advance'])->default('loan'); // loan = préstamo, advance = adelanto
            $table->decimal('amount', 12, 2); // Monto total del préstamo
            $table->unsignedInteger('installments_count')->default(1); // Número de cuotas
            $table->decimal('installment_amount', 12, 2); // Monto de cada cuota
            $table->enum('status', ['pending', 'active', 'paid', 'cancelled', 'defaulted'])->default('pending');
            $table->text('reason')->nullable(); // Motivo del préstamo/adelanto
            $table->date('granted_at')->nullable(); // Fecha de otorgamiento
            $table->foreignId('granted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices para mejorar rendimiento
            $table->index(['employee_id', 'status']);
            $table->index(['employee_id', 'type']);
            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
