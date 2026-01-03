<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;

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
                $amount = round($employee->base_salary * ($perception->percent / 100), 2);
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
