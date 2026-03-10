<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->enum('salary_type', ['mensual', 'jornal'])->default('mensual')->after('daily_salary');
            $table->decimal('indemnizacion_estabilidad_amount', 15, 2)->default(0)->after('indemnizacion_amount');
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->dropColumn(['salary_type', 'indemnizacion_estabilidad_amount']);
        });
    }
};
