<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración de datos: normaliza los estados de adelantos para el nuevo flujo
 * pending → approved → disbursed → paid.
 *
 * Paso 1 — paid → approved (empresa 2):
 *   Los adelantos 'paid' sin payroll_id corresponden a adelantos aprobados que
 *   aún no fueron entregados al empleado. Todos pertenecen a la empresa 2.
 *
 * Paso 2 — approved → disbursed (empresa 1, transferencia):
 *   Los adelantos aprobados de la empresa 1 con método 'transfer' ya fueron
 *   acreditados bancariamente el 2026-05-13. Se sellan como entregados.
 *   Los de empresa 1 con 'cash' y los de empresa 2 quedan como 'approved'
 *   para que el usuario los gestione manualmente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Paso 1: paid → approved (ninguno tiene payroll_id, todos son empresa 2)
        DB::table('advances')
            ->where('status', 'paid')
            ->whereNull('payroll_id')
            ->update(['status' => 'approved']);

        // Paso 2: approved de empresa 1 + transfer → disbursed con fecha de acreditación
        DB::table('advances')
            ->join('employees', 'employees.id', '=', 'advances.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->where('advances.status', 'approved')
            ->where('branches.company_id', 1)
            ->where('advances.payment_method', 'transfer')
            ->update([
                'advances.status' => 'disbursed',
                'advances.disbursement_batch_at' => '2026-05-13',
            ]);
    }

    public function down(): void
    {
        // Revertir paso 2: disbursed de empresa 1 + transfer → approved
        DB::table('advances')
            ->join('employees', 'employees.id', '=', 'advances.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->where('advances.status', 'disbursed')
            ->where('branches.company_id', 1)
            ->where('advances.payment_method', 'transfer')
            ->whereNull('advances.payroll_id')
            ->update([
                'advances.status' => 'approved',
                'advances.disbursement_batch_at' => null,
            ]);

        // Revertir paso 1: approved de empresa 2 → paid
        DB::table('advances')
            ->join('employees', 'employees.id', '=', 'advances.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->where('advances.status', 'approved')
            ->where('branches.company_id', 2)
            ->whereNull('advances.payroll_id')
            ->update(['advances.status' => 'paid']);
    }
};
