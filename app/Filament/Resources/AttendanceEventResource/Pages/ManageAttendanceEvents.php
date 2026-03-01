<?php

namespace App\Filament\Resources\AttendanceEventResource\Pages;

use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Resources\AttendanceEventResource;
use Filament\Actions\CreateAction;

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
                ->tooltip('Actualizar listado de marcaciones'),

            CreateAction::make()
                ->label('Nueva Marcación')
                ->icon('heroicon-o-plus')
                ->tooltip('Registrar marcación manual'),

            AttendanceEventResource::getExcelExportAction(),
        ];
    }

    public function getTabs(): array
    {
        $counts = DB::table('attendance_events')
            ->selectRaw('
                COUNT(*) as all_count,
                SUM(CASE WHEN event_type = "check_in" THEN 1 ELSE 0 END) as check_in_count,
                SUM(CASE WHEN event_type = "check_out" THEN 1 ELSE 0 END) as check_out_count,
                SUM(CASE WHEN event_type = "break_start" THEN 1 ELSE 0 END) as break_start_count,
                SUM(CASE WHEN event_type = "break_end" THEN 1 ELSE 0 END) as break_end_count,
                SUM(CASE WHEN DATE(recorded_at) = CURDATE() THEN 1 ELSE 0 END) as today_count
            ')
            ->first();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts->all_count)
                ->badgeColor('gray')
                ->icon('heroicon-o-finger-print'),

            'today' => Tab::make('Hoy')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('recorded_at', now()))
                ->badge($counts->today_count)
                ->badgeColor('primary')
                ->icon('heroicon-o-calendar-days'),

            'check_in' => Tab::make('Entradas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('event_type', 'check_in'))
                ->badge($counts->check_in_count)
                ->badgeColor('success')
                ->icon('heroicon-o-arrow-right-circle'),

            'check_out' => Tab::make('Salidas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('event_type', 'check_out'))
                ->badge($counts->check_out_count)
                ->badgeColor('danger')
                ->icon('heroicon-o-arrow-left-circle'),

            'break_start' => Tab::make('Inicio descanso')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('event_type', 'break_start'))
                ->badge($counts->break_start_count)
                ->badgeColor('warning')
                ->icon('heroicon-o-pause-circle'),

            'break_end' => Tab::make('Fin descanso')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('event_type', 'break_end'))
                ->badge($counts->break_end_count)
                ->badgeColor('info')
                ->icon('heroicon-o-play-circle'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'today';
    }
}
