<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Elimina el valor 'suspended' del enum employees.status.
 * La suspensión ahora se gestiona a nivel de contrato (contracts.status).
 * Seguro en producción: 0 empleados tienen status = 'suspended'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Salvaguarda: no proceder si existen empleados suspendidos
        if (DB::table('employees')->where('status', 'suspended')->exists()) {
            throw new \RuntimeException('No se puede aplicar la migración: existen empleados con status = suspended. Reactivarlos primero.');
        }

        DB::statement("ALTER TABLE employees MODIFY COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employees MODIFY COLUMN status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active'");
    }
};
