<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega advance_percent a contracts para el adelanto automático mensual.
 * NULL = sin adelanto automático. 1–25 = porcentaje del salario a adelantar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedTinyInteger('advance_percent')
                ->nullable()
                ->after('payment_method')
                ->comment('Porcentaje del salario para adelanto automático mensual (1-25). NULL = desactivado.');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('advance_percent');
        });
    }
};
