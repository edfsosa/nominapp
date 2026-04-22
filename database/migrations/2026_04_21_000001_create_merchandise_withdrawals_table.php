<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla principal de retiros de mercaderías a crédito.
 *
 * Ciclo de vida: pending → approved → paid / cancelled
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_amount', 12, 2)->default(0);      // Suma de subtotales de ítems
            $table->unsignedInteger('installments_count')->default(1);
            $table->decimal('installment_amount', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->date('approved_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_withdrawals');
    }
};
