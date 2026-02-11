<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->decimal('extra_hours_diurnas', 5, 2)->nullable()
                ->after('extra_hours')
                ->comment('Horas extra en periodo diurno (06:00-20:00)');

            $table->decimal('extra_hours_nocturnas', 5, 2)->nullable()
                ->after('extra_hours_diurnas')
                ->comment('Horas extra en periodo nocturno (20:00-06:00)');

            $table->boolean('overtime_limit_exceeded')->default(false)
                ->after('overtime_approved')
                ->comment('True si horas extra exceden limite legal de 3h/dia');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropColumn(['extra_hours_diurnas', 'extra_hours_nocturnas', 'overtime_limit_exceeded']);
        });
    }
};
