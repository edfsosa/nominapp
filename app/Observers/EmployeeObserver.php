<?php

namespace App\Observers;

use App\Models\Employee;
use App\Services\ScheduleAssignmentService;
use Carbon\Carbon;

class EmployeeObserver
{
    public function created(Employee $employee): void
    {
        if (! Employee::$skipMandatoryDeductions) {
            $employee->assignMandatoryDeductions();
        }
    }

    public function updated(Employee $employee): void
    {
        if (! $employee->isDirty('status')) {
            return;
        }

        if ($employee->status === 'inactive') {
            $employee->activeEmployeePerceptions->each->deactivate();
            $employee->activeEmployeeDeductions->each->deactivate();
            ScheduleAssignmentService::closeActive($employee, Carbon::today());
        }
    }
}
