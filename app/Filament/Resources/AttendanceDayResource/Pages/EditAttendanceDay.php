<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\AttendanceDayResource;

class EditAttendanceDay extends EditRecord
{
    protected static string $resource = AttendanceDayResource::class;

    /**
     * Funcion que define las acciones del encabezado
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),

            AttendanceDayResource::getApproveOvertimeAction(),

            AttendanceDayResource::getExportPdfAction(),

            AttendanceDayResource::getCalculateAction(),

            DeleteAction::make(),
        ];
    }

    /**
     * Funcion que define la notificación al guardar
     *
     * @return Notification|null
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Asistencia actualizada')
            ->body("Los cambios para {$this->record->employee->full_name} ({$this->record->date->format('d/m/Y')}) han sido guardados.")
            ->duration(5000);
    }

    /**
     * Funcion que define la URL de redirección después de guardar
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
