<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\Holiday;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceCalculator
{
    // Definir constantes para los tipos de eventos
    private const EVENT_CHECK_IN = 'check_in';
    private const EVENT_CHECK_OUT = 'check_out';
    private const EVENT_BREAK_START = 'break_start';
    private const EVENT_BREAK_END = 'break_end';

    // Definir constantes para los estados
    private const STATUS_PRESENT = 'present';
    private const STATUS_ON_LEAVE = 'on_leave';
    private const STATUS_ABSENT = 'absent';
    private const STATUS_HOLIDAY = 'holiday';
    private const STATUS_WEEKEND = 'weekend';

    /**
     * Calcula y actualiza los campos básicos de asistencia para el día dado.
     */
    public static function apply(AttendanceDay $day): void
    {
        // Validar datos iniciales
        if (!$day->employee) {
            Log::warning("AttendanceDay {$day->id} no tiene empleado asignado");
            return;
        }

        // Verificar si el empleado está de vacaciones o tiene permiso aprobado
        self::checkVacationStatus($day);
        self::checkLeaveStatus($day);

        // Si el empleado está de vacaciones o tiene permiso, marcar como "on_leave" y detener cálculos
        if ($day->on_vacation || $day->justified_absence) {
            $day->status = self::STATUS_ON_LEAVE;
            self::clearAttendanceData($day);
            self::markAsCalculated($day); // ← Agregar
            return;
        }

        // Verificar si el día es feriado o domingo
        self::checkHolidayAndWeekend($day);

        // Obtener eventos del día
        $events = $day->events()->orderBy('recorded_at')->get();

        // Si es feriado o fin de semana SIN eventos, marcar apropiadamente
        if ($events->isEmpty()) {
            if ($day->is_holiday) {
                $day->status = self::STATUS_HOLIDAY;
            } elseif ($day->is_weekend) {
                $day->status = self::STATUS_WEEKEND;
            } else {
                $day->status = self::STATUS_ABSENT;
            }
            self::clearAttendanceData($day);
            self::markAsCalculated($day); // ← Agregar
            return;
        }

        // Si es feriado o fin de semana CON eventos, es trabajo extraordinario
        if ($day->is_holiday || $day->is_weekend) {
            $day->is_extraordinary_work = true;
        }

        // Calcular horarios y descansos
        self::calculateAttendanceDetails($day, $events);

        // Si llegó hasta aquí con eventos, el empleado estuvo presente
        $day->status = self::STATUS_PRESENT;

        // Marcar como calculado
        self::markAsCalculated($day); // ← Agregar
    }

    /**
     * Marca el día como calculado con timestamp.
     */
    private static function markAsCalculated(AttendanceDay $day): void
    {
        $day->is_calculated = true;
        $day->calculated_at = now();
    }

    /**
     * Aplica el cálculo de asistencia para un rango de fechas.
     * Solo calcula registros existentes, no genera nuevos.
     */
    public static function applyForDateRange(Carbon $startDate, Carbon $endDate): void
    {
        DB::transaction(function () use ($startDate, $endDate) {
            // Calcular/recalcular todos los registros existentes en el rango
            AttendanceDay::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->chunk(100, function ($days) {
                    foreach ($days as $day) {
                        try {
                            self::apply($day);
                            $day->save();
                        } catch (\Exception $e) {
                            Log::error("Error procesando AttendanceDay {$day->id}: {$e->getMessage()}");
                            // Continúa con el siguiente registro
                        }
                    }
                });
        });
    }

    /**
     * Limpia los datos de asistencia cuando no aplican.
     */
    private static function clearAttendanceData(AttendanceDay $day): void
    {
        $day->check_in_time = null;
        $day->check_out_time = null;
        $day->break_minutes = 0;
        $day->total_hours = null;
        $day->net_hours = null;
        $day->extra_hours = null;
        $day->late_minutes = null;
        $day->early_leave_minutes = null;
    }

    /**
     * Verifica si el día es feriado o domingo y actualiza las banderas correspondientes.
     */
    private static function checkHolidayAndWeekend(AttendanceDay $day): void
    {
        $date = Carbon::parse($day->date);

        // Verificar si es domingo
        $day->is_weekend = $date->isSunday();

        // Verificar si es feriado
        $day->is_holiday = Holiday::whereDate('date', $date->toDateString())->exists();
    }

    /**
     * Calcula los detalles de asistencia: horarios, descansos, horas trabajadas, etc.
     */
    private static function calculateAttendanceDetails(AttendanceDay $day, $events): void
    {
        // Solo actualizar valores esperados en el PRIMER cálculo
        // En recalculos, mantener los valores originales (aunque sean NULL)
        if (!$day->is_calculated) {
            $day->expected_check_in = $day->employee->today_scheduled_check_in;
            $day->expected_check_out = $day->employee->today_scheduled_check_out;
            $day->expected_break_minutes = $day->employee->today_expected_break_minutes;
            $day->expected_hours = self::calculateExpectedHours(
                $day->expected_check_in,
                $day->expected_check_out
            );
        }

        // Usar los valores esperados guardados (no los actuales del empleado)
        $scheduledCheckIn = $day->expected_check_in;
        $scheduledCheckOut = $day->expected_check_out;

        // Calcular tiempos de entrada, salida y descansos
        $checkIn = self::getFirstEventTime($events, self::EVENT_CHECK_IN);
        $checkOut = self::getLastEventTime($events, self::EVENT_CHECK_OUT);
        $breakMinutes = self::calculateBreakMinutes($events);

        // Asignar valores REALES calculados (estos SÍ se recalculan siempre)
        $day->check_in_time = optional($checkIn)->format('H:i:s');
        $day->check_out_time = optional($checkOut)->format('H:i:s');
        $day->break_minutes = $breakMinutes;

        // Calcular horas trabajadas
        [$totalHours, $netHours] = self::calculateWorkedHours($checkIn, $checkOut, $breakMinutes);
        $day->total_hours = $totalHours;
        $day->net_hours = $netHours;

        // Calcular horas extra (basado en expected_hours guardadas)
        $day->extra_hours = self::calculateExtraHours($totalHours, $day->expected_hours);

        // Calcular minutos de llegada tarde (basado en expected_check_in guardada)
        $day->late_minutes = self::calculateLateMinutes($scheduledCheckIn, $day->check_in_time);

        // Calcular minutos de salida anticipada (basado en expected_check_out guardada)
        $day->early_leave_minutes = self::calculateEarlyLeaveMinutes($scheduledCheckOut, $day->check_out_time);
    }

    /**
     * Calcula las horas esperadas según los horarios programados.
     */
    private static function calculateExpectedHours(?string $checkIn, ?string $checkOut): ?float
    {
        if ($checkIn && $checkOut) {
            $minutes = Carbon::parse($checkIn)->diffInMinutes(Carbon::parse($checkOut));
            return round($minutes / 60, 2);
        }
        return null;
    }

    /**
     * Obtiene la hora del primer evento de un tipo específico.
     */
    private static function getFirstEventTime($events, string $eventType): ?Carbon
    {
        return $events->where('event_type', $eventType)->first()?->recorded_at;
    }

    /**
     * Obtiene la hora del último evento de un tipo específico.
     */
    private static function getLastEventTime($events, string $eventType): ?Carbon
    {
        return $events->where('event_type', $eventType)->last()?->recorded_at;
    }

    /**
     * Calcula los minutos totales de descanso.
     * Empareja break_start con break_end de forma segura.
     */
    private static function calculateBreakMinutes($events): int
    {
        $breakEvents = $events->filter(
            fn($e) =>
            in_array($e->event_type, [self::EVENT_BREAK_START, self::EVENT_BREAK_END])
        )->values();

        $totalMinutes = 0;
        $breakStart = null;

        foreach ($breakEvents as $event) {
            if ($event->event_type === self::EVENT_BREAK_START) {
                $breakStart = $event->recorded_at;
            } elseif ($event->event_type === self::EVENT_BREAK_END && $breakStart) {
                $totalMinutes += Carbon::parse($breakStart)->diffInMinutes($event->recorded_at);
                $breakStart = null; // Reset para el siguiente par
            }
        }

        return $totalMinutes;
    }

    /**
     * Calcula las horas totales y netas trabajadas.
     */
    private static function calculateWorkedHours(?Carbon $checkIn, ?Carbon $checkOut, int $breakMinutes): array
    {
        if ($checkIn && $checkOut) {
            $totalMinutes = $checkIn->diffInMinutes($checkOut);
            $netMinutes = max(0, $totalMinutes - $breakMinutes);
            return [
                round($totalMinutes / 60, 2),
                round($netMinutes / 60, 2),
            ];
        }
        return [null, null];
    }

    /**
     * Calcula las horas extra trabajadas.
     */
    private static function calculateExtraHours(?float $totalHours, ?float $expectedHours): ?float
    {
        if ($totalHours !== null && $expectedHours !== null) {
            $extraHours = $totalHours - $expectedHours;
            return $extraHours > 0 ? round($extraHours, 2) : 0;
        }
        return null;
    }

    /**
     * Calcula los minutos de llegada tarde.
     */
    private static function calculateLateMinutes(?string $scheduledCheckIn, ?string $actualCheckIn): ?int
    {
        if ($scheduledCheckIn && $actualCheckIn) {
            $expected = Carbon::parse($scheduledCheckIn);
            $actual = Carbon::parse($actualCheckIn);
            return $actual->greaterThan($expected) ? $expected->diffInMinutes($actual) : 0;
        }
        return null;
    }

    /**
     * Calcula los minutos de salida anticipada.
     */
    private static function calculateEarlyLeaveMinutes(?string $scheduledCheckOut, ?string $actualCheckOut): ?int
    {
        if ($scheduledCheckOut && $actualCheckOut) {
            $expected = Carbon::parse($scheduledCheckOut);
            $actual = Carbon::parse($actualCheckOut);
            return $actual->lessThan($expected) ? $expected->diffInMinutes($actual) : 0;
        }
        return null;
    }

    /**
     * Verifica si el empleado está de vacaciones y actualiza la bandera on_vacation.
     */
    public static function checkVacationStatus(AttendanceDay $day): void
    {
        $isOnVacation = $day->employee->vacations()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $day->date)
            ->whereDate('end_date', '>=', $day->date)
            ->exists();

        $day->on_vacation = $isOnVacation;
    }

    /**
     * Verifica si el empleado tiene permiso aprobado y actualiza la bandera justified_absence.
     */
    public static function checkLeaveStatus(AttendanceDay $day): void
    {
        $hasJustifiedLeave = $day->employee->leaves()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $day->date)
            ->whereDate('end_date', '>=', $day->date)
            ->exists();

        $day->justified_absence = $hasJustifiedLeave;
    }
}
