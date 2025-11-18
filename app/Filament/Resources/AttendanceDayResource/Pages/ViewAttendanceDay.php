<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use App\Filament\Resources\AttendanceDayResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendanceDay extends ViewRecord
{
    protected static string $resource = AttendanceDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Exportar (PDF)')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('attendance-days.export', ['attendance_day' => $this->record->id]))
                ->openUrlInNewTab(),
        ];
    }
}
