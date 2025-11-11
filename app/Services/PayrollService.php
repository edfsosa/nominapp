<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\EmployeeDeduction;
use App\Models\EmployeePerception;


class PayrollService
{
    public function generateForPeriod(PayrollPeriod $period): int
    {
        $createdCount = 0;

        $frecuency = $period->frequency;

        $monthlyEmployees = Employee::withPayrollTypeAndSalary('monthly')->get();
        $biweeklyEmployees = Employee::withPayrollTypeAndSalary('biweekly')->get();
        $weeklyEmployees = Employee::withPayrollTypeAndSalary('weekly')->get();

        if ($frecuency === 'monthly') {
            // Lógica específica para nóminas mensuales si es necesario
            foreach ($monthlyEmployees as $employee) {
                // Evitar duplicados
                if (Payroll::where('employee_id', $employee->id)
                    ->where('payroll_period_id', $period->id)->exists()
                ) {
                    continue;
                }

                // Obtener percepciones activas
                $perceptions = EmployeePerception::where('employee_id', $employee->id)->get();
                $deductions = EmployeeDeduction::where('employee_id', $employee->id)->get();

                $baseSalary = $employee->base_salary;

                $totalPerceptions = $perceptions->sum('custom_amount');
                $totalDeductions = $deductions->sum('custom_amount');
                $gross = $totalPerceptions + $baseSalary;
                $net = $gross - $totalDeductions;

                // Crear recibo
                $payroll = Payroll::create([
                    'employee_id' => $employee->id,
                    'payroll_period_id' => $period->id,
                    'gross_salary' => $gross,
                    'total_perceptions' => $gross,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $net,
                ]);

                // Item de salario base
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'perception',
                    'description' => 'Salario Base',
                    'amount' => $baseSalary,
                ]);

                // Items de percepciones
                foreach ($perceptions as $p) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'perception',
                        'description' => $p->perception->name,
                        'amount' => $p->custom_amount ?? 0,
                    ]);
                }

                // Items de deducciones
                foreach ($deductions as $d) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'deduction',
                        'description' => $d->deduction->name,
                        'amount' => $d->custom_amount ?? 0,
                    ]);
                }

                $createdCount++;
            }
        } elseif ($frecuency === 'biweekly') {
            // Lógica específica para nóminas quincenales si es necesario
            foreach ($biweeklyEmployees as $employee) {
                // Evitar duplicados
                if (Payroll::where('employee_id', $employee->id)
                    ->where('payroll_period_id', $period->id)->exists()
                ) {
                    continue;
                }

                // Obtener percepciones activas
                $perceptions = EmployeePerception::where('employee_id', $employee->id)->get();
                $deductions = EmployeeDeduction::where('employee_id', $employee->id)->get();

                $baseSalary = $employee->base_salary / 2; // Ajuste para quincenal

                $totalPerceptions = $perceptions->sum('custom_amount') / 2;
                $totalDeductions = $deductions->sum('custom_amount') / 2;
                $gross = $totalPerceptions + $baseSalary;
                $net = $gross - $totalDeductions;

                // Crear recibo
                $payroll = Payroll::create([
                    'employee_id' => $employee->id,
                    'payroll_period_id' => $period->id,
                    'gross_salary' => $gross,
                    'total_perceptions' => $gross,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $net,
                ]);

                // Item de salario base
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'perception',
                    'description' => 'Salario Base',
                    'amount' => $baseSalary,
                ]);

                // Items de percepciones
                foreach ($perceptions as $p) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'perception',
                        'description' => $p->perception->name,
                        'amount' => ($p->custom_amount ?? 0) / 2,
                    ]);
                }

                // Items de deducciones
                foreach ($deductions as $d) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'deduction',
                        'description' => $d->deduction->name,
                        'amount' => ($d->custom_amount ?? 0) / 2,
                    ]);
                }

                $createdCount++;
            }
        } elseif ($frecuency === 'weekly') {
            // Lógica específica para nóminas semanales si es necesario
            foreach ($weeklyEmployees as $employee) {
                // Evitar duplicados
                if (Payroll::where('employee_id', $employee->id)
                    ->where('payroll_period_id', $period->id)->exists()
                ) {
                    continue;
                }

                // Obtener percepciones activas
                $perceptions = EmployeePerception::where('employee_id', $employee->id)->get();
                $deductions = EmployeeDeduction::where('employee_id', $employee->id)->get();

                $baseSalary = $employee->base_salary / 4; // Ajuste para semanal

                $totalPerceptions = $perceptions->sum('custom_amount') / 4;
                $totalDeductions = $deductions->sum('custom_amount') / 4;
                $gross = $totalPerceptions + $baseSalary;
                $net = $gross - $totalDeductions;

                // Crear recibo
                $payroll = Payroll::create([
                    'employee_id' => $employee->id,
                    'payroll_period_id' => $period->id,
                    'gross_salary' => $gross,
                    'total_perceptions' => $gross,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $net,
                ]);

                // Item de salario base
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'perception',
                    'description' => 'Salario Base',
                    'amount' => $baseSalary,
                ]);

                // Items de percepciones
                foreach ($perceptions as $p) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'perception',
                        'description' => $p->perception->name,
                        'amount' => ($p->custom_amount ?? 0) / 4,
                    ]);
                }

                // Items de deducciones
                foreach ($deductions as $d) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'deduction',
                        'description' => $d->deduction->name,
                        'amount' => ($d->custom_amount ?? 0) / 4,
                    ]);
                }

                $createdCount++;
            }
        }

        return $createdCount;
    }
}
