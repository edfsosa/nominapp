<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Resources\DepartmentResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convertir el nombre del departamento a formato título (Primera Letra Mayúscula)
        if (isset($data['name'])) {
            $data['name'] = mb_convert_case($data['name'], MB_CASE_TITLE, 'UTF-8');
        }

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Departamento creado correctamente')
            ->body('El departamento "' . $this->record->name . '" ha sido creado exitosamente.')
            ->duration(5000)
            ->send();
    }
}
