<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_days', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('day_of_week');
            $table->time('start_time')->nullable()->change();
            $table->time('end_time')->nullable()->change();
        });

        // Todos los días existentes estaban activos (modelo anterior no tenía is_active)
        DB::table('schedule_days')->update(['is_active' => true]);

        // Crear los días faltantes (inactivos) para horarios ya existentes
        $scheduleIds = DB::table('schedules')->pluck('id');

        foreach ($scheduleIds as $scheduleId) {
            $existingDays = DB::table('schedule_days')
                ->where('schedule_id', $scheduleId)
                ->pluck('day_of_week')
                ->toArray();

            $missingDays = array_diff(range(1, 7), $existingDays);

            foreach ($missingDays as $day) {
                DB::table('schedule_days')->insert([
                    'schedule_id' => $scheduleId,
                    'day_of_week' => $day,
                    'is_active'   => false,
                    'start_time'  => null,
                    'end_time'    => null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('schedule_days', function (Blueprint $table) {
            $table->dropColumn('is_active');
            $table->time('start_time')->nullable(false)->change();
            $table->time('end_time')->nullable(false)->change();
        });
    }
};
