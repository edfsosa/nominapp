<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;

class AbsencePenaltyCalculator
{
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $hourlyRate = $employee->base_salary / 240;
        $dailyRate = $hourlyRate * 8;

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
