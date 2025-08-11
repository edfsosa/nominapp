<?php

namespace Database\Seeders;

use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttendanceDayWithEventsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = Employee::all();

        if ($employees->isEmpty()) {
            $this->command->warn('⚠ No hay empleados en la base de datos. Crea algunos antes de correr este seeder.');
            return;
        }

        $statuses = ['present', 'absent', 'on_leave'];

        foreach ($employees as $employee) {
            for ($i = 0; $i < 10; $i++) {
                $date = Carbon::today()->subDays($i);

                // 1. Crear o actualizar el día
                $day = AttendanceDay::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date'        => $date->toDateString(),
                    ],
                    [
                        'status'      => $status = $statuses[array_rand($statuses)],
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]
                );

                // 2. Si está presente, generar eventos
                if ($status === 'present') {
                    $checkInTime   = Carbon::parse($date->format('Y-m-d') . ' 08:00')->addMinutes(rand(0, 20)); // posible retraso
                    $breakStart    = $checkInTime->copy()->addHours(4); // después de 4 horas
                    $breakEnd      = $breakStart->copy()->addMinutes(rand(30, 60)); // descanso de 30 a 60 min
                    $checkOutTime  = $breakEnd->copy()->addHours(4)->addMinutes(rand(-15, 15)); // +/- 15 min del final

                    $eventsData = [
                        ['event_type' => 'check_in',    'recorded_at' => $checkInTime],
                        ['event_type' => 'break_start', 'recorded_at' => $breakStart],
                        ['event_type' => 'break_end',   'recorded_at' => $breakEnd],
                        ['event_type' => 'check_out',   'recorded_at' => $checkOutTime],
                    ];

                    foreach ($eventsData as $data) {
                        AttendanceEvent::updateOrCreate(
                            [
                                'attendance_day_id' => $day->id,
                                'event_type'        => $data['event_type'],
                            ],
                            [
                                'recorded_at' => $data['recorded_at'],
                                'location'    => ['lat' => -25.3 + rand(0, 100) / 1000, 'lng' => -57.6 + rand(0, 100) / 1000],
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ]
                        );
                    }
                }
            }
        }

        $this->command->info('✅ AttendanceDayWithEventsSeeder completado con eventos realistas.');
    }
}
