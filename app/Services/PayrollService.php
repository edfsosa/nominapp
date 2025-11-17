<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PayrollService
{
    public function generateForPeriod(PayrollPeriod $period): int
    {
        $createdCount = 0;
        $now = now();

        $employees = Employee::query()
            ->where('payroll_type', $period->frequency)
            ->whereNotNull('base_salary')
            ->whereIn('status', ['active', 'suspended'])
            ->get();

        foreach ($employees as $employee) {
            // Evitar duplicados
            if (Payroll::where('employee_id', $employee->id)
                ->where('payroll_period_id', $period->id)
                ->exists()
            ) {
                continue;
            }

            $baseSalary = $employee->base_salary;

            // Obtener percepciones activas
            $perceptions = $employee->perceptions()
                ->wherePivot('start_date', '<=', $now)
                ->where(function ($query) use ($now) {
                    $query->whereNull('end_date')->orWhere('end_date', '>=', $now);
                })
                ->get();

            // Obtener deducciones activas
            $deductions = $employee->deductions()
                ->wherePivot('start_date', '<=', $now)
                ->where(function ($query) use ($now) {
                    $query->whereNull('end_date')->orWhere('end_date', '>=', $now);
                })
                ->get();

            // Calcular percepciones y deducciones con lógica de tipo
            $totalPerceptions = 0;
            $totalDeductions = 0;
            $calculatedPerceptions = [];
            $calculatedDeductions = [];

            foreach ($perceptions as $perception) {
                if (!is_null($perception->pivot->custom_amount)) {
                    $amount = $perception->pivot->custom_amount;
                } elseif ($perception->calculation === 'percentage') {
                    $amount = round($baseSalary * ($perception->percent / 100), 2);
                } else {
                    $amount = $perception->amount ?? 0;
                }

                $totalPerceptions += $amount;

                $calculatedPerceptions[] = [
                    'description' => $perception->name,
                    'amount' => $amount,
                ];
            }

            $gross = $baseSalary + $totalPerceptions;

            foreach ($deductions as $deduction) {
                if (!is_null($deduction->pivot->custom_amount)) {
                    $amount = $deduction->pivot->custom_amount;
                } elseif ($deduction->calculation === 'percentage') {
                    $amount = round($gross * ($deduction->percent / 100), 2);
                } else {
                    $amount = $deduction->amount ?? 0;
                }

                $totalDeductions += $amount;

                $calculatedDeductions[] = [
                    'description' => $deduction->name,
                    'amount' => $amount,
                ];
            }

            $net = $gross - $totalDeductions;

            // Crear nómina
            $payroll = Payroll::create([
                'employee_id' => $employee->id,
                'payroll_period_id' => $period->id,
                'base_salary' => $baseSalary,
                'total_perceptions' => $totalPerceptions,
                'gross_salary' => $gross,
                'total_deductions' => $totalDeductions,
                'net_salary' => $net,
                'generated_at' => now(),
            ]);

            // Guardar ítems
            foreach ($calculatedPerceptions as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'perception',
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            foreach ($calculatedDeductions as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'deduction',
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            // Generar y guardar PDF
            $pdf = Pdf::loadView('pdf.payroll', compact('payroll'));
            $filename = 'payrolls/' . now()->format('Y') . '/' . now()->format('m') . '/payroll_' . $payroll->id . '.pdf';
            Storage::put($filename, $pdf->output());

            $payroll->update(['pdf_path' => $filename]);

            $createdCount++;
        }

        return $createdCount;
    }
}
