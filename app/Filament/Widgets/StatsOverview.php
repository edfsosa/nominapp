<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceDay;
use App\Models\Employee;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = Carbon::today();

        $totalEmpleados   = Employee::count();
        $empleadosActivos = Employee::where('status', 'active')->count();

        $presentesHoy = AttendanceDay::where('date', $today)
            ->where('status', 'present')
            ->whereHas('employee', fn($q) => $q->where('status', 'active'))
            ->count();

        $ausentesHoy = $empleadosActivos - $presentesHoy;

        $porcentajeAsistencia = $empleadosActivos > 0
            ? round(($presentesHoy / $empleadosActivos) * 100, 1)
            : 0;

        return [
            Stat::make('Empleados Activos', $empleadosActivos)
                ->description('Total de ' . $totalEmpleados . ' empleados')
                ->descriptionIcon('heroicon-o-users')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->chart($this->getEmployeeTrend()),

            Stat::make('Presentes Hoy', $presentesHoy)
                ->description($porcentajeAsistencia . '% de asistencia')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($porcentajeAsistencia >= 90 ? 'success' : ($porcentajeAsistencia >= 70 ? 'warning' : 'danger'))
                ->icon('heroicon-o-user-group')
                ->chart($this->getAttendanceTrend($empleadosActivos)),

            Stat::make('Ausentes Hoy', $ausentesHoy)
                ->description('Faltas registradas hoy')
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color($ausentesHoy === 0 ? 'success' : 'danger')
                ->icon('heroicon-o-user-minus'),

            Stat::make('Cumpleaños del Mes', $this->getBirthdaysThisMonth())
                ->description('Celebraciones este mes')
                ->descriptionIcon('heroicon-o-cake')
                ->color('primary')
                ->icon('heroicon-o-gift')
                ->url(route('filament.admin.resources.employees.index', [
                    'tableFilters' => [
                        'birthday_month' => ['value' => now()->month],
                    ],
                ])),
        ];
    }

    /**
     * Tendencia de empleados activos en los últimos 7 días (2 queries en lugar de 7).
     */
    protected function getEmployeeTrend(): array
    {
        $windowStart = Carbon::today()->subDays(6)->startOfDay();

        $baseCount = Employee::where('status', 'active')
            ->where('created_at', '<', $windowStart)
            ->count();

        $perDay = Employee::where('status', 'active')
            ->where('created_at', '>=', $windowStart)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->map(fn($c) => (int) $c);

        $trend      = [];
        $cumulative = $baseCount;

        for ($i = 6; $i >= 0; $i--) {
            $dateStr    = Carbon::today()->subDays($i)->toDateString();
            $cumulative += $perDay->get($dateStr, 0);
            $trend[]    = $cumulative;
        }

        return $trend;
    }

    /**
     * Tendencia de asistencia (%) en los últimos 7 días (1 query en lugar de 7).
     */
    protected function getAttendanceTrend(int $empleadosActivos): array
    {
        $windowStart = Carbon::today()->subDays(6);

        $presentesPorDia = AttendanceDay::where('date', '>=', $windowStart)
            ->where('status', 'present')
            ->whereHas('employee', fn($q) => $q->where('status', 'active'))
            ->selectRaw('date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->mapWithKeys(fn($count, $date) => [Carbon::parse($date)->toDateString() => (int) $count]);

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $dateStr   = Carbon::today()->subDays($i)->toDateString();
            $presentes = $presentesPorDia->get($dateStr, 0);
            $trend[]   = $empleadosActivos > 0
                ? round(($presentes / $empleadosActivos) * 100)
                : 0;
        }

        return $trend;
    }

    protected function getBirthdaysThisMonth(): int
    {
        return Employee::where('status', 'active')
            ->whereMonth('birth_date', now()->month)
            ->count();
    }
}
