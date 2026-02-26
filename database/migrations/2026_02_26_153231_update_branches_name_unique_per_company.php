<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Eliminar el unique global en name
            $table->dropUnique(['name']);

            // Agregar unique compuesto: mismo nombre solo es duplicado dentro de la misma empresa
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
            $table->unique(['name']);
        });
    }
};
