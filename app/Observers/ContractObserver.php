<?php

namespace App\Observers;

use App\Models\Contract;

/**
 * Sincroniza percepciones y deducciones del empleado cuando el estado
 * del contrato cambia entre active y suspended.
 */
class ContractObserver
{
    public function updated(Contract $contract): void
    {
        if (! $contract->isDirty('status')) {
            return;
        }

        $employee = $contract->employee;

        if ($contract->status === 'suspended') {
            $employee->activeEmployeePerceptions->each(fn ($p) => $p->deactivate(bySystem: true));
            $employee->activeEmployeeDeductions->each(fn ($d) => $d->deactivate(bySystem: true));
        } elseif ($contract->status === 'active' && $contract->getOriginal('status') === 'suspended') {
            $employee->employeePerceptions()
                ->where('deactivated_by_system', true)
                ->get()
                ->each(fn ($p) => $p->update(['end_date' => null, 'deactivated_by_system' => false]));

            $employee->employeeDeductions()
                ->where('deactivated_by_system', true)
                ->get()
                ->each(fn ($d) => $d->update(['end_date' => null, 'deactivated_by_system' => false]));
        }
    }
}
