<?php

namespace App\Observers;

use App\Models\Employee;
use App\Services\ScheduleAssignmentService;
use Carbon\Carbon;

class EmployeeObserver
{
    public function created(Employee $employee): void
    {
        $employee->assignMandatoryDeductions();
    }

    public function updated(Employee $employee): void
    {
        if (! $employee->isDirty('status')) {
            return;
        }

        if ($employee->status === 'suspended') {
            $employee->activeEmployeePerceptions->each(fn($p) => $p->deactivate(bySystem: true));
            $employee->activeEmployeeDeductions->each(fn($d) => $d->deactivate(bySystem: true));
        } elseif ($employee->status === 'inactive') {
            $employee->activeEmployeePerceptions->each->deactivate();
            $employee->activeEmployeeDeductions->each->deactivate();
            ScheduleAssignmentService::closeActive($employee, Carbon::today());
        } elseif ($employee->status === 'active') {
            $employee->employeePerceptions()
                ->where('deactivated_by_system', true)
                ->get()
                ->each(fn($p) => $p->update(['end_date' => null, 'deactivated_by_system' => false]));

            $employee->employeeDeductions()
                ->where('deactivated_by_system', true)
                ->get()
                ->each(fn($d) => $d->update(['end_date' => null, 'deactivated_by_system' => false]));
        }
    }
}
