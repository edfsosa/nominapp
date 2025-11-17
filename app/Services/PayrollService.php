<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    protected PerceptionCalculator $perceptionCalculator;
    protected DeductionCalculator $deductionCalculator;
    protected ExtraHourCalculator $extraHourCalculator;
    protected AbsencePenaltyCalculator $absencePenaltyCalculator;
    protected PayrollPDFGenerator $payrollPDFGenerator;

    public function __construct(
        PerceptionCalculator $perceptionCalculator,
        DeductionCalculator $deductionCalculator,
        ExtraHourCalculator $extraHourCalculator,
        AbsencePenaltyCalculator $absencePenaltyCalculator,
        PayrollPDFGenerator $payrollPDFGenerator
    ) {
        $this->perceptionCalculator = $perceptionCalculator;
        $this->deductionCalculator = $deductionCalculator;
        $this->extraHourCalculator = $extraHourCalculator;
        $this->absencePenaltyCalculator = $absencePenaltyCalculator;
        $this->payrollPDFGenerator = $payrollPDFGenerator;
    }

    public function generateForPeriod(PayrollPeriod $period): int
    {
        $count = 0;

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

            DB::beginTransaction();

            try {
                $baseSalary = $employee->base_salary;

                // Cálculo modular
                $perceptions = $this->perceptionCalculator->calculate($employee, $period);
                $deductions = $this->deductionCalculator->calculate($employee, $period);
                $extras = $this->extraHourCalculator->calculate($employee, $period);
                $absences = $this->absencePenaltyCalculator->calculate($employee, $period);

                $totalPerceptions = $perceptions['total'] + $extras['total'];
                $totalDeductions = $deductions['total'] + $absences['total'];
                $netSalary = $baseSalary + $totalPerceptions - $totalDeductions;

                $payroll = Payroll::create([
                    'employee_id' => $employee->id,
                    'payroll_period_id' => $period->id,
                    'base_salary' => $baseSalary,
                    'total_perceptions' => $totalPerceptions,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                    'gross_salary' => $baseSalary + $totalPerceptions,
                    'generated_at' => now(),
                ]);

                // Ítems: percepciones
                foreach (array_merge($perceptions['items'], $extras['items']) as $item) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'perception',
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                    ]);
                }

                // Ítems: deducciones
                foreach (array_merge($deductions['items'], $absences['items']) as $item) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'deduction',
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                    ]);
                }

                // Generar PDF
                $pdfPath = $this->payrollPDFGenerator->generate($payroll);
                $payroll->update(['pdf_path' => $pdfPath]);

                DB::commit();
                $count++;
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Error al generar recibo: ' . $e->getMessage(), ['employee_id' => $employee->id]);
            }
        }
        return $count;
    }
}
