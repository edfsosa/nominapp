<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PayrollService
{
    protected PerceptionCalculator $perceptionCalculator;
    protected DeductionCalculator $deductionCalculator;
    protected ExtraHourCalculator $extraHourCalculator;
    protected AbsencePenaltyCalculator $absencePenaltyCalculator;
    protected LoanInstallmentCalculator $loanInstallmentCalculator;
    protected PayrollPDFGenerator $payrollPDFGenerator;

    public function __construct(
        PerceptionCalculator $perceptionCalculator,
        DeductionCalculator $deductionCalculator,
        ExtraHourCalculator $extraHourCalculator,
        AbsencePenaltyCalculator $absencePenaltyCalculator,
        LoanInstallmentCalculator $loanInstallmentCalculator,
        PayrollPDFGenerator $payrollPDFGenerator
    ) {
        $this->perceptionCalculator = $perceptionCalculator;
        $this->deductionCalculator = $deductionCalculator;
        $this->extraHourCalculator = $extraHourCalculator;
        $this->absencePenaltyCalculator = $absencePenaltyCalculator;
        $this->loanInstallmentCalculator = $loanInstallmentCalculator;
        $this->payrollPDFGenerator = $payrollPDFGenerator;
    }

    public function generateForPeriod(PayrollPeriod $period): int
    {
        $count = 0;

        $employees = Employee::query()
            ->where('payroll_type', $period->frequency)
            ->whereNotNull('base_salary')
            ->where('status', 'active')
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
                $loanInstallments = $this->loanInstallmentCalculator->calculate($employee, $period);

                $totalPerceptions = $perceptions['total'] + $extras['total'];
                $totalDeductions = $deductions['total'] + $absences['total'] + $loanInstallments['total'];
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

                // Ítems: deducciones (incluye cuotas de préstamos/adelantos)
                foreach (array_merge($deductions['items'], $absences['items'], $loanInstallments['items']) as $item) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'deduction',
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                    ]);
                }

                // Marcar cuotas de préstamos como pagadas
                if ($loanInstallments['installments']->isNotEmpty()) {
                    $installmentIds = $loanInstallments['installments']->pluck('id')->toArray();
                    $this->loanInstallmentCalculator->markInstallmentsAsPaid($installmentIds);
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

    public function regenerateForEmployee(Payroll $payroll): Payroll
    {
        $employee = $payroll->employee;
        $period = $payroll->period;

        DB::beginTransaction();

        try {
            // Eliminar ítems existentes
            $payroll->items()->delete();

            // Eliminar PDF anterior
            if ($payroll->pdf_path && Storage::disk('public')->exists($payroll->pdf_path)) {
                Storage::disk('public')->delete($payroll->pdf_path);
            }

            $baseSalary = $employee->base_salary;

            // Recalcular con los 5 calculadores
            $perceptions = $this->perceptionCalculator->calculate($employee, $period);
            $deductions = $this->deductionCalculator->calculate($employee, $period);
            $extras = $this->extraHourCalculator->calculate($employee, $period);
            $absences = $this->absencePenaltyCalculator->calculate($employee, $period);
            $loanInstallments = $this->loanInstallmentCalculator->calculate($employee, $period);

            $totalPerceptions = $perceptions['total'] + $extras['total'];
            $totalDeductions = $deductions['total'] + $absences['total'] + $loanInstallments['total'];
            $netSalary = $baseSalary + $totalPerceptions - $totalDeductions;

            // Actualizar el registro existente
            $payroll->update([
                'base_salary' => $baseSalary,
                'total_perceptions' => $totalPerceptions,
                'total_deductions' => $totalDeductions,
                'gross_salary' => $baseSalary + $totalPerceptions,
                'net_salary' => $netSalary,
                'generated_at' => now(),
            ]);

            // Recrear ítems: percepciones
            foreach (array_merge($perceptions['items'], $extras['items']) as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'perception',
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            // Recrear ítems: deducciones
            foreach (array_merge($deductions['items'], $absences['items'], $loanInstallments['items']) as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'deduction',
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            // Marcar cuotas de préstamos como pagadas
            if ($loanInstallments['installments']->isNotEmpty()) {
                $installmentIds = $loanInstallments['installments']->pluck('id')->toArray();
                $this->loanInstallmentCalculator->markInstallmentsAsPaid($installmentIds);
            }

            // Regenerar PDF
            $pdfPath = $this->payrollPDFGenerator->generate($payroll);
            $payroll->update(['pdf_path' => $pdfPath]);

            DB::commit();

            return $payroll->refresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al regenerar recibo: ' . $e->getMessage(), [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
            ]);
            throw $e;
        }
    }
}
