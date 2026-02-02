<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LoanInstallment;
use App\Models\PayrollPeriod;

class LoanInstallmentCalculator
{
    /**
     * Calcula las cuotas de préstamos/adelantos pendientes para el período de nómina.
     *
     * @param Employee $employee
     * @param PayrollPeriod $period
     * @return array ['total' => float, 'items' => array, 'installments' => Collection]
     */
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $items = [];
        $total = 0;
        $processedInstallments = collect();

        // Obtener cuotas pendientes con fecha de vencimiento en el período
        $installments = LoanInstallment::query()
            ->whereHas('loan', fn($q) => $q
                ->where('employee_id', $employee->id)
                ->where('status', 'active'))
            ->where('status', 'pending')
            ->whereBetween('due_date', [$period->start_date, $period->end_date])
            ->with('loan')
            ->orderBy('due_date')
            ->get();

        foreach ($installments as $installment) {
            $amount = (float) $installment->amount;
            $total += $amount;

            $loanType = $installment->loan->isAdvance() ? 'Adelanto' : 'Préstamo';
            $items[] = [
                'description' => "{$loanType} - Cuota {$installment->installment_number}/{$installment->loan->installments_count}",
                'amount' => $amount,
                'installment_id' => $installment->id,
            ];

            $processedInstallments->push($installment);
        }

        return [
            'total' => $total,
            'items' => $items,
            'installments' => $processedInstallments,
        ];
    }

    /**
     * Marca las cuotas como pagadas después de generar la nómina.
     *
     * @param array $installmentIds
     * @return int Número de cuotas marcadas como pagadas
     */
    public function markInstallmentsAsPaid(array $installmentIds): int
    {
        $count = 0;

        foreach ($installmentIds as $id) {
            $installment = LoanInstallment::find($id);

            if ($installment && $installment->isPending()) {
                $installment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'notes' => ($installment->notes ? $installment->notes . "\n" : '') .
                        'Pagado automáticamente vía nómina.',
                ]);

                // Verificar si el préstamo está completamente pagado
                $installment->loan->checkIfPaid();

                $count++;
            }
        }

        return $count;
    }
}
