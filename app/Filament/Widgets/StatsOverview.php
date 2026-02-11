<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    /**
     * Obtiene las estadísticas para el widget de resumen, incluyendo empleados activos, presentes y ausentes hoy, inactivos/suspendidos, nómina del período actual y cumpleaños del mes. También incluye tendencias de empleados y asistencia.
     *
     * @return array
     */
    protected function getStats(): array
    {
        // Obtener la fecha actual para las consultas de asistencia y cumpleaños
        $today = Carbon::today();

        // Estadísticas de Empleados
        $totalEmpleados = Employee::count();
        $empleadosActivos = Employee::where('status', 'active')->count();
        $empleadosInactivos = Employee::where('status', 'inactive')->count();
        $empleadosSuspendidos = Employee::where('status', 'suspended')->count();

        // Estadísticas de Asistencia de Hoy
        $presentesHoy = AttendanceDay::where('date', $today)
            ->where('status', 'present')
            ->whereHas('employee', function ($query) {
                $query->where('status', 'active');
            })
            ->count();

        // Cálculo de ausentes y porcentaje de asistencia
        $ausentesHoy = $empleadosActivos - $presentesHoy;

        // Evitar división por cero y calcular porcentaje de asistencia
        $porcentajeAsistencia = $empleadosActivos > 0
            ? round(($presentesHoy / $empleadosActivos) * 100, 1)
            : 0;

        // Estadísticas de Tardanzas
        $tardesHoy = AttendanceDay::where('date', $today)
            ->where('late_minutes', '>', 0)
            ->whereHas('employee', function ($query) {
                $query->where('status', 'active');
            })
            ->count();

        // Estadísticas de Nómina
        $periodoActual = PayrollPeriod::where('status', 'processing')
            ->orWhere('status', 'draft')
            ->latest('start_date')
            ->first();

        // Cálculo de la nómina total del período actual, con manejo de caso sin período activo
        $totalNominaActual = $periodoActual
            ? Payroll::where('payroll_period_id', $periodoActual->id)->sum('net_salary')
            : 0;

        // Retornar las estadísticas como un array de objetos Stat, con configuraciones personalizadas para cada estadística, incluyendo descripciones, íconos, colores y gráficos de tendencia
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
                ->chart($this->getAttendanceTrend()),

            Stat::make('Ausentes Hoy', $ausentesHoy)
                ->description($tardesHoy . ' llegadas tarde')
                ->descriptionIcon('heroicon-o-clock')
                ->color($ausentesHoy > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-x-circle'),

            Stat::make('Inactivos/Suspendidos', $empleadosInactivos + $empleadosSuspendidos)
                ->description($empleadosInactivos . ' inactivos, ' . $empleadosSuspendidos . ' suspendidos')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->icon('heroicon-o-pause-circle'),

            Stat::make('Nómina Período Actual', '₲ ' . number_format($totalNominaActual, 0, ',', '.'))
                ->description($periodoActual ? $periodoActual->name : 'Sin período activo')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('info')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Cumpleaños del Mes', $this->getBirthdaysThisMonth())
                ->description('Celebraciones este mes')
                ->descriptionIcon('heroicon-o-cake')
                ->color('primary')
                ->icon('heroicon-o-gift')
                ->url(route('filament.admin.resources.employees.index', [
                    'tableFilters' => [
                        'birthday_month' => ['value' => now()->month]
                    ]
                ])),
        ];
    }

    /**
     * Obtiene la tendencia de empleados de los últimos 7 días
     */
    protected function getEmployeeTrend(): array
    {
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $trend[] = Employee::where('status', 'active')
                ->whereDate('created_at', '<=', $date)
                ->count();
        }
        return $trend;
    }

    /**
     * Obtiene la tendencia de asistencia de los últimos 7 días
     */
    protected function getAttendanceTrend(): array
    {
        $trend = [];
        $empleadosActivos = Employee::where('status', 'active')->count();

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $presentes = AttendanceDay::where('date', $date)
                ->where('status', 'present')
                ->whereHas('employee', function ($query) {
                    $query->where('status', 'active');
                })
                ->count();

            $trend[] = $empleadosActivos > 0
                ? round(($presentes / $empleadosActivos) * 100)
                : 0;
        }
        return $trend;
    }

    /**
     * Obtiene el número de cumpleaños del mes actual
     */
    protected function getBirthdaysThisMonth(): int
    {
        return Employee::where('status', 'active')
            ->whereMonth('birth_date', now()->month)
            ->count();
    }
}
