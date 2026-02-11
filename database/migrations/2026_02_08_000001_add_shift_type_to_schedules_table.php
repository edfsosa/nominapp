<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('shift_type', ['diurno', 'nocturno', 'mixto'])
                ->default('diurno')
                ->after('description')
                ->comment('Tipo de jornada: diurno (06-20), nocturno (20-06), mixto');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('shift_type');
        });
    }
};
