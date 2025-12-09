<?php

namespace App\Filament\Resources\EmployeeLeaveResource\Pages;

use App\Filament\Resources\EmployeeLeaveResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeLeave extends CreateRecord
{
    protected static string $resource = EmployeeLeaveResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Permiso de empleado creado correctamente')
            ->body('El permiso de empleado ha sido creado exitosamente.')
            ->duration(5000)
            ->send();
    }
}