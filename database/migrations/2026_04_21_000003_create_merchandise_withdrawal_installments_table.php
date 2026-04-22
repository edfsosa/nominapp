<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cuotas de descuento en nómina para retiros de mercadería. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise_withdrawal_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchandise_withdrawal_id');
            $table->foreign('merchandise_withdrawal_id', 'mwi_withdrawal_fk')
                ->references('id')->on('merchandise_withdrawals')->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('employee_deduction_id')->nullable();
            $table->foreign('employee_deduction_id', 'mwi_deduction_fk')
                ->references('id')->on('employee_deductions')->nullOnDelete();
            $table->foreignId('payroll_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['merchandise_withdrawal_id', 'status'], 'mwi_withdrawal_status_idx');
            $table->index(['merchandise_withdrawal_id', 'installment_number'], 'mwi_withdrawal_number_idx');
            $table->index('status');
            $table->index('due_date');
            $table->unique(['merchandise_withdrawal_id', 'installment_number'], 'mwi_withdrawal_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_withdrawal_installments');
    }
};
