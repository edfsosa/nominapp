<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\AttendanceDayResource;

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

            AttendanceDayResource::getExportPdfAction(),

            AttendanceDayResource::getCalculateAction(),
        ];
    }
}
