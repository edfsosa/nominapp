<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega payroll_id a loan_installments para trazabilidad:
 * saber en qué nómina se cobró cada cuota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->foreignId('payroll_id')
                ->nullable()
                ->after('employee_deduction_id')
                ->constrained('payrolls')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Payroll::class);
            $table->dropColumn('payroll_id');
        });
    }
};
