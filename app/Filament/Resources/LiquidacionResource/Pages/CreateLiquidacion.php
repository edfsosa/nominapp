<?php

namespace App\Filament\Resources\LiquidacionResource\Pages;

use App\Filament\Resources\LiquidacionResource;
use App\Models\Employee;
use Filament\Resources\Pages\CreateRecord;

class CreateLiquidacion extends CreateRecord
{
    protected static string $resource = LiquidacionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $employee = Employee::find($data['employee_id']);

        $data['hire_date'] = $employee->hire_date;
        $data['base_salary'] = $employee->base_salary;
        $data['daily_salary'] = round($employee->base_salary / 30, 2);
        $data['created_by_id'] = auth()->id();
        $data['status'] = 'draft';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
