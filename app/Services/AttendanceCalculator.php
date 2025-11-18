<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\Holiday;
use Illuminate\Support\Carbon;

class AttendanceCalculator
{
    // Definir constantes para los tipos de eventos
    private const EVENT_CHECK_IN = 'check_in';
    private const EVENT_CHECK_OUT = 'check_out';
    private const EVENT_BREAK_START = 'break_start';
    private const EVENT_BREAK_END = 'break_end';

    // Definir constantes para los estados
    private const STATUS_ON_LEAVE = 'on_leave';
    private const STATUS_ABSENT = 'absent';

    /**
     * Calcula y actualiza los campos básicos de asistencia para el día dado.
     */
    public static function apply(AttendanceDay $day): void
    {
        // Validar datos iniciales
        if (!$day->employee) {
            return;
        }

        // Verificar si el empleado está de vacaciones o tiene permiso aprobado
        self::checkVacationStatus($day);
        self::checkLeaveStatus($day);

        // Si el empleado está de vacaciones o tiene permiso, marcar como "on_leave" y detener cálculos
        if ($day->on_vacation || $day->justified_absence) {
            $day->status = self::STATUS_ON_LEAVE;
            return;
        }

        // Verificar si el día es feriado o domingo
        self::checkHolidayAndWeekend($day);

        // Obtener eventos del día
        $events = $day->events()->orderBy('recorded_at')->get();

        // Determinar si el empleado debe estar marcado como "absent"
        if ($events->isEmpty() && !$day->is_holiday && !$day->is_weekend) {
            $day->status = self::STATUS_ABSENT;
            return;
        }

        // Calcular horarios y descansos
        self::calculateAttendanceDetails($day, $events);
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
        // Obtener horarios programados
        $scheduledCheckIn = $day->employee->today_scheduled_check_in;
        $scheduledCheckOut = $day->employee->today_scheduled_check_out;
        $expectedBreakMinutes = $day->employee->today_expected_break_minutes;

        // Calcular horas esperadas
        $day->expected_hours = self::calculateExpectedHours($scheduledCheckIn, $scheduledCheckOut);

        // Calcular tiempos de entrada, salida y descansos
        $checkIn = self::getFirstEventTime($events, self::EVENT_CHECK_IN);
        $checkOut = self::getLastEventTime($events, self::EVENT_CHECK_OUT);
        $breakMinutes = self::calculateBreakMinutes($events);

        // Asignar valores calculados al modelo
        $day->check_in_time = optional($checkIn)->format('H:i:s');
        $day->check_out_time = optional($checkOut)->format('H:i:s');
        $day->break_minutes = $breakMinutes;
        $day->expected_break_minutes = $expectedBreakMinutes;
        $day->expected_check_in = $scheduledCheckIn;
        $day->expected_check_out = $scheduledCheckOut;

        // Calcular horas trabajadas
        [$totalHours, $netHours] = self::calculateWorkedHours($checkIn, $checkOut, $breakMinutes);
        $day->total_hours = $totalHours;
        $day->net_hours = $netHours;

        // Calcular horas extra
        $day->extra_hours = self::calculateExtraHours($totalHours, $day->expected_hours);

        // Calcular minutos de llegada tarde
        $day->late_minutes = self::calculateLateMinutes($scheduledCheckIn, $day->check_in_time);

        // Calcular minutos de salida anticipada
        $day->early_leave_minutes = self::calculateEarlyLeaveMinutes($scheduledCheckOut, $day->check_out_time);
    }

    /**
     * Calcula las horas esperadas según los horarios programados.
     */
    private static function calculateExpectedHours(?string $checkIn, ?string $checkOut): ?int
    {
        if ($checkIn && $checkOut) {
            return Carbon::parse($checkIn)->diffInHours(Carbon::parse($checkOut));
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
     */
    private static function calculateBreakMinutes($events): int
    {
        $breaks = $events->filter(fn($e) => in_array($e->event_type, [self::EVENT_BREAK_START, self::EVENT_BREAK_END]))->values();

        return $breaks->chunk(2)->sum(function ($pair) {
            if ($pair->count() === 2) {
                $start = $pair[0]->recorded_at;
                $end = $pair[1]->recorded_at;
                return Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
            }
            return 0;
        });
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
    private static function calculateExtraHours(?float $totalHours, ?int $expectedHours): ?float
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
