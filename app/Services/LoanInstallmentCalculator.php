<?php

namespace App\Services;

use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\Log;

/**
 * Calcula y registra las cuotas de préstamos/adelantos que vencen en el período.
 *
 * Por cada cuota encontrada crea (o actualiza) un registro en `employee_deductions`
 * usando el código PRE001 (préstamo) o ADE001 (adelanto), de modo que
 * DeductionCalculator las procese junto al resto de deducciones del empleado.
 */
class LoanInstallmentCalculator
{
    /** @var int|null ID de la deducción PRE001 (Cuota de Préstamo) */
    private ?int $loanDeductionId = null;

    /** @var int|null ID de la deducción ADE001 (Cuota de Adelanto) */
    private ?int $advanceDeductionId = null;

    /**
     * Identifica cuotas vencidas en el período, crea sus EmployeeDeduction y
     * retorna la colección de instancias para trazabilidad posterior.
     *
     * @param  Employee      $employee
     * @param  PayrollPeriod $period
     * @return array{installments: \Illuminate\Support\Collection}
     */
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $processedInstallments = collect();

        $installments = LoanInstallment::query()
            ->whereHas('loan', fn($q) => $q
                ->where('employee_id', $employee->id)
                ->whereIn('status', ['active', 'defaulted']))
            ->where('status', 'pending')
            ->whereBetween('due_date', [$period->start_date, $period->end_date])
            ->with('loan')
            ->orderBy('due_date')
            ->get();

        foreach ($installments as $installment) {
            $deductionId = $installment->loan->isAdvance()
                ? $this->getAdvanceDeductionId()
                : $this->getLoanDeductionId();

            if ($deductionId === null) {
                Log::warning('LoanInstallmentCalculator: deducción PRE001/ADE001 no encontrada. Verificá el seeder.', [
                    'installment_id' => $installment->id,
                    'loan_type'      => $installment->loan->type,
                ]);
                continue;
            }

            $notes = $this->buildDescription($installment);

            // Idempotente: si ya tiene EmployeeDeduction del ciclo anterior (regeneración),
            // actualiza el monto; si no, crea uno nuevo.
            if ($installment->employee_deduction_id) {
                $employeeDeduction = EmployeeDeduction::find($installment->employee_deduction_id);

                if ($employeeDeduction) {
                    $employeeDeduction->update([
                        'custom_amount' => (float) $installment->amount,
                        'notes'         => $notes,
                    ]);
                } else {
                    // El registro fue eliminado (payroll previo borrado); crear uno nuevo
                    $employeeDeduction = $this->createEmployeeDeduction(
                        $employee->id, $deductionId, $installment, $notes
                    );
                }
            } else {
                $employeeDeduction = $this->createEmployeeDeduction(
                    $employee->id, $deductionId, $installment, $notes
                );
            }

            // Guardar referencia en la cuota
            $installment->update(['employee_deduction_id' => $employeeDeduction->id]);

            $processedInstallments->push($installment);
        }

        return ['installments' => $processedInstallments];
    }

    /**
     * Marca las cuotas como pagadas después de que la nómina pasa a estado 'paid'.
     *
     * @param  array    $installmentIds IDs de las cuotas a marcar como pagadas
     * @param  int|null $payrollId      ID de la nómina que cubre estas cuotas
     * @return int Número de cuotas marcadas como pagadas
     */
    public function markInstallmentsAsPaid(array $installmentIds, ?int $payrollId = null): int
    {
        if (empty($installmentIds)) {
            return 0;
        }

        $installments = LoanInstallment::whereIn('id', $installmentIds)
            ->where('status', 'pending')
            ->get();

        $now = now();

        foreach ($installments as $installment) {
            $notes = ($installment->notes ? $installment->notes . "\n" : '') .
                'Pagado automáticamente vía nómina.';

            $installment->update([
                'status'     => 'paid',
                'paid_at'    => $now,
                'notes'      => $notes,
                'payroll_id' => $payrollId,
            ]);
        }

        // Verificar una sola vez por préstamo único si ya está completamente pagado
        $installments->pluck('loan_id')->unique()->each(function (int $loanId) {
            \App\Models\Loan::find($loanId)?->checkIfPaid();
        });

        return $installments->count();
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * Crea un registro EmployeeDeduction puntual (start_date = end_date = due_date)
     * para que DeductionCalculator lo recoja en ese período específico.
     */
    private function createEmployeeDeduction(
        int $employeeId,
        int $deductionId,
        LoanInstallment $installment,
        string $notes,
    ): EmployeeDeduction {
        return EmployeeDeduction::create([
            'employee_id'  => $employeeId,
            'deduction_id' => $deductionId,
            'start_date'   => $installment->due_date,
            'end_date'     => $installment->due_date,
            'custom_amount'=> (float) $installment->amount,
            'notes'        => $notes,
        ]);
    }

    /**
     * Construye la descripción que aparecerá en el recibo de nómina.
     * Ejemplo: "Préstamo - Cuota 3/10"
     */
    private function buildDescription(LoanInstallment $installment): string
    {
        $loanType = $installment->loan->isAdvance() ? 'Adelanto' : 'Préstamo';
        return "{$loanType} - Cuota {$installment->installment_number}/{$installment->loan->installments_count}";
    }

    /**
     * Retorna el ID de la deducción PRE001, cacheado para el ciclo de nómina.
     */
    private function getLoanDeductionId(): ?int
    {
        if ($this->loanDeductionId === null) {
            $this->loanDeductionId = Deduction::where('code', 'PRE001')->value('id');
        }
        return $this->loanDeductionId;
    }

    /**
     * Retorna el ID de la deducción ADE001, cacheado para el ciclo de nómina.
     */
    private function getAdvanceDeductionId(): ?int
    {
        if ($this->advanceDeductionId === null) {
            $this->advanceDeductionId = Deduction::where('code', 'ADE001')->value('id');
        }
        return $this->advanceDeductionId;
    }
}
