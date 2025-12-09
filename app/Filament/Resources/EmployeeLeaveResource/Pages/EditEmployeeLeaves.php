<?php

namespace App\Filament\Resources\EmployeeLeaveResource\Pages;

use App\Filament\Resources\EmployeeLeaveResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeLeave extends EditRecord
{
    protected static string $resource = EmployeeLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Eliminar')
                ->modalHeading('Eliminar permiso de empleado')
                ->modalDescription('¿Estás seguro de que deseas eliminar este permiso de empleado? Esta acción no se puede deshacer.')
                ->successNotificationTitle('Permiso de empleado eliminado')
                ->successRedirectUrl($this->getResource()::getUrl('index')),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Permiso de empleado actualizado con éxito')
            ->body('Los cambios en el permiso de empleado han sido guardados correctamente.')
            ->duration(5000)
            ->send();
    }
}
