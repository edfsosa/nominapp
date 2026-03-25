<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use App\Filament\Resources\AttendanceDayResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/** Página de creación manual de un registro de asistencia diaria. */
class CreateAttendanceDay extends CreateRecord
{
    protected static string $resource = AttendanceDayResource::class;

    /**
     * Notificación mostrada al crear el registro exitosamente.
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Asistencia registrada')
            ->body('El registro de asistencia fue creado correctamente.');
    }

    /**
     * Redirige al ViewRecord tras la creación.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
