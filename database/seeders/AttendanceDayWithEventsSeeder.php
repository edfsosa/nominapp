<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra 30 días de asistencia para empleados activos.
 *
 * Para cada día hábil se generan los cuatro eventos del ciclo completo
 * (check_in, break_start, break_end, check_out) con sus columnas
 * desnormalizadas (employee_id, employee_name, employee_ci, branch_id, branch_name).
 *
 * Las métricas del día (total_hours, net_hours, check_in_time, etc.) se
 * calculan directamente en el seeder en lugar de depender del observer,
 * ya que la inserción es masiva vía DB::table().
 *
 * El status de cada día es determinista (hash de employee_id + fecha)
 * para garantizar reproducibilidad entre ejecuciones.
 */
class AttendanceDayWithEventsSeeder extends Seeder
{
    /** Días a generar hacia atrás desde hoy. */
    private const DAYS = 30;

    /** Hora de entrada/salida esperada (horario estándar). */
    private const EXPECTED_IN         = '08:00:00';
    private const EXPECTED_OUT        = '17:00:00';
    private const EXPECTED_HOURS      = 8.0;
    private const EXPECTED_BREAK_MINS = 60;

    public function run(): void
    {
        $employees = Employee::where('status', 'active')
            ->with('branch')
            ->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados activos. Ejecuta EmployeeSeeder primero.');
            return;
        }

        // Feriados del período para marcar días como 'holiday'
        $startDate    = Carbon::today()->subDays(self::DAYS - 1);
        $holidayDates = DB::table('holidays')
            ->whereBetween('date', [$startDate->toDateString(), Carbon::today()->toDateString()])
            ->pluck('date')
            ->toArray();

        $now         = now();
        $dayRows     = [];
        $presentMeta = []; // "employee_id:date" => event timing data

        foreach ($employees as $employee) {
            $branchCoords = $employee->branch?->coordinates;
            $baseLat      = is_array($branchCoords) ? ($branchCoords['lat'] ?? -25.2637) : -25.2637;
            $baseLng      = is_array($branchCoords) ? ($branchCoords['lng'] ?? -57.5759) : -57.5759;

            for ($i = 0; $i < self::DAYS; $i++) {
                $date      = Carbon::today()->subDays($i);
                $dateStr   = $date->toDateString();
                $isWeekend = $date->isWeekend();
                $isHoliday = in_array($dateStr, $holidayDates);

                if ($isWeekend) {
                    $status = 'weekend';
                } elseif ($isHoliday) {
                    $status = 'holiday';
                } else {
                    // Determinista: mismo resultado en cada ejecución
                    $hash   = abs(crc32($employee->id . '-' . $dateStr)) % 100;
                    $status = match (true) {
                        $hash < 80 => 'present',
                        $hash < 92 => 'absent',
                        default    => 'on_leave',
                    };
                }

                $dayRow = [
                    'employee_id'            => $employee->id,
                    'date'                   => $dateStr,
                    'status'                 => $status,
                    'is_weekend'             => $isWeekend,
                    'is_holiday'             => $isHoliday,
                    'justified_absence'      => $status === 'on_leave',
                    'on_vacation'            => false,
                    'is_extraordinary_work'  => false,
                    'manual_adjustment'      => false,
                    'overtime_approved'      => false,
                    'overtime_limit_exceeded'=> false,
                    'anomaly_flag'           => false,
                    // Campos calculados: null para días no-presentes.
                    // MySQL exige que todas las filas de un bulk insert
                    // tengan exactamente las mismas columnas.
                    'check_in_time'          => null,
                    'check_out_time'         => null,
                    'break_minutes'          => null,
                    'total_hours'            => null,
                    'net_hours'              => null,
                    'expected_check_in'      => null,
                    'expected_check_out'     => null,
                    'expected_hours'         => null,
                    'expected_break_minutes' => null,
                    'late_minutes'           => null,
                    'early_leave_minutes'    => null,
                    'extra_hours'            => null,
                    'extra_hours_diurnas'    => null,
                    'extra_hours_nocturnas'  => null,
                    'is_calculated'          => false,
                    'calculated_at'          => null,
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ];

                if ($status === 'present') {
                    // Tiempos con variación pequeña por empleado y día
                    $lateOffset  = ($employee->id + $i) % 16;          // 0–15 min tarde
                    $extraOffset = ($employee->id * 3 + $i * 7) % 31;  // 0–30 min extra al salir

                    $checkIn    = Carbon::parse("$dateStr " . self::EXPECTED_IN)->addMinutes($lateOffset);
                    $breakStart = Carbon::parse("$dateStr 12:00:00");
                    $breakEnd   = Carbon::parse("$dateStr 13:00:00");
                    $checkOut   = Carbon::parse("$dateStr " . self::EXPECTED_OUT)->addMinutes($extraOffset);

                    $breakMins  = self::EXPECTED_BREAK_MINS;
                    $totalMins  = $checkIn->diffInMinutes($checkOut);
                    $totalHours = round($totalMins / 60, 2);
                    $netHours   = round(($totalMins - $breakMins) / 60, 2);
                    $lateMins   = $lateOffset; // ya calculado arriba
                    $extraHours = max(0.0, round($netHours - self::EXPECTED_HOURS, 2));

                    $dayRow = array_merge($dayRow, [
                        'check_in_time'          => $checkIn->format('H:i:s'),
                        'check_out_time'         => $checkOut->format('H:i:s'),
                        'break_minutes'          => $breakMins,
                        'total_hours'            => $totalHours,
                        'net_hours'              => $netHours,
                        'expected_check_in'      => self::EXPECTED_IN,
                        'expected_check_out'     => self::EXPECTED_OUT,
                        'expected_hours'         => self::EXPECTED_HOURS,
                        'expected_break_minutes' => self::EXPECTED_BREAK_MINS,
                        'late_minutes'           => $lateMins,
                        'early_leave_minutes'    => 0,
                        'extra_hours'            => $extraHours,
                        'extra_hours_diurnas'    => $extraHours,
                        'extra_hours_nocturnas'  => 0,
                        'is_calculated'          => true,
                        'calculated_at'          => $now,
                    ]);

                    // GPS con drift ~100m desde la coordenada de la sucursal
                    $lat = round($baseLat + (($employee->id + $i) % 200 - 100) / 100000, 6);
                    $lng = round($baseLng + (($employee->id * 2 + $i) % 200 - 100) / 100000, 6);

                    $presentMeta["$employee->id:$dateStr"] = [
                        'employee'   => $employee,
                        'checkIn'    => $checkIn->toDateTimeString(),
                        'breakStart' => $breakStart->toDateTimeString(),
                        'breakEnd'   => $breakEnd->toDateTimeString(),
                        'checkOut'   => $checkOut->toDateTimeString(),
                        'location'   => json_encode(['lat' => $lat, 'lng' => $lng]),
                    ];
                }

                $dayRows[] = $dayRow;
            }
        }

        DB::transaction(function () use ($dayRows, $presentMeta, $employees, $now) {
            // Insertar días en bulk
            foreach (array_chunk($dayRows, 500) as $chunk) {
                DB::table('attendance_days')->insert($chunk);
            }

            // Recuperar IDs de días presentes para construir eventos
            $presentDayIds = DB::table('attendance_days')
                ->where('status', 'present')
                ->whereIn('employee_id', $employees->pluck('id'))
                ->get(['id', 'employee_id', 'date']);

            $eventRows = [];
            foreach ($presentDayIds as $day) {
                $key  = "$day->employee_id:$day->date";
                $meta = $presentMeta[$key] ?? null;
                if (! $meta) {
                    continue;
                }

                $emp = $meta['employee'];

                $base = [
                    'employee_id'   => $emp->id,
                    'employee_name' => $emp->full_name,
                    'employee_ci'   => $emp->ci,
                    'branch_id'     => $emp->branch_id,
                    'branch_name'   => $emp->branch?->name,
                    'location'      => $meta['location'],
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];

                foreach ([
                    ['check_in',    $meta['checkIn']],
                    ['break_start', $meta['breakStart']],
                    ['break_end',   $meta['breakEnd']],
                    ['check_out',   $meta['checkOut']],
                ] as [$type, $time]) {
                    $eventRows[] = array_merge($base, [
                        'attendance_day_id' => $day->id,
                        'event_type'        => $type,
                        'recorded_at'       => $time,
                    ]);
                }
            }

            foreach (array_chunk($eventRows, 500) as $chunk) {
                DB::table('attendance_events')->insert($chunk);
            }
        });
    }
}
