<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Models\Employee;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\EmployeeResource;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    /**
     * Modifica los datos del formulario antes de crear el registro.
     *
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return Employee::sanitizeFormData($data, isCreating: true);
    }

    /**
     * Personaliza la notificación que se muestra después de crear el registro.
     *
     * @return Notification
     */
    protected function getCreatedNotification(): Notification
    {
        return Notification::make()
            ->success()
            ->title('Empleado registrado exitosamente')
            ->body('El empleado ' . $this->record->full_name . ' ha sido creado correctamente.')
            ->duration(5000)
            ->send();
    }

    /**
     * Muestra un aviso persistente si el empleado fue creado sin contrato.
     */
    protected function afterCreate(): void
    {
        if ($this->record->contracts()->doesntExist()) {
            Notification::make()
                ->warning()
                ->title('Contrato pendiente')
                ->body('El empleado fue creado sin contrato. Recordá crearlo desde la pestaña "Contratos" para que aparezca el cargo y salario.')
                ->persistent()
                ->send();
        }
    }
}
