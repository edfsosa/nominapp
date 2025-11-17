<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;

class DeductionCalculator
{
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $items = [];
        $total = 0;

        $deductions = $employee->deductions()
            ->wherePivot('start_date', '<=', $period->end_date)
            ->where(function ($query) use ($period) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $period->start_date);
            })
            ->get();

        foreach ($deductions as $deduction) {
            if (!is_null($deduction->pivot->custom_amount)) {
                $amount = $deduction->pivot->custom_amount;
            } elseif ($deduction->calculation === 'percentage') {
                $amount = round($employee->base_salary * ($deduction->percent / 100), 2);
            } else {
                $amount = $deduction->amount ?? 0;
            }

            $total += $amount;

            $items[] = [
                'description' => $deduction->name,
                'amount' => $amount,
            ];
        }

        return [
            'total' => $total,
            'items' => $items,
        ];
    }
}
