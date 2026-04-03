<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deductions', function (Blueprint $table) {
            $table->string('type', 20)->default('legal')->after('code');
        });

        // Todas las deducciones existentes se clasifican como legales por defecto.
        DB::table('deductions')->update(['type' => 'legal']);
    }

    public function down(): void
    {
        Schema::table('deductions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
