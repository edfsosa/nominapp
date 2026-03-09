<?php

namespace App\Filament\Resources\LiquidacionResource\Pages;

use App\Filament\Resources\LiquidacionResource;
use App\Models\Employee;
use App\Models\Liquidacion;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateLiquidacion extends CreateRecord
{
    protected static string $resource = LiquidacionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $exists = Liquidacion::where('employee_id', $data['employee_id'])
            ->whereDate('termination_date', $data['termination_date'])
            ->exists();

        if ($exists) {
            Notification::make()
                ->danger()
                ->title('Liquidación duplicada')
                ->body('Ya existe una liquidación para este empleado con la misma fecha de egreso.')
                ->send();

            throw ValidationException::withMessages([
                'termination_date' => 'Ya existe una liquidación para este empleado con esa fecha de egreso.',
            ]);
        }

        $employee = Employee::find($data['employee_id']);

        $data['hire_date'] = $employee->hire_date;
        $data['base_salary'] = $employee->base_salary;
        $data['daily_salary'] = round($employee->base_salary / 30, 2);
        $data['created_by_id'] = Auth::id();
        $data['status'] = 'draft';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
