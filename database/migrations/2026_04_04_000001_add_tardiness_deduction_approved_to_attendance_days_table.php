<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->boolean('tardiness_deduction_approved')->default(false)
                ->after('overtime_limit_exceeded')
                ->comment('True si RR.HH. aprobó descontar los minutos de tardanza de este día');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropColumn('tardiness_deduction_approved');
        });
    }
};
