<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Settings\PayrollSettings;

class AbsencePenaltyCalculator
{
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $settings = app(PayrollSettings::class);
        $monthlyHours = $settings->monthly_hours;
        $dailyHours = $settings->daily_hours;

        $hourlyRate = $employee->base_salary / $monthlyHours;
        $dailyRate = $hourlyRate * $dailyHours;

        $absentDays = $employee->attendanceDays()
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->where('status', 'absent')
            ->where('is_holiday', false)
            ->where('is_weekend', false)
            ->get();

        $total = round($absentDays->count() * $dailyRate, 2);

        return [
            'total' => $total,
            'days' => $absentDays->count(),
            'items' => $absentDays->isNotEmpty() ? [[
                'description' => "Ausencias Injustificadas ({$absentDays->count()} día/s)",
                'amount' => $total,
            ]] : [],
        ];
    }
}
