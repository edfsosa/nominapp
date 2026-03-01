<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use App\Filament\Widgets\AttendanceDayStatsWidget;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\AttendanceDayResource;
use App\Models\AttendanceDay;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceDays extends ListRecords
{
    protected static string $resource = AttendanceDayResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            AttendanceDayStatsWidget::class,
        ];
    }

    /**
     * Define las acciones del encabezado de la página
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            AttendanceDayResource::getCalculateTodayAction(),
            AttendanceDayResource::getCalculateRangeAction(),
            AttendanceDayResource::getApproveOvertimeRangeAction(),
            AttendanceDayResource::getExcelExportAction(),
        ];
    }

    /**
     * Define las pestañas de filtrado con estadísticas optimizadas
     *
     * @return array
     */
    public function getTabs(): array
    {
        // Optimización: una sola query para todos los contadores
        $stats = AttendanceDay::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = "on_leave" THEN 1 ELSE 0 END) as on_leave,
            SUM(CASE WHEN is_calculated = 1 THEN 1 ELSE 0 END) as calculated,
            SUM(CASE WHEN is_calculated = 0 THEN 1 ELSE 0 END) as not_calculated
        ')->first();

        return [
            'all' => Tab::make('Todos')
                ->badge($stats->total)
                ->badgeColor('gray')
                ->icon('heroicon-o-calendar-days'),

            'present' => Tab::make('Presentes')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'present'))
                ->badge($stats->present)
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle'),

            'absent' => Tab::make('Ausentes')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'absent'))
                ->badge($stats->absent)
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle'),

            'on_leave' => Tab::make('De permiso')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'on_leave'))
                ->badge($stats->on_leave)
                ->badgeColor('warning')
                ->icon('heroicon-o-pause-circle'),

            'calculated' => Tab::make('Calculados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_calculated', true))
                ->badge($stats->calculated)
                ->badgeColor('info')
                ->icon('heroicon-o-calculator'),

            'not_calculated' => Tab::make('Sin calcular')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_calculated', false))
                ->badge($stats->not_calculated)
                ->badgeColor('warning')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    /**
     * Define la pestaña activa por defecto
     *
     * @return string|int|null
     */
    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
