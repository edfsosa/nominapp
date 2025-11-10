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
        Schema::table('attendance_days', function (Blueprint $table) {

            $table->decimal('total_hours', 5, 2)->nullable()->after('status')
                ->comment('Total de horas trabajadas incluyendo descansos');

            $table->decimal('net_hours', 5, 2)->nullable()
                ->comment('Horas netas trabajadas descontando descansos');

            $table->decimal('expected_hours', 5, 2)->nullable()
                ->comment('Horas que el empleado debía cumplir ese día');

            $table->time('expected_check_in')->nullable()
                ->comment('Hora de entrada esperada según el horario asignado');

            $table->time('expected_check_out')->nullable()
                ->comment('Hora de salida esperada según el horario asignado');

            $table->integer('expected_break_minutes')->nullable()
                ->comment('Minutos de descanso esperados según el horario asignado');

            $table->integer('late_minutes')->nullable()
                ->comment('Minutos de llegada tarde respecto al horario esperado');

            $table->integer('early_leave_minutes')->nullable()
                ->comment('Minutos que se retiró antes del horario previsto');

            $table->decimal('extra_hours', 5, 2)->nullable()
                ->comment('Horas extra trabajadas (pueden ser negativas)');

            $table->integer('break_minutes')->nullable()
                ->comment('Minutos totales de descansos ese día');

            $table->time('check_in_time')->nullable()
                ->comment('Hora del primer check-in');

            $table->time('check_out_time')->nullable()
                ->comment('Hora del último check-out');

            $table->boolean('anomaly_flag')->default(false)
                ->comment('True si hubo irregularidades en el registro de asistencia');

            $table->text('notes')->nullable()
                ->comment('Notas del supervisor o recursos humanos');

            // Extras opcionales
            $table->boolean('is_weekend')->nullable()
                ->comment('True si el día fue sábado o domingo');

            $table->boolean('is_holiday')->nullable()
                ->comment('True si fue feriado no laborable');

            $table->boolean('manual_adjustment')->default(false)
                ->comment('True si se modificó manualmente el día');

            $table->boolean('overtime_approved')->default(false)
                ->comment('True si las horas extra fueron autorizadas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropColumn([
                'total_hours',
                'net_hours',
                'expected_hours',
                'expected_check_in',
                'expected_check_out',
                'expected_break_minutes',
                'late_minutes',
                'early_leave_minutes',
                'extra_hours',
                'break_minutes',
                'check_in_time',
                'check_out_time',
                'anomaly_flag',
                'notes',
                'is_weekend',
                'is_holiday',
                'manual_adjustment',
                'overtime_approved',
            ]);
        });
    }
};
