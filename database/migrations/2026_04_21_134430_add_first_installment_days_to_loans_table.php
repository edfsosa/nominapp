<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna first_installment_days a loans.
 *
 * first_installment_days: días desde la aprobación hasta el vencimiento de la primera cuota.
 * El setting global correspondiente está en database/settings/2026_05_08_000001.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedSmallInteger('first_installment_days')->default(30)->after('installment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('first_installment_days');
        });
    }
};
