<?php

namespace App\Filament\Resources\AttendanceEventResource\Pages;

use Filament\Resources\Components\Tab;
use App\Filament\Resources\AttendanceEventResource;
use App\Models\AttendanceEvent;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageAttendanceEvents extends ManageRecords
{
    protected static string $resource = AttendanceEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Actualizar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->dispatch('$refresh'))
                ->tooltip('Actualizar marcaciones'),
        ];
    }

    public function getTabs(): array
    {
        // Optimización: ejecutar queries una sola vez
        $allCount = AttendanceEvent::count();
        $checkInCount = AttendanceEvent::where('event_type', 'check_in')->count();
        $checkOutCount = AttendanceEvent::where('event_type', 'check_out')->count();
        $breakStartCount = AttendanceEvent::where('event_type', 'break_start')->count();
        $breakEndCount = AttendanceEvent::where('event_type', 'break_end')->count();
        $todayCount = AttendanceEvent::whereDate('recorded_at', now())->count();

        return [
            'all' => Tab::make('Todos')
                ->badge($allCount)
                ->badgeColor('gray')
                ->icon('heroicon-o-finger-print'),

            'today' => Tab::make('Hoy')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('recorded_at', now()))
                ->badge($todayCount)
                ->badgeColor('primary')
                ->icon('heroicon-o-calendar-days'),

            'check_in' => Tab::make('Entradas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('event_type', 'check_in'))
                ->badge($checkInCount)
                ->badgeColor('success')
                ->icon('heroicon-o-arrow-right-circle'),

            'check_out' => Tab::make('Salidas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('event_type', 'check_out'))
                ->badge($checkOutCount)
                ->badgeColor('danger')
                ->icon('heroicon-o-arrow-left-circle'),

            'break_start' => Tab::make('Inicio descanso')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('event_type', 'break_start'))
                ->badge($breakStartCount)
                ->badgeColor('warning')
                ->icon('heroicon-o-pause-circle'),

            'break_end' => Tab::make('Fin descanso')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('event_type', 'break_end'))
                ->badge($breakEndCount)
                ->badgeColor('info')
                ->icon('heroicon-o-play-circle'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'today';
    }
}
