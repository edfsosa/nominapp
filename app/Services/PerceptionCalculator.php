<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\Log;

class PerceptionCalculator
{
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $items = [];
        $total = 0;

        $perceptions = $employee->perceptions()
            ->wherePivot('start_date', '<=', $period->end_date)
            ->where(function ($query) use ($period) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $period->start_date);
            })
            ->get();

        foreach ($perceptions as $perception) {
            if (!is_null($perception->pivot->custom_amount)) {
                $amount = $perception->pivot->custom_amount;
            } elseif ($perception->calculation === 'percentage') {
                // Para jornaleros usar daily_rate como base, para tiempo completo usar base_salary
                $salaryBase = $employee->employment_type === 'day_laborer'
                    ? $employee->daily_rate
                    : $employee->base_salary;

                if (!$salaryBase || $salaryBase <= 0) {
                    Log::warning('PerceptionCalculator: porcentaje sobre salario base inválido', [
                        'employee_id' => $employee->id,
                        'perception' => $perception->name,
                        'salary_base' => $salaryBase,
                        'employment_type' => $employee->employment_type,
                    ]);
                    $amount = 0;
                } else {
                    $amount = round($salaryBase * ($perception->percent / 100), 2);
                }
            } else {
                $amount = $perception->amount ?? 0;
            }

            $total += $amount;

            $items[] = [
                'description' => $perception->name,
                'amount' => $amount,
            ];
        }

        return [
            'total' => $total,
            'items' => $items,
        ];
    }
}
