<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de datos para las columnas agregadas en la migración anterior.
 *
 * Para loans:
 *   outstanding_balance = suma de cuotas pendientes (status='pending') del préstamo.
 *   Para préstamos ya pagados o cancelados queda en 0 (correcto).
 *
 * Para loan_installments (todos son préstamos sin interés hasta ahora):
 *   capital_amount = amount  (toda la cuota es capital)
 *   interest_amount = 0      (sin interés en registros históricos)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Poblar outstanding_balance en loans existentes
        DB::statement('
            UPDATE loans l
            SET l.outstanding_balance = (
                SELECT COALESCE(SUM(li.amount), 0)
                FROM loan_installments li
                WHERE li.loan_id = l.id
                  AND li.status = \'pending\'
            )
        ');

        // Poblar capital_amount e interest_amount en cuotas existentes
        // Todos los préstamos actuales son sin interés → capital = amount, interest = 0
        DB::statement('
            UPDATE loan_installments
            SET capital_amount  = amount,
                interest_amount = 0
        ');
    }

    public function down(): void
    {
        // Revertir a 0 (los valores por defecto de la migración anterior)
        DB::statement('UPDATE loans SET outstanding_balance = 0');
        DB::statement('UPDATE loan_installments SET capital_amount = 0, interest_amount = 0');
    }
};
