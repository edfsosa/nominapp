<?php

namespace App\Services;

use App\Models\AttendanceDay;
use Illuminate\Support\Carbon;

class AttendanceCalculator
{
    /**
     * Calcula y actualiza los campos básicos de asistencia para el día dado.
     */
    public static function apply(AttendanceDay $day): void
    {
        // ✅ Obtener el horario de entrada y salida del empleado de acuerdo a su horario asignado
        $scheduledCheckIn = $day->employee->today_scheduled_check_in;
        $scheduledCheckOut = $day->employee->today_scheduled_check_out;
        $expectedBreakMinutes = $day->employee->today_expected_break_minutes;     

        // ✅ Calcular horas esperadas
        if ($scheduledCheckIn && $scheduledCheckOut) {
            $day->expected_hours = Carbon::parse($scheduledCheckIn)
                ->diffInHours(Carbon::parse($scheduledCheckOut));
        } else {
            $day->expected_hours = null;
        }

        // ✅ Obtener eventos del día ordenados por hora
        $events = $day->events()->orderBy('recorded_at')->get();

        // ✅ Primer check-in y último check-out
        $checkIn = $events->where('event_type', 'check_in')->first()?->recorded_at;
        $checkOut = $events->where('event_type', 'check_out')->last()?->recorded_at;

        // ✅ Calcular minutos de descanso
        $breaks = $events->filter(fn($e) => in_array($e->event_type, ['break_start', 'break_end']))->values();
        $breakMinutes = 0;

        for ($i = 0; $i < $breaks->count(); $i += 2) {
            $start = $breaks->get($i)?->recorded_at;
            $end = $breaks->get($i + 1)?->recorded_at;

            if ($start && $end) {
                $breakMinutes += Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
            }
        }

        // ✅ Asignar los campos al modelo
        $day->check_in_time = optional($checkIn)->format('H:i:s');
        $day->check_out_time = optional($checkOut)->format('H:i:s');
        $day->break_minutes = $breakMinutes;
        $day->expected_break_minutes = $expectedBreakMinutes;
        $day->expected_check_in = $scheduledCheckIn;
        $day->expected_check_out = $scheduledCheckOut;
        

        // ✅ Calcular horas netas trabajadas
        if ($checkIn && $checkOut) {
            $totalMinutes = Carbon::parse($checkIn)->diffInMinutes(Carbon::parse($checkOut));
            $netMinutes = $totalMinutes - $breakMinutes;
            $day->net_hours = round($netMinutes / 60, 2);
            $day->total_hours = round($totalMinutes / 60, 2);
        } else {
            $day->net_hours = null;
            $day->total_hours = null;
        }

        // ✅ Calcular horas extra
        if ($day->total_hours !== null && $day->expected_hours !== null) {
            $extraHours = $day->total_hours - $day->expected_hours;
            $day->extra_hours = $extraHours > 0 ? round($extraHours, 2) : 0;
        } else {
            $day->extra_hours = null;
        }

        // ✅ Calcular minutos de llegada tarde
        if ($scheduledCheckIn && $day->check_in_time) {
            $expected = Carbon::parse($scheduledCheckIn);
            $actual = Carbon::parse($day->check_in_time);
            if ($actual->greaterThan($expected)) {
                $day->late_minutes = $expected->diffInMinutes($actual);
            } else {
                $day->late_minutes = 0;
            }
        } else {
            $day->late_minutes = null;
        }

        // ✅ Calcular minutos de salida anticipada
        if ($scheduledCheckOut && $day->check_out_time) {
            $expected = Carbon::parse($scheduledCheckOut);
            $actual = Carbon::parse($day->check_out_time);
            if ($actual->lessThan($expected)) {
                $day->early_leave_minutes = $expected->diffInMinutes($actual);
            } else {
                $day->early_leave_minutes = 0;
            }
        } else {
            $day->early_leave_minutes = null;
        }
    }
}
