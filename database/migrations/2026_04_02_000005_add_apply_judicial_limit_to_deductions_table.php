<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deductions', function (Blueprint $table) {
            // Solo relevante para type='judicial'. true = embargo (tope 25% excedente mínimo);
            // false = alimentaria u otra judicial sin tope estricto de CLT.
            $table->boolean('apply_judicial_limit')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('deductions', function (Blueprint $table) {
            $table->dropColumn('apply_judicial_limit');
        });
    }
};
