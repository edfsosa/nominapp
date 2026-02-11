<?php

namespace App\Observers;

use App\Models\Employee;

class EmployeeObserver
{
    public function created(Employee $employee): void
    {
        $employee->assignMandatoryDeductions();
    }
}
