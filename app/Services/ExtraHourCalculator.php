<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;

class ExtraHourCalculator
{
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $hourlyRate = $employee->base_salary / 240;

        $totalExtra = 0;
        $totalHours = 0;

        $days = $employee->attendanceDays()
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->where('extra_hours', '>', 0)
            ->where('overtime_approved', true)
            ->get();

        foreach ($days as $day) {
            $multiplier = ($day->is_holiday || $day->is_weekend) ? 2.0 : 1.5;
            $hours = $day->extra_hours;
            $amount = round($hours * $hourlyRate * $multiplier, 2);

            $totalExtra += $amount;
            $totalHours += $hours;
        }

        return [
            'total' => $totalExtra,
            'hours' => $totalHours,
            'items' => $totalHours > 0 ? [[
                'description' => "Horas Extras Aprobadas ({$totalHours}h)",
                'amount' => $totalExtra,
            ]] : [],
        ];
    }
}
