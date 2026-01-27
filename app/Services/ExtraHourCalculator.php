<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;

class ExtraHourCalculator
{
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $monthlyHours = config('payroll.hours.monthly');
        $hourlyRate = $employee->base_salary / $monthlyHours;

        $totalExtra = 0;
        $totalHours = 0;

        $days = $employee->attendanceDays()
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->where('extra_hours', '>', 0)
            ->where('overtime_approved', true)
            ->get();

        $holidayMultiplier = config('payroll.overtime_multipliers.holiday_weekend');
        $regularMultiplier = config('payroll.overtime_multipliers.regular');

        foreach ($days as $day) {
            $multiplier = ($day->is_holiday || $day->is_weekend) ? $holidayMultiplier : $regularMultiplier;
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
