<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Settings\PayrollSettings;
use Illuminate\Support\Facades\Log;

class ExtraHourCalculator
{
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $emptyResult = ['total' => 0, 'hours' => 0, 'items' => []];

        $settings = app(PayrollSettings::class);

        if ($employee->employment_type === 'day_laborer') {
            // Jornaleros: tarifa horaria = daily_rate / horas por jornada
            if (!$employee->daily_rate || $employee->daily_rate <= 0) {
                Log::warning('ExtraHourCalculator: jornalero sin tarifa diaria válida', [
                    'employee_id' => $employee->id,
                    'daily_rate' => $employee->daily_rate,
                ]);
                return $emptyResult;
            }

            $dailyHours = $settings->daily_hours;
            if ($dailyHours <= 0) {
                Log::warning('ExtraHourCalculator: horas diarias inválidas', [
                    'employee_id' => $employee->id,
                    'daily_hours' => $dailyHours,
                ]);
                return $emptyResult;
            }

            $hourlyRate = $employee->daily_rate / $dailyHours;
        } else {
            // Tiempo completo: tarifa horaria = base_salary / horas mensuales
            if (!$employee->base_salary || $employee->base_salary <= 0) {
                Log::warning('ExtraHourCalculator: empleado sin salario base válido', [
                    'employee_id' => $employee->id,
                    'base_salary' => $employee->base_salary,
                ]);
                return $emptyResult;
            }

            $monthlyHours = $employee->getScheduleForDate($period->start_date)?->getMonthlyHours()
                ?? $settings->monthly_hours;

            if ($monthlyHours <= 0) {
                Log::warning('ExtraHourCalculator: horas mensuales inválidas', [
                    'employee_id' => $employee->id,
                    'monthly_hours' => $monthlyHours,
                ]);
                return $emptyResult;
            }

            $hourlyRate = $employee->base_salary / $monthlyHours;
        }

        $days = $employee->attendanceDays()
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->where('extra_hours', '>', 0)
            ->where('overtime_approved', true)
            ->get();

        $multiplierDiurno          = $settings->overtime_multiplier_diurno;
        $multiplierNocturno        = $settings->overtime_multiplier_nocturno;
        $multiplierHoliday         = $settings->overtime_multiplier_holiday;
        $multiplierNocturnoHoliday = $settings->overtime_multiplier_nocturno_holiday;

        $buckets = [
            'diurnas'          => ['hours' => 0, 'amount' => 0],
            'nocturnas'        => ['hours' => 0, 'amount' => 0],
            'feriado_domingo'  => ['hours' => 0, 'amount' => 0],
            'feriado_nocturno' => ['hours' => 0, 'amount' => 0],
        ];

        foreach ($days as $day) {
            if ($day->is_holiday || $day->is_weekend) {
                // Feriado/domingo: desglosar diurnas y nocturnas
                $diurnas  = (float) ($day->extra_hours_diurnas ?? $day->extra_hours);
                $nocturnas = (float) ($day->extra_hours_nocturnas ?? 0);

                if ($diurnas > 0) {
                    $buckets['feriado_domingo']['hours']  += $diurnas;
                    $buckets['feriado_domingo']['amount'] += round($diurnas * $hourlyRate * $multiplierHoliday, 2);
                }
                if ($nocturnas > 0) {
                    $buckets['feriado_nocturno']['hours']  += $nocturnas;
                    $buckets['feriado_nocturno']['amount'] += round($nocturnas * $hourlyRate * $multiplierNocturnoHoliday, 2);
                }
            } else {
                // Día regular: desglosar diurnas/nocturnas
                $diurnas  = (float) ($day->extra_hours_diurnas ?? $day->extra_hours);
                $nocturnas = (float) ($day->extra_hours_nocturnas ?? 0);

                if ($diurnas > 0) {
                    $buckets['diurnas']['hours']  += $diurnas;
                    $buckets['diurnas']['amount'] += round($diurnas * $hourlyRate * $multiplierDiurno, 2);
                }
                if ($nocturnas > 0) {
                    $buckets['nocturnas']['hours']  += $nocturnas;
                    $buckets['nocturnas']['amount'] += round($nocturnas * $hourlyRate * $multiplierNocturno, 2);
                }
            }
        }

        $items = [];
        $total = 0;
        $totalHours = 0;

        if ($buckets['diurnas']['hours'] > 0) {
            $items[] = [
                'description'    => "Horas Extras Diurnas ({$buckets['diurnas']['hours']}h al 50%)",
                'amount'         => $buckets['diurnas']['amount'],
                'perception_type' => 'extra_hours',
            ];
            $total      += $buckets['diurnas']['amount'];
            $totalHours += $buckets['diurnas']['hours'];
        }

        if ($buckets['nocturnas']['hours'] > 0) {
            $items[] = [
                'description'    => "Horas Extras Nocturnas ({$buckets['nocturnas']['hours']}h al 160%)",
                'amount'         => $buckets['nocturnas']['amount'],
                'perception_type' => 'extra_hours',
            ];
            $total      += $buckets['nocturnas']['amount'];
            $totalHours += $buckets['nocturnas']['hours'];
        }

        if ($buckets['feriado_domingo']['hours'] > 0) {
            $items[] = [
                'description'    => "Horas Extras Feriado/Domingo ({$buckets['feriado_domingo']['hours']}h al 100%)",
                'amount'         => $buckets['feriado_domingo']['amount'],
                'perception_type' => 'extra_hours',
            ];
            $total      += $buckets['feriado_domingo']['amount'];
            $totalHours += $buckets['feriado_domingo']['hours'];
        }

        if ($buckets['feriado_nocturno']['hours'] > 0) {
            $items[] = [
                'description'    => "Horas Extras Nocturnas Feriado/Domingo ({$buckets['feriado_nocturno']['hours']}h al 160%)",
                'amount'         => $buckets['feriado_nocturno']['amount'],
                'perception_type' => 'extra_hours',
            ];
            $total      += $buckets['feriado_nocturno']['amount'];
            $totalHours += $buckets['feriado_nocturno']['hours'];
        }

        return [
            'total' => $total,
            'hours' => $totalHours,
            'items' => $items,
        ];
    }
}
