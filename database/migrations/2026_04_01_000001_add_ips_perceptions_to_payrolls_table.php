<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna ips_perceptions a la tabla payrolls.
 *
 * Separa la base imponible para IPS y aguinaldo (solo percepciones salariales
 * con affects_ips = true + horas extras) del total de percepciones general.
 * Cumple con Ley 213/93: el aguinaldo y el aporte IPS solo computan sobre
 * remuneraciones salariales, no sobre viáticos, subsidios ni beneficios sociales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('ips_perceptions', 12, 2)->default(0)->after('total_perceptions');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('ips_perceptions');
        });
    }
};
