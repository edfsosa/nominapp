<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas necesarias para el módulo de intereses y separación de adelantos.
 *
 * En loans:
 *   - interest_rate: tasa anual en porcentaje (0 = sin interés)
 *   - outstanding_balance: saldo pendiente almacenado para reportes rápidos
 *
 * En loan_installments:
 *   - capital_amount: porción capital de la cuota (amortización francesa)
 *   - interest_amount: porción interés de la cuota
 *   - installment_amount: renombramos amount → installment_amount para mayor claridad
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->default(0)->after('amount')
                ->comment('Tasa de interés anual en porcentaje. 0 = sin interés.');
            $table->decimal('outstanding_balance', 15, 2)->default(0)->after('installment_amount')
                ->comment('Saldo pendiente actualizado al cobrar cada cuota.');
        });

        Schema::table('loan_installments', function (Blueprint $table) {
            $table->decimal('capital_amount', 15, 2)->default(0)->after('amount')
                ->comment('Porción capital de esta cuota (amortización francesa).');
            $table->decimal('interest_amount', 15, 2)->default(0)->after('capital_amount')
                ->comment('Porción interés de esta cuota (amortización francesa).');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['interest_rate', 'outstanding_balance']);
        });

        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropColumn(['capital_amount', 'interest_amount']);
        });
    }
};
