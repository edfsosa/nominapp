<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Models\Employee;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\EmployeeResource;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return Employee::sanitizeFormData($data, isCreating: true);
    }

    protected function getCreatedNotification(): Notification
    {
        return Notification::make()
            ->success()
            ->title('Empleado registrado exitosamente')
            ->body('El empleado ' . $this->record->full_name . ' ha sido creado correctamente.')
            ->duration(5000)
            ->send();
    }
}
