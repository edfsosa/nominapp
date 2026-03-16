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

        $assignments = $employee->employeePerceptions()
            ->forPeriod($period->start_date, $period->end_date)
            ->with('perception')
            ->get();

        foreach ($assignments as $assignment) {
            $perception   = $assignment->perception;
            $customAmount = $assignment->custom_amount;
            $salaryBase   = 0;

            if ($customAmount === null && $perception->isPercentage()) {
                $salaryBase = $employee->employment_type === 'day_laborer'
                    ? (int) ($employee->daily_rate ?? 0)
                    : (int) ($employee->base_salary ?? 0);

                if ($salaryBase <= 0) {
                    Log::warning('PerceptionCalculator: porcentaje sobre salario base inválido', [
                        'employee_id'     => $employee->id,
                        'perception'      => $perception->name,
                        'salary_base'     => $salaryBase,
                        'employment_type' => $employee->employment_type,
                    ]);

                    $items[] = ['description' => $perception->name, 'amount' => 0];
                    continue;
                }
            }

            $amount = $perception->calculateAmount($salaryBase, $customAmount);
            $total += $amount;

            $items[] = [
                'description' => $perception->name,
                'amount'      => $amount,
            ];
        }

        return [
            'total' => $total,
            'items' => $items,
        ];
    }
}
