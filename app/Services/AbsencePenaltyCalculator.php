<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Settings\PayrollSettings;
use Illuminate\Support\Facades\Log;

class AbsencePenaltyCalculator
{
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $emptyResult = ['total' => 0, 'days' => 0, 'items' => []];

        // Para jornaleros, la penalización por ausencia es simplemente no cobrar ese día
        // (ya que su salario base se calcula por días trabajados).
        // Solo aplicamos penalización si es tiempo completo.
        if ($employee->employment_type === 'day_laborer') {
            return $emptyResult;
        }

        if (!$employee->base_salary || $employee->base_salary <= 0) {
            Log::warning('AbsencePenaltyCalculator: empleado sin salario base válido', [
                'employee_id' => $employee->id,
                'base_salary' => $employee->base_salary,
            ]);
            return $emptyResult;
        }

        $settings = app(PayrollSettings::class);
        $monthlyHours = $settings->monthly_hours;
        $dailyHours = $settings->daily_hours;

        if ($monthlyHours <= 0 || $dailyHours <= 0) {
            Log::warning('AbsencePenaltyCalculator: configuración de horas inválida', [
                'monthly_hours' => $monthlyHours,
                'daily_hours' => $dailyHours,
            ]);
            return $emptyResult;
        }

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
