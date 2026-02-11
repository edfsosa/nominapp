<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Settings\PayrollSettings;

class ExtraHourCalculator
{
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $settings = app(PayrollSettings::class);
        $monthlyHours = $employee->schedule?->getMonthlyHours()
            ?? $settings->monthly_hours;
        $hourlyRate = $employee->base_salary / $monthlyHours;

        $days = $employee->attendanceDays()
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->where('extra_hours', '>', 0)
            ->where('overtime_approved', true)
            ->get();

        $multiplierDiurno = $settings->overtime_multiplier_diurno;
        $multiplierNocturno = $settings->overtime_multiplier_nocturno;
        $multiplierHoliday = $settings->overtime_multiplier_holiday;

        $buckets = [
            'diurnas' => ['hours' => 0, 'amount' => 0],
            'nocturnas' => ['hours' => 0, 'amount' => 0],
            'feriado_domingo' => ['hours' => 0, 'amount' => 0],
        ];

        foreach ($days as $day) {
            if ($day->is_holiday || $day->is_weekend) {
                // Feriado/domingo: todas las horas al 100% recargo
                $hours = (float) $day->extra_hours;
                $buckets['feriado_domingo']['hours'] += $hours;
                $buckets['feriado_domingo']['amount'] += round($hours * $hourlyRate * $multiplierHoliday, 2);
            } else {
                // Día regular: desglosar diurnas/nocturnas
                $diurnas = (float) ($day->extra_hours_diurnas ?? $day->extra_hours);
                $nocturnas = (float) ($day->extra_hours_nocturnas ?? 0);

                if ($diurnas > 0) {
                    $buckets['diurnas']['hours'] += $diurnas;
                    $buckets['diurnas']['amount'] += round($diurnas * $hourlyRate * $multiplierDiurno, 2);
                }
                if ($nocturnas > 0) {
                    $buckets['nocturnas']['hours'] += $nocturnas;
                    $buckets['nocturnas']['amount'] += round($nocturnas * $hourlyRate * $multiplierNocturno, 2);
                }
            }
        }

        $items = [];
        $total = 0;
        $totalHours = 0;

        if ($buckets['diurnas']['hours'] > 0) {
            $items[] = [
                'description' => "Horas Extras Diurnas ({$buckets['diurnas']['hours']}h al 50%)",
                'amount' => $buckets['diurnas']['amount'],
            ];
            $total += $buckets['diurnas']['amount'];
            $totalHours += $buckets['diurnas']['hours'];
        }

        if ($buckets['nocturnas']['hours'] > 0) {
            $items[] = [
                'description' => "Horas Extras Nocturnas ({$buckets['nocturnas']['hours']}h al 160%)",
                'amount' => $buckets['nocturnas']['amount'],
            ];
            $total += $buckets['nocturnas']['amount'];
            $totalHours += $buckets['nocturnas']['hours'];
        }

        if ($buckets['feriado_domingo']['hours'] > 0) {
            $items[] = [
                'description' => "Horas Extras Feriado/Domingo ({$buckets['feriado_domingo']['hours']}h al 100%)",
                'amount' => $buckets['feriado_domingo']['amount'],
            ];
            $total += $buckets['feriado_domingo']['amount'];
            $totalHours += $buckets['feriado_domingo']['hours'];
        }

        return [
            'total' => $total,
            'hours' => $totalHours,
            'items' => $items,
        ];
    }
}
