<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceDayWithEventsSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::all();

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados. Ejecuta EmployeeSeeder primero.');
            return;
        }

        DB::transaction(function () use ($employees) {
            $now = now();
            $attendanceDays = [];

            foreach ($employees as $employee) {
                for ($i = 0; $i < 10; $i++) {
                    $date = Carbon::today()->subDays($i);
                    $isWeekend = $date->isWeekend();

                    if ($isWeekend) {
                        $status = 'weekend';
                    } else {
                        // Ponderado: 70% presente, 15% ausente, 15% permiso
                        $rand = rand(1, 100);
                        $status = match (true) {
                            $rand <= 70 => 'present',
                            $rand <= 85 => 'absent',
                            default     => 'on_leave',
                        };
                    }

                    $attendanceDays[] = [
                        'employee_id' => $employee->id,
                        'date'        => $date->toDateString(),
                        'status'      => $status,
                        'is_weekend'  => $isWeekend,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }

            // Bulk insert de todos los días
            foreach (array_chunk($attendanceDays, 500) as $chunk) {
                DB::table('attendance_days')->insert($chunk);
            }

            // Generar eventos solo para días con status 'present'
            $presentDays = DB::table('attendance_days')
                ->where('status', 'present')
                ->get();

            $events = [];
            foreach ($presentDays as $day) {
                $date = Carbon::parse($day->date);
                $checkIn    = Carbon::parse($date->format('Y-m-d') . ' 08:00')->addMinutes(rand(0, 20));
                $breakStart = $checkIn->copy()->addHours(4);
                $breakEnd   = $breakStart->copy()->addMinutes(rand(30, 60));
                $checkOut   = $breakEnd->copy()->addHours(4)->addMinutes(rand(-15, 15));

                // GPS con drift realista (~100m) cerca de Asunción
                $baseLat = -25.2637 + rand(-100, 100) / 100000;
                $baseLng = -57.5759 + rand(-100, 100) / 100000;
                $location = json_encode(['lat' => $baseLat, 'lng' => $baseLng]);

                $eventTypes = [
                    ['event_type' => 'check_in',    'recorded_at' => $checkIn->toDateTimeString()],
                    ['event_type' => 'break_start', 'recorded_at' => $breakStart->toDateTimeString()],
                    ['event_type' => 'break_end',   'recorded_at' => $breakEnd->toDateTimeString()],
                    ['event_type' => 'check_out',   'recorded_at' => $checkOut->toDateTimeString()],
                ];

                foreach ($eventTypes as $evt) {
                    $events[] = [
                        'attendance_day_id' => $day->id,
                        'event_type'        => $evt['event_type'],
                        'recorded_at'       => $evt['recorded_at'],
                        'location'          => $location,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            }

            // Bulk insert de todos los eventos
            foreach (array_chunk($events, 500) as $chunk) {
                DB::table('attendance_events')->insert($chunk);
            }
        });
    }
}
