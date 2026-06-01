<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use App\Filament\Resources\AttendanceDayResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Página de visualización de un registro de asistencia diaria. */
class ViewAttendanceDay extends ViewRecord
{
    protected static string $resource = AttendanceDayResource::class;

    /**
     * Funcion que define las acciones del encabezado
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('primary'),

            AttendanceDayResource::getApproveOvertimeAction(),

            AttendanceDayResource::getApproveTardinessAction(),

            AttendanceDayResource::getExportPdfAction(),

            AttendanceDayResource::getCalculateAction(),
        ];
    }
}
