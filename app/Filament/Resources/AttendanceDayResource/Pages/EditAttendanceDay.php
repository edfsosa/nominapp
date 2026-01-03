<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use App\Filament\Resources\AttendanceDayResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceDay extends EditRecord
{
    protected static string $resource = AttendanceDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
