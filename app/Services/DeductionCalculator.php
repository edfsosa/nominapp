<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\Log;

class DeductionCalculator
{
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $items = [];
        $total = 0;

        $assignments = $employee->employeeDeductions()
            ->forPeriod($period->start_date, $period->end_date)
            ->with('deduction')
            ->get();

        foreach ($assignments as $assignment) {
            $deduction    = $assignment->deduction;
            $customAmount = $assignment->custom_amount;
            $salaryBase   = 0;

            if ($customAmount === null && $deduction->isPercentage()) {
                $salaryBase = $employee->employment_type === 'day_laborer'
                    ? (int) ($employee->daily_rate ?? 0)
                    : (int) ($employee->base_salary ?? 0);

                if ($salaryBase <= 0) {
                    Log::warning('DeductionCalculator: porcentaje sobre salario base inválido', [
                        'employee_id'     => $employee->id,
                        'deduction'       => $deduction->name,
                        'salary_base'     => $salaryBase,
                        'employment_type' => $employee->employment_type,
                    ]);

                    $items[] = ['description' => $deduction->name, 'amount' => 0];
                    continue;
                }
            }

            $amount = $deduction->calculateAmount($salaryBase, $customAmount);
            $total += $amount;

            $items[] = [
                'description' => $deduction->name,
                'amount'      => $amount,
            ];
        }

        return [
            'total' => $total,
            'items' => $items,
        ];
    }
}
