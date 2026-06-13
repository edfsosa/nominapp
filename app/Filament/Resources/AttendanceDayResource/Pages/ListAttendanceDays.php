<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use App\Filament\Pages\AttendanceReport;
use App\Filament\Resources\AttendanceDayResource;
use App\Models\AttendanceDay;
use Filament\Actions\Action;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/** Página de listado de asistencias diarias — muestra únicamente registros con estado presente. */
class ListAttendanceDays extends ListRecords
{
    protected static string $resource = AttendanceDayResource::class;

    /**
     * Define las acciones del encabezado de la página
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('go_to_report')
                ->label('Ver Reporte')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(AttendanceReport::getUrl()),

            AttendanceDayResource::getApproveOvertimeRangeAction(),

            AttendanceDayResource::getRegisterExtraHoursAction(),
        ];
    }

    /**
     * Define las pestañas de filtrado con estadísticas optimizadas
     */
    public function getTabs(): array
    {
        // Una sola query sobre registros presentes para todos los contadores
        $stats = AttendanceDay::where('status', 'present')->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN is_calculated = 1 THEN 1 ELSE 0 END) as calculated,
            SUM(CASE WHEN is_calculated = 0 THEN 1 ELSE 0 END) as not_calculated
        ')->first();

        return [
            'all' => Tab::make('Todos')
                ->badge($stats->total)
                ->badgeColor('gray')
                ->icon('heroicon-o-calendar-days'),

            'calculated' => Tab::make('Calculados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_calculated', true))
                ->badge($stats->calculated)
                ->badgeColor('info')
                ->icon('heroicon-o-calculator'),

            'not_calculated' => Tab::make('Sin calcular')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_calculated', false))
                ->badge($stats->not_calculated)
                ->badgeColor('warning')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    /**
     * Define la pestaña activa por defecto
     */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
