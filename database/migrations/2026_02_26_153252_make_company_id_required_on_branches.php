<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMPORTANTE: Correr esta migración SOLO después de haber asignado
 * una empresa a todas las sucursales huérfanas desde el panel de administración.
 *
 * Para verificar sucursales sin empresa antes de correr:
 *   SELECT * FROM branches WHERE company_id IS NULL;
 */
return new class extends Migration
{
    public function up(): void
    {
        // Verificar que no quedan sucursales sin empresa
        $orphans = \DB::table('branches')->whereNull('company_id')->count();
        if ($orphans > 0) {
            throw new \RuntimeException(
                "No se puede ejecutar esta migración: existen {$orphans} sucursal(es) sin empresa asignada. " .
                "Asigna una empresa a todas las sucursales desde el panel de administración primero."
            );
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->foreignId('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }
};
