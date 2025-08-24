<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceDay;
use App\Models\Employee;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();

        $totalEmpleados = Employee::count();

        $empleadosActivos = Employee::where('status', 'active')->count();

        $empleadosInactivos = Employee::where('status', 'inactive')->count();

        $empleadosSuspendidos = Employee::where('status', 'suspended')->count();

        return [
            Stat::make('Total Empleados', $totalEmpleados)
                ->color('primary')
                ->icon('heroicon-o-users'),

            Stat::make('Empleados Activos', $empleadosActivos)
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Empleados Inactivos', $empleadosInactivos)
                ->color('warning')
                ->icon('heroicon-o-exclamation-circle'),

            Stat::make('Empleados Suspendidos', $empleadosSuspendidos)
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }
}
