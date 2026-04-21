<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migra los registros de adelantos (type='advance') desde la tabla loans
 * hacia la nueva tabla advances.
 *
 * Mapeo de estados:
 *   pending   → pending
 *   active    → approved   (activo = ya fue aprobado)
 *   paid      → paid
 *   cancelled → cancelled
 *   defaulted → cancelled  (no aplica a adelantos; se trata como cancelado)
 *
 * Los adelantos tienen siempre una sola cuota en loan_installments.
 * El employee_deduction_id y payroll_id se toman de esa única cuota.
 *
 * Esta migración es reversible: el down() restaura los registros en loans.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Insertar adelantos en la nueva tabla
        DB::statement("
            INSERT INTO advances (
                employee_id,
                amount,
                status,
                approved_by_id,
                approved_at,
                notes,
                employee_deduction_id,
                payroll_id,
                created_at,
                updated_at
            )
            SELECT
                l.employee_id,
                l.amount,
                CASE l.status
                    WHEN 'pending'   THEN 'pending'
                    WHEN 'active'    THEN 'approved'
                    WHEN 'paid'      THEN 'paid'
                    WHEN 'cancelled' THEN 'cancelled'
                    WHEN 'defaulted' THEN 'cancelled'
                END,
                l.granted_by_id,
                CASE WHEN l.granted_at IS NOT NULL
                    THEN CONCAT(l.granted_at, ' 00:00:00')
                    ELSE NULL
                END,
                l.notes,
                -- employee_deduction_id y payroll_id desde la única cuota
                (SELECT li.employee_deduction_id
                 FROM loan_installments li
                 WHERE li.loan_id = l.id
                 ORDER BY li.id
                 LIMIT 1),
                (SELECT li.payroll_id
                 FROM loan_installments li
                 WHERE li.loan_id = l.id
                 ORDER BY li.id
                 LIMIT 1),
                l.created_at,
                l.updated_at
            FROM loans l
            WHERE l.type = 'advance'
        ");
    }

    public function down(): void
    {
        // Eliminar solo los registros migrados en este paso
        // (el cleanup del paso 5 no habrá corrido aún si hacemos rollback en orden)
        DB::statement("
            DELETE FROM advances
            WHERE created_at IN (
                SELECT created_at FROM loans WHERE type = 'advance'
            )
              AND employee_id IN (
                SELECT employee_id FROM loans WHERE type = 'advance'
            )
        ");
    }
};
