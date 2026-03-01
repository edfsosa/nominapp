<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceDay;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AttendanceDayStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $today = Carbon::today();

        $stats = AttendanceDay::selectRaw("
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave,
            SUM(CASE WHEN late_minutes > 0 AND status = 'present' THEN 1 ELSE 0 END) as late
        ")->whereDate('date', $today)->first();

        $present        = (int) ($stats->present ?? 0);
        $absent         = (int) ($stats->absent ?? 0);
        $onLeave        = (int) ($stats->on_leave ?? 0);
        $late           = (int) ($stats->late ?? 0);

        return [
            Stat::make('Presentes hoy', $present)
                ->description($today->translatedFormat('l d/m/Y'))
                ->descriptionIcon('heroicon-o-calendar')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Ausentes hoy', $absent)
                ->description($onLeave > 0 ? "{$onLeave} con permiso" : 'Sin permisos activos')
                ->descriptionIcon('heroicon-o-pause-circle')
                ->color($absent > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-x-circle'),

            Stat::make('Tardanzas hoy', $late)
                ->description($late > 0 ? 'Llegaron tarde' : 'Sin tardanzas')
                ->descriptionIcon('heroicon-o-clock')
                ->color($late > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock'),
        ];
    }
}
