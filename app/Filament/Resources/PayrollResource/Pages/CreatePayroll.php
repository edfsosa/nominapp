<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePayroll extends CreateRecord
{
    protected static string $resource = PayrollResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Recibo creado')
            ->body("El recibo para {$this->record->employee->full_name} ha sido creado exitosamente.");
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Establecer la fecha de generación
        $data['generated_at'] = now();

        // Si no se proporciona base_salary, obtenerlo del empleado
        if (empty($data['base_salary'])) {
            $employee = \App\Models\Employee::find($data['employee_id']);
            $data['base_salary'] = $employee?->salary ?? 0;
        }

        // Calcular gross_salary si no se proporciona
        if (empty($data['gross_salary'])) {
            $data['gross_salary'] = $data['base_salary'] + ($data['total_perceptions'] ?? 0);
        }

        // Calcular net_salary si no se proporciona
        if (empty($data['net_salary'])) {
            $data['net_salary'] = $data['gross_salary'] - ($data['total_deductions'] ?? 0);
        }

        return $data;
    }
}
