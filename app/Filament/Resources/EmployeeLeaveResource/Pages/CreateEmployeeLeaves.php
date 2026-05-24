<?php

namespace App\Filament\Resources\EmployeeLeaveResource\Pages;

use App\Filament\Resources\EmployeeLeaveResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeLeaves extends CreateRecord
{
    protected static string $resource = EmployeeLeaveResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Licencia registrada correctamente')
            ->body('La licencia ha sido registrada exitosamente.')
            ->duration(5000)
            ->send();
    }
}
