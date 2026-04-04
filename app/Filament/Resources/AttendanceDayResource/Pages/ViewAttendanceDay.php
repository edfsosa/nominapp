<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\AttendanceDayResource;

/** Página de visualización de un registro de asistencia diaria. */
class ViewAttendanceDay extends ViewRecord
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
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),

            AttendanceDayResource::getApproveOvertimeAction(),

            AttendanceDayResource::getApproveTardinessAction(),

            AttendanceDayResource::getExportPdfAction(),

            AttendanceDayResource::getCalculateAction(),
        ];
    }
}
