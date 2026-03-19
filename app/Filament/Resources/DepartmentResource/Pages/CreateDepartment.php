<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Resources\DepartmentResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;

    /**
     * Mutar los datos del formulario antes de crear el registro, asegurando que el nombre del departamento tenga la primera letra de cada palabra en mayúscula.
     *
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['name'])) {
            $data['name'] = preg_replace_callback('/(?:^|\s)\S/u', fn($m) => mb_strtoupper($m[0], 'UTF-8'), $data['name']);
        }
        return $data;
    }

    /**
     * Definir la notificación personalizada que se muestra después de crear un departamento.
     *
     * @return Notification|null
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Departamento creado')
            ->body('El departamento "' . $this->record->name . '" de la empresa "' . $this->record->company->trade_name . '" ha sido creado exitosamente.')
            ->send();
    }
}
