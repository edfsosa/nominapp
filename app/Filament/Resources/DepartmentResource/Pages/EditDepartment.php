<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Resources\DepartmentResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDepartment extends EditRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Eliminar')
                ->modalHeading('Eliminar departamento')
                ->modalDescription('¿Estás seguro de que deseas eliminar este departamento? Esta acción no se puede deshacer.')
                ->successNotificationTitle('Departamento eliminado')
                ->successRedirectUrl($this->getResource()::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convertir el nombre del departamento a formato título (Primera Letra Mayúscula)
        if (isset($data['name'])) {
            $data['name'] = mb_convert_case($data['name'], MB_CASE_TITLE, 'UTF-8');
        }
        return $data;
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Departamento actualizado con éxito')
            ->body('Los cambios en el departamento han sido guardados correctamente.')
            ->duration(5000)
            ->send();
    }
}
