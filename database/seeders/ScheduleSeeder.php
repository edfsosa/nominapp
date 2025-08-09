<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            // 1) Schedules base
            $schedules = [
                ['name' => 'Estándar',   'description' => 'Lun-Vie 08:00-17:00 (1h almuerzo)'],
                ['name' => 'Mañana',     'description' => 'Lun-Sáb 06:00-12:00'],
                ['name' => 'Tarde',      'description' => 'Lun-Sáb 12:00-18:00'],
                ['name' => 'Nocturno',   'description' => 'Lun-Vie 22:00-06:00'],
                ['name' => 'Medio Tiempo', 'description' => 'Lun-Vie 08:00-12:00'],
            ];

            DB::table('schedules')->insertOrIgnore(
                collect($schedules)->map(fn($s) => array_merge($s, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]))->toArray()
            );

            // (name => id)
            $scheduleIds = DB::table('schedules')->pluck('id', 'name');

            // 2) Configuración de días por schedule (1=Mon ... 7=Sun)
            $dayConfigs = [
                'Estándar' => [
                    ['days' => [1, 2, 3, 4, 5], 'start' => '08:00:00', 'end' => '17:00:00'],
                ],
                'Mañana'   => [
                    ['days' => [1, 2, 3, 4, 5, 6], 'start' => '06:00:00', 'end' => '12:00:00'],
                ],
                'Tarde'    => [
                    ['days' => [1, 2, 3, 4, 5, 6], 'start' => '12:00:00', 'end' => '18:00:00'],
                ],
                'Nocturno' => [
                    // Turno cruzando medianoche (22:00 -> 06:00 del día siguiente)
                    ['days' => [1, 2, 3, 4, 5], 'start' => '22:00:00', 'end' => '06:00:00'],
                ],
                'Medio Tiempo' => [
                    ['days' => [1, 2, 3, 4, 5], 'start' => '08:00:00', 'end' => '12:00:00'],
                ],
            ];

            $scheduleDaysRows = [];
            foreach ($dayConfigs as $scheduleName => $chunks) {
                $sid = $scheduleIds[$scheduleName] ?? null;
                if (!$sid) continue;

                foreach ($chunks as $chunk) {
                    foreach ($chunk['days'] as $dow) {
                        $scheduleDaysRows[] = [
                            'schedule_id' => $sid,
                            'day_of_week' => $dow,
                            'start_time'  => $chunk['start'],
                            'end_time'    => $chunk['end'],
                            'created_at'  => $now,
                            'updated_at'  => $now,
                        ];
                    }
                }
            }
            if ($scheduleDaysRows) {
                DB::table('schedule_days')->insert($scheduleDaysRows);
            }

            // schedule_day_id => (schedule_id, dow) para asignar breaks por nombre de schedule
            $days = DB::table('schedule_days')->get();
            $daysByScheduleId = $days->groupBy('schedule_id');

            // 3) Breaks por schedule
            $breakConfigs = [
                'Estándar' => [
                    ['name' => 'Almuerzo', 'start' => '12:00:00', 'end' => '13:00:00'],
                ],
                'Mañana'   => [
                    ['name' => 'Desayuno', 'start' => '09:00:00', 'end' => '09:15:00'],
                ],
                'Tarde'    => [
                    ['name' => 'Merienda', 'start' => '15:00:00', 'end' => '15:15:00'],
                ],
                // Nocturno / Medio Tiempo sin breaks por defecto (puedes agregar si querés)
            ];

            $breakRows = [];
            foreach ($breakConfigs as $scheduleName => $breaks) {
                $sid = $scheduleIds[$scheduleName] ?? null;
                if (!$sid || !$daysByScheduleId->has($sid)) continue;

                foreach ($daysByScheduleId[$sid] as $dayRow) {
                    foreach ($breaks as $b) {
                        $breakRows[] = [
                            'schedule_day_id' => $dayRow->id,
                            'name'       => $b['name'],
                            'start_time' => $b['start'],
                            'end_time'   => $b['end'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            if ($breakRows) {
                DB::table('schedule_breaks')->insert($breakRows);
            }
        });
    }
}
