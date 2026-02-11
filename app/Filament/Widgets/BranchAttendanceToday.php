<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceDay;
use App\Models\Branch;
use Filament\Widgets\ChartWidget;

/**
 * Widget de gráfico que muestra el estado de asistencia (presentes y ausentes) por sucursal para el día de hoy.
 */
class BranchAttendanceToday extends ChartWidget
{
    // Configuraciones del widget
    protected static ?string $heading = 'Presencias / Ausencias por Sucursal';
    protected static ?string $description = 'Estado de asistencia del día de hoy';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 2;

    /**
     * Obtiene los datos para el gráfico de asistencia por sucursal del día de hoy.
     *
     * @return array
     */
    protected function getData(): array
    {
        // Obtener la fecha actual en formato Y-m-d
        $today = now()->toDateString();

        // Obtener todas las sucursales con empleados activos
        $branches = Branch::withCount(['employees' => function ($query) {
            $query->where('status', 'active');
        }])->having('employees_count', '>', 0)->get();

        // Inicializar arrays para etiquetas, presentes, ausentes y porcentajes
        $labels = [];
        $presentes = [];
        $ausentes = [];
        $porcentajes = [];

        // Recorrer cada sucursal para calcular el total de empleados, presentes, ausentes y porcentaje
        foreach ($branches as $branch) {
            // Total de empleados activos en la sucursal
            $totalEmpleados = $branch->employees_count;

            // Empleados que marcaron asistencia hoy (presentes)
            $empleadosPresentes = AttendanceDay::where('date', $today)
                ->where('status', 'present')
                ->whereHas('employee', function ($query) use ($branch) {
                    $query->where('branch_id', $branch->id)
                        ->where('status', 'active');
                })
                ->count();

            // Empleados ausentes (total - presentes) y porcentaje de presentes
            $empleadosAusentes = $totalEmpleados - $empleadosPresentes;
            $porcentajePresentes = $totalEmpleados > 0
                ? round(($empleadosPresentes / $totalEmpleados) * 100, 1)
                : 0;

            // Agregar datos a los arrays para el gráfico
            $labels[] = $branch->name . " ({$totalEmpleados})";
            $presentes[] = $empleadosPresentes;
            $ausentes[] = $empleadosAusentes;
            $porcentajes[] = $porcentajePresentes;
        }

        // Retornar los datos formateados para el gráfico, incluyendo etiquetas y datasets para presentes y ausentes
        return [
            'datasets' => [
                [
                    'label' => 'Presentes',
                    'data' => $presentes,
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Ausentes',
                    'data' => $ausentes,
                    'backgroundColor' => '#ef4444',
                    'borderColor' => '#dc2626',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Define el tipo de gráfico a utilizar (en este caso, un gráfico de barras).
     *
     * @return string
     */
    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Configura las opciones del gráfico, incluyendo plugins para leyenda y tooltip, así como escalas para los ejes X e Y, y opciones de responsividad.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => false,
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'stacked' => false,
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                        'precision' => 0,
                    ],
                    'grid' => [
                        'display' => true,
                        'drawBorder' => false,
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}
