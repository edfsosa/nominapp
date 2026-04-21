<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limpia la tabla loans después de migrar los adelantos a advances.
 *
 * Acciones:
 *   1. Elimina las cuotas (loan_installments) de los adelantos migrados.
 *   2. Elimina los préstamos de tipo 'advance' de loans.
 *   3. Elimina la columna type y sus índices (ya no tiene razón de existir).
 *
 * IMPORTANTE: Esta migración debe ejecutarse después de verificar que
 * la migración anterior (migrate_advances_from_loans_to_advances_table)
 * haya copiado correctamente todos los registros.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar cuotas de los adelantos
        DB::statement("
            DELETE li FROM loan_installments li
            INNER JOIN loans l ON l.id = li.loan_id
            WHERE l.type = 'advance'
        ");

        // 2. Eliminar los adelantos de loans
        DB::statement("DELETE FROM loans WHERE type = 'advance'");

        // 3. Eliminar índices que incluyen la columna type, luego la columna
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('loans_employee_id_type_index');
            $table->dropIndex('loans_type_index');
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        // Restaurar la columna type y sus índices
        Schema::table('loans', function (Blueprint $table) {
            $table->enum('type', ['loan', 'advance'])->default('loan')->after('employee_id');
            $table->index(['employee_id', 'type'], 'loans_employee_id_type_index');
            $table->index('type', 'loans_type_index');
        });

        // Restaurar registros de advances → loans
        // Nota: los loan_installments de esos registros no se restauran aquí.
        // Para una restauración completa, hacer rollback también de la migración anterior.
        DB::statement("
            INSERT INTO loans (
                employee_id, type, amount, installments_count, installment_amount,
                status, reason, granted_at, granted_by_id, notes, created_at, updated_at
            )
            SELECT
                a.employee_id,
                'advance',
                a.amount,
                1,
                a.amount,
                CASE a.status
                    WHEN 'pending'   THEN 'pending'
                    WHEN 'approved'  THEN 'active'
                    WHEN 'paid'      THEN 'paid'
                    WHEN 'cancelled' THEN 'cancelled'
                    WHEN 'rejected'  THEN 'cancelled'
                END,
                NULL,
                CASE WHEN a.approved_at IS NOT NULL THEN DATE(a.approved_at) ELSE NULL END,
                a.approved_by_id,
                a.notes,
                a.created_at,
                a.updated_at
            FROM advances a
        ");
    }
};
