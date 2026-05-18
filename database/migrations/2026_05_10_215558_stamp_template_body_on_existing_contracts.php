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
        DB::statement('
            UPDATE contracts c
            JOIN contract_templates t ON t.type = c.type
            SET c.body = t.body
            WHERE c.body IS NULL
              AND t.body IS NOT NULL
        ');
    }

    public function down(): void
    {
        // No reversible: no es posible distinguir qué body vino de la plantilla
        // vs. el que el usuario editó manualmente en cada contrato.
    }
};
