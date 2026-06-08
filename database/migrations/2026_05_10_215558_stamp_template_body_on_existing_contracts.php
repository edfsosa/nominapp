<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copia el body de cada plantilla de contrato a los contratos existentes
 * que aún tienen body = null. Esto "estampa" las cláusulas vigentes en cada
 * contrato para que el PDF sea inmutable a futuros cambios de plantilla.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL JOIN-UPDATE syntax — en SQLite usamos subquery
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('
                UPDATE contracts c
                JOIN contract_templates t ON t.type = c.type
                SET c.body = t.body
                WHERE c.body IS NULL
                  AND t.body IS NOT NULL
            ');
        } else {
            DB::statement('
                UPDATE contracts
                SET body = (
                    SELECT t.body FROM contract_templates t
                    WHERE t.type = contracts.type AND t.body IS NOT NULL
                    LIMIT 1
                )
                WHERE body IS NULL
            ');
        }
    }

    public function down(): void
    {
        // No reversible: no es posible distinguir qué body vino de la plantilla
        // vs. el que el usuario editó manualmente en cada contrato.
    }
};
