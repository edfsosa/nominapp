<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // attendance_days: índices para filtros más frecuentes
        Schema::table('attendance_days', function (Blueprint $table) {
            // WHERE status = 'present' — aplicado en cada carga del recurso
            $table->index('status', 'idx_attendance_days_status');

            // WHERE is_calculated = 0/1 — usado en tabs y bulk actions
            $table->index('is_calculated', 'idx_attendance_days_is_calculated');

            // WHERE date BETWEEN ... — filtro de rango de fechas
            $table->index('date', 'idx_attendance_days_date');

            // WHERE status = 'present' AND date BETWEEN ... — combinación más frecuente
            $table->index(['status', 'date'], 'idx_attendance_days_status_date');
        });

        // attendance_events: índices para campos desnormalizados usados en filtros
        Schema::table('attendance_events', function (Blueprint $table) {
            // WHERE employee_id = ? — filtro de empleado en el recurso
            $table->index('employee_id', 'idx_attendance_events_employee_id');

            // WHERE branch_id = ? — filtro de sucursal en el recurso
            $table->index('branch_id', 'idx_attendance_events_branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_days_status');
            $table->dropIndex('idx_attendance_days_is_calculated');
            $table->dropIndex('idx_attendance_days_date');
            $table->dropIndex('idx_attendance_days_status_date');
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_events_employee_id');
            $table->dropIndex('idx_attendance_events_branch_id');
        });
    }
};
