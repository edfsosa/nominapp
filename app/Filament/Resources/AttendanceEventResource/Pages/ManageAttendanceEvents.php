<?php

namespace App\Filament\Resources\AttendanceEventResource\Pages;

use Filament\Resources\Components\Tab;
use App\Filament\Resources\AttendanceEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAttendanceEvents extends ManageRecords
{
    protected static string $resource = AttendanceEventResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label('Todos')
                ->badge($this->getResource()::getModel()::count())
                ->badgeColor('primary'),
            'check_in' => Tab::make()
                ->label('Entrada jornada')
                ->modifyQueryUsing(fn($query) => $query->where('event_type', 'check_in'))
                ->badge($this->getResource()::getModel()::where('event_type', 'check_in')->count())
                ->badgeColor('success'),
            'check_out' => Tab::make()
                ->label('Salida jornada')
                ->modifyQueryUsing(fn($query) => $query->where('event_type', 'check_out'))
                ->badge($this->getResource()::getModel()::where('event_type', 'check_out')->count())
                ->badgeColor('danger'),
            'break_start' => Tab::make()
                ->label('Inicio descanso')
                ->modifyQueryUsing(fn($query) => $query->where('event_type', 'break_start'))
                ->badge($this->getResource()::getModel()::where('event_type', 'break_start')->count())
                ->badgeColor('warning'),
            'break_end' => Tab::make()
                ->label('Fin descanso')
                ->modifyQueryUsing(fn($query) => $query->where('event_type', 'break_end'))
                ->badge($this->getResource()::getModel()::where('event_type', 'break_end')->count())
                ->badgeColor('warning'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
