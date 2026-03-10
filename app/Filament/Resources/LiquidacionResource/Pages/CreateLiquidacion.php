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
        $activeLiquidacion = Liquidacion::where('employee_id', $data['employee_id'])
            ->whereIn('status', ['draft', 'calculated'])
            ->first();

        if ($activeLiquidacion) {
            $statusLabel = $activeLiquidacion->isDraft() ? 'borrador' : 'calculada';

            Notification::make()
                ->danger()
                ->title('Liquidación activa existente')
                ->body("Este empleado ya tiene una liquidación en estado {$statusLabel}. Debe cerrarla o eliminarla antes de crear una nueva.")
                ->send();

            throw ValidationException::withMessages([
                'employee_id' => "Este empleado ya tiene una liquidación en estado {$statusLabel}.",
            ]);
        }

        $employee = Employee::find($data['employee_id']);
        $contract = $employee->activeContract;

        if (!$contract) {
            Notification::make()
                ->danger()
                ->title('Sin contrato activo')
                ->body('Este empleado no tiene un contrato activo. No se puede crear una liquidación.')
                ->send();

            throw ValidationException::withMessages([
                'employee_id' => 'Este empleado no tiene un contrato activo.',
            ]);
        }

        $salary = (float) ($contract->salary ?? 0);

        if ($salary <= 0) {
            Notification::make()
                ->danger()
                ->title('Salario inválido')
                ->body('El contrato activo del empleado tiene un salario de 0. No se puede crear una liquidación.')
                ->send();

            throw ValidationException::withMessages([
                'employee_id' => 'El contrato activo del empleado tiene un salario de 0.',
            ]);
        }

        if ($contract->salary_type === 'jornal') {
            $dailySalary = $salary;
            $baseSalary  = round($salary * 30, 2);
        } else {
            $baseSalary  = $salary;
            $dailySalary = round($salary / 30, 2);
        }

        $data['hire_date']     = $contract->start_date;
        $data['base_salary']   = $baseSalary;
        $data['daily_salary']  = $dailySalary;
        $data['salary_type']   = $contract->salary_type;
        $data['created_by_id'] = Auth::id();
        $data['status']        = 'draft';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
