<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSchedule extends CreateRecord
{
    protected static string $resource = ScheduleResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Horario creado')
            ->body("El horario \"{$this->record->name}\" ha sido creado correctamente.")
            ->send();
    }
}
