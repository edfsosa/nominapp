<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Employee;
use Filament\Widgets\ChartWidget;

class BranchAttendanceToday extends ChartWidget
{
    protected static ?string $heading = 'Presencias / Ausencias por Sucursal';
    protected static ?string $description = 'Estado de asistencia del día de hoy';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $today = now()->toDateString();

        // Obtener todas las sucursales con empleados activos
        $branches = Branch::withCount(['employees' => function ($query) {
            $query->where('status', 'active');
        }])
            ->having('employees_count', '>', 0)
            ->get();

        $labels = [];
        $presentes = [];
        $ausentes = [];
        $porcentajes = [];

        foreach ($branches as $branch) {
            $totalEmpleados = $branch->employees_count;

            // Empleados que marcaron asistencia hoy (presentes)
            $empleadosPresentes = AttendanceDay::where('date', $today)
                ->where('status', 'present')
                ->whereHas('employee', function ($query) use ($branch) {
                    $query->where('branch_id', $branch->id)
                        ->where('status', 'active');
                })
                ->count();

            $empleadosAusentes = $totalEmpleados - $empleadosPresentes;
            $porcentajePresentes = $totalEmpleados > 0
                ? round(($empleadosPresentes / $totalEmpleados) * 100, 1)
                : 0;

            $labels[] = $branch->name . " ({$totalEmpleados})";
            $presentes[] = $empleadosPresentes;
            $ausentes[] = $empleadosAusentes;
            $porcentajes[] = $porcentajePresentes;
        }

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

    protected function getType(): string
    {
        return 'bar';
    }

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
